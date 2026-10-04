<?php

namespace Tests\Feature\Database;

use App\Models\Resident;
use App\Models\ResidentLifecycleEvent as Event;
use App\Models\User;
use App\Support\Lifecycle\LifecycleEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentLifecycleEventTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    private function record(Resident $resident, array $arguments = []): Event
    {
        return app(LifecycleEventRecorder::class)->record($resident, Event::REGISTRY_CAPTURE, 'fixture:capture', ...$arguments);
    }

    public function test_schema_has_exact_dormant_history_columns_constraints_and_indexes(): void
    {
        $columns = array_column(Schema::getColumns('resident_lifecycle_events'), null, 'name');
        $expected = ['id', 'resident_id', 'event_type', 'event_key', 'effective_date', 'recorded_at',
            'actor_user_id', 'actor_name_snapshot', 'actor_role_snapshot', 'remarks', 'metadata', 'provenance'];
        foreach (['source', 'destination'] as $side) {
            foreach (['barangay_id', 'purok_id', 'household_id', 'barangay_name_snapshot', 'purok_number_snapshot', 'purok_name_snapshot', 'household_no_snapshot'] as $field) {
                $expected[] = $side.'_'.$field;
            }
        }
        $this->assertEqualsCanonicalizing($expected, array_keys($columns));
        $this->assertSame('varchar(64)', $columns['event_type']['type']);
        $this->assertSame('varchar(100)', $columns['event_key']['type']);
        $this->assertSame('datetime(6)', $columns['recorded_at']['type']);
        $this->assertFalse($columns['recorded_at']['nullable']);
        $this->assertTrue($columns['effective_date']['nullable']);
        $indexes = Schema::getIndexes('resident_lifecycle_events');
        $this->assertNotEmpty(array_filter($indexes, fn ($i) => $i['unique'] && $i['columns'] === ['resident_id', 'event_key']));
        $this->assertNotEmpty(array_filter($indexes, fn ($i) => $i['columns'] === ['resident_id', 'recorded_at', 'id']));
        $foreignKeys = Schema::getForeignKeys('resident_lifecycle_events');
        $this->assertCount(8, $foreignKeys);
        foreach ($foreignKeys as $fk) {
            $this->assertSame($fk['columns'] === ['resident_id'] ? 'restrict' : 'set null', $fk['on_delete']);
        }
        foreach ($indexes as $index) {
            foreach ($index['columns'] as $field) {
                $this->assertFalse(str_ends_with($field, '_snapshot') || in_array($field, ['metadata', 'remarks']));
            }
        }
        $this->assertSame(['registry_capture', 'resident_registered', 'household_changed', 'barangay_transferred',
            'moved_out_of_municipality', 'returned_to_municipality', 'marked_deceased'], Event::TYPES);
    }

    public function test_recorder_persists_explicit_snapshots_metadata_provenance_and_utc_microseconds(): void
    {
        $resident = $this->residentFixture();
        $household = $resident->household;
        $purok = $household->purok;
        $barangay = $purok->barangay;
        $actor = User::factory()->create(['role' => 'bhw']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 13:14:15.123456', 'Asia/Manila'));
        try {
            $event = $this->record($resident, ['actor' => $actor, 'source' => ['barangay' => $barangay, 'purok' => $purok, 'household' => $household],
                'effectiveDate' => '2026-09-30', 'remarks' => 'Explicit fixture observation', 'metadata' => ['nested' => ['b' => 2, 'a' => 1]], 'provenance' => 'fixture:test']);
            $this->assertSame('2026-10-05 05:14:15.123456', DB::table('resident_lifecycle_events')->value('recorded_at'));
            $timezone = date_default_timezone_get();
            try {
                date_default_timezone_set('Asia/Manila');
                $this->assertSame('UTC', $event->fresh()->recorded_at->timezoneName);
                $this->assertSame('05:14:15.123456', $event->fresh()->recorded_at->format('H:i:s.u'));
            } finally {
                date_default_timezone_set($timezone);
            }
            $this->assertSame('2026-09-30', $event->effective_date->format('Y-m-d'));
            $this->assertSame($actor->display_name, $event->actor_name_snapshot);
            $this->assertSame('bhw', $event->actor_role_snapshot);
            $this->assertSame($barangay->name, $event->source_barangay_name_snapshot);
            $this->assertSame($purok->purok_number, $event->source_purok_number_snapshot);
            $this->assertSame($purok->purok_name, $event->source_purok_name_snapshot);
            $this->assertSame($household->household_no, $event->source_household_no_snapshot);
            $this->assertSame(['nested' => ['b' => 2, 'a' => 1]], $event->metadata);
            $this->assertSame('fixture:test', $event->provenance);
            $this->assertSame('Explicit fixture observation', $event->remarks);
            $this->assertNull($event->destination_barangay_id);
            $this->assertSame($resident->id, $event->resident->id);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_nullable_effective_date_has_no_invented_registration_or_location_facts(): void
    {
        $resident = $this->residentFixture();
        $event = $this->record($resident);
        $this->assertNull($event->effective_date);
        $this->assertNull($event->actor_user_id);
        $this->assertNull($event->actor_name_snapshot);
        $this->assertNull($event->source_household_id);
        $this->assertNull($event->source_barangay_name_snapshot);
        $this->assertFalse($event->timestamps);
        $this->assertSame(['*'], $event->getGuarded());
        $this->assertSame(0, $resident->fresh()->lifecycle_version);
    }

    public function test_identical_retry_reuses_event_and_freezes_renamed_actor_and_context(): void
    {
        $resident = $this->residentFixture();
        $household = $resident->household;
        $purok = $household->purok;
        $barangay = $purok->barangay;
        $actor = User::factory()->create(['role' => 'bhw']);
        $args = ['actor' => $actor, 'source' => ['barangay' => $barangay, 'purok' => $purok, 'household' => $household], 'metadata' => ['b' => 2, 'a' => 1]];
        $event = $this->record($resident, $args);
        $before = $event->getRawOriginal();
        $actor->update(['first_name' => 'Renamed', 'role' => 'bns']);
        $barangay->update(['name' => 'Renamed fixture barangay']);
        $purok->update(['purok_name' => 'Renamed fixture purok', 'purok_number' => 2]);
        $household->update(['household_no' => 'renumbered']);
        $args['metadata'] = ['a' => 1, 'b' => 2];
        $retry = $this->record($resident, $args);
        $this->assertSame($event->id, $retry->id);
        $this->assertSame($before, $retry->getRawOriginal());
        $this->assertSame(1, Event::count());
        $this->expectException(LogicException::class);
        $this->record($resident, ['remarks' => 'Different facts']);
    }

    public function test_database_rejects_duplicate_resident_key_but_different_residents_can_reuse_key(): void
    {
        $first = $this->record($this->residentFixture());
        $this->record($this->residentFixture());
        $attributes = $first->getRawOriginal();
        unset($attributes['id']);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('resident_lifecycle_events')->insert($attributes);
    }

    #[DataProvider('mutations')]
    public function test_persisted_history_rejects_model_quiet_and_bulk_mutation(string $method): void
    {
        $event = $this->record($this->residentFixture());
        $before = $event->getRawOriginal();
        try {
            match ($method) {
                'save' => $event->forceFill(['remarks' => 'changed'])->save(),
                'saveQuietly' => $event->forceFill(['remarks' => 'changed'])->saveQuietly(),
                'update' => $event->update(['remarks' => 'changed']),
                'updateQuietly' => $event->updateQuietly(['remarks' => 'changed']),
                'delete' => $event->delete(), 'deleteQuietly' => $event->deleteQuietly(), 'forceDelete' => $event->forceDelete(),
                'destroy' => Event::destroy($event->id),
                'bulkUpdate' => Event::whereKey($event->id)->update(['remarks' => 'changed']),
                'bulkDelete' => Event::whereKey($event->id)->delete(),
                'bulkForceDelete' => Event::whereKey($event->id)->forceDelete(),
                'increment' => $event->increment('resident_id'),
                'decrementQuietly' => $event->decrementQuietly('resident_id'),
                'incrementEach' => Event::query()->incrementEach(['resident_id' => 1]),
                'upsert' => Event::query()->upsert([['id' => $event->id, 'remarks' => 'changed']], ['id']),
            };
            $this->fail('History mutation was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
        $this->assertSame($before, $event->fresh()->getRawOriginal());
    }

    public static function mutations(): array
    {
        return array_map(fn ($method) => [$method], ['save', 'saveQuietly', 'update', 'updateQuietly', 'delete', 'deleteQuietly', 'forceDelete', 'destroy',
            'bulkUpdate', 'bulkDelete', 'bulkForceDelete', 'increment', 'decrementQuietly', 'incrementEach', 'upsert']);
    }

    public function test_policy_denies_direct_access_for_all_roles_and_admin_shortcut_is_narrow(): void
    {
        $resident = $this->residentFixture();
        $event = $this->record($resident);
        foreach (array_keys(User::ROLES) as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['create', 'viewAny'] as $ability) {
                $this->assertFalse(Gate::forUser($user)->allows($ability, Event::class));
            }
            foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
                $this->assertFalse(Gate::forUser($user)->allows($ability, $event));
            }
            if ($role === 'admin') {
                $this->assertTrue(Gate::forUser($user)->allows('update', $resident));
            }
        }
    }

    public function test_deleted_context_references_become_null_while_all_snapshots_survive(): void
    {
        $resident = $this->residentFixture();
        $other = $this->residentFixture();
        $household = $other->household;
        $purok = $household->purok;
        $barangay = $purok->barangay;
        $actor = User::factory()->create(['role' => 'bhw']);
        $event = $this->record($resident, ['actor' => $actor, 'destination' => ['barangay' => $barangay, 'purok' => $purok, 'household' => $household]]);
        $snapshots = array_filter($event->getRawOriginal(), fn ($key) => str_ends_with($key, '_snapshot'), ARRAY_FILTER_USE_KEY);
        $actor->forceDelete();
        $barangay->forceDelete();
        $event->refresh();
        $this->assertNull($event->actor_user_id);
        $this->assertNull($event->destination_barangay_id);
        $this->assertNull($event->destination_purok_id);
        $this->assertNull($event->destination_household_id);
        $this->assertSame($snapshots, array_filter($event->getRawOriginal(), fn ($key) => str_ends_with($key, '_snapshot'), ARRAY_FILTER_USE_KEY));
    }

    #[DataProvider('residentParents')]
    public function test_resident_history_restricts_direct_and_indirect_hard_deletion(string $target): void
    {
        $resident = $this->residentFixture();
        $event = $this->record($resident);
        $resident->delete();
        $this->assertSame($resident->id, $event->resident->id);
        $this->assertSame(1, Event::count());
        $model = match ($target) {
            'resident' => $resident, 'household' => $resident->household,
            'purok' => $resident->household->purok, 'barangay' => $resident->household->purok->barangay
        };
        try {
            $model->forceDelete();
            $this->fail('History was deleted by cascade.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
        $this->assertSame(1, Event::count());
        $this->assertDatabaseHas('residents', ['id' => $resident->id]);
    }

    public static function residentParents(): array
    {
        return [['resident'], ['household'], ['purok'], ['barangay']];
    }

    public function test_ordinary_resident_crud_never_emits_events_or_advances_version(): void
    {
        $resident = $this->residentFixture();
        $resident->update(['occupation' => 'Vendor', 'is_active' => false]);
        $resident->delete();
        $resident->restore();
        $this->assertSame(0, Event::count());
        $this->assertSame(0, $resident->fresh()->lifecycle_version);
    }

    public function test_rollback_refuses_existing_history_before_any_ddl(): void
    {
        $event = $this->record($this->residentFixture());
        $migration = require database_path('migrations/2026_10_05_000002_create_resident_lifecycle_events_table.php');
        try {
            $migration->down();
            $this->fail('Unsafe rollback was allowed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('while history exists', $exception->getMessage());
        }
        $this->assertDatabaseHas('resident_lifecycle_events', ['id' => $event->id]);
    }

    public function test_unsupported_event_type_is_rejected_without_history(): void
    {
        $resident = $this->residentFixture();
        try {
            app(LifecycleEventRecorder::class)->record($resident, 'invented_event', 'fixture');
            $this->fail('Invalid event accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }
        $this->assertSame(0, Event::count());
    }
}
