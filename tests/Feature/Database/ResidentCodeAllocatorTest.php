<?php

namespace Tests\Feature\Database;

use App\Models\ArchivedRecord;
use App\Models\Resident;
use App\Models\User;
use App\Support\Population\PoocPopulation;
use App\Support\ResidentCodeAllocator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentCodeAllocatorTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    private int $nameSequence = 0;

    private function next(Resident $base, array $overrides = []): Resident
    {
        $attributes = $base->getAttributes();
        foreach (['id', 'official_resident_code', 'mobile_uuid', 'philsys_card_no', 'created_at', 'updated_at', 'deleted_at'] as $key) {
            unset($attributes[$key]);
        }
        $attributes['first_name'] = 'Allocation '.++$this->nameSequence;

        return Resident::create(array_replace($attributes, $overrides));
    }

    private function namespace(Resident $resident): int
    {
        return (int) $resident->household->purok->barangay_id;
    }

    private function high(int $namespace): int
    {
        return (int) DB::table('resident_code_sequences')->where('origin_barangay_id', $namespace)->value('last_value');
    }

    public function test_schema_has_permanent_namespace_primary_key_no_fk_and_unsigned_default(): void
    {
        $columns = array_column(Schema::getColumns('resident_code_sequences'), null, 'name');
        $this->assertSame(['origin_barangay_id', 'last_value'], array_keys($columns));
        foreach ($columns as $column) {
            $this->assertStringContainsString('bigint', $column['type']);
            $this->assertStringContainsString('unsigned', $column['type']);
            $this->assertFalse($column['nullable']);
        }
        $this->assertSame('0', (string) $columns['last_value']['default']);
        $indexes = Schema::getIndexes('resident_code_sequences');
        $this->assertCount(1, $indexes);
        $this->assertTrue($indexes[0]['primary']);
        $this->assertSame(['origin_barangay_id'], $indexes[0]['columns']);
        $this->assertSame([], Schema::getForeignKeys('resident_code_sequences'));
        $this->assertNotEmpty(array_filter(Schema::getIndexes('residents'), fn ($index) => $index['unique'] && $index['columns'] === ['official_resident_code']));
    }

    public function test_new_code_precedes_created_event_and_deleted_or_moved_people_never_release_suffixes(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        $second = $this->next($base);
        $this->assertSame(sprintf('RS-%04d-00002', $namespace), $second->official_resident_code);
        $second->delete();
        $other = $this->residentFixture(['first_name' => 'Different fixture']);
        $base->update(['household_id' => $other->household_id]);
        $third = $this->next($second, ['resident_status' => 'active', 'is_active' => true]);
        $this->assertSame(sprintf('RS-%04d-00003', $namespace), $third->official_resident_code);
        $third->forceDelete();
        $fourth = $this->next($second);
        $this->assertSame(sprintf('RS-%04d-00004', $namespace), $fourth->official_resident_code);
        $this->assertSame(4, $this->high($namespace));
    }

    public function test_initialization_uses_encoded_origin_and_archived_snapshot_even_without_live_resident(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        DB::table('residents')->where('id', $base->id)->update(['official_resident_code' => sprintf('RS-%04d-03002', $namespace), 'deleted_at' => now()]);
        ArchivedRecord::create(['original_table' => 'residents', 'original_id' => 999999,
            'data_snapshot' => ['official_resident_code' => sprintf('RS-%04d-04000', $namespace)],
            'archived_by' => User::factory()->create()->id, 'is_purged' => true]);
        DB::table('resident_code_sequences')->delete();
        $before = DB::table('residents')->orderBy('id')->get()->toJson();
        $this->artisan('residents:initialize-code-sequences')->assertSuccessful();
        $this->assertSame(4000, $this->high($namespace));
        $this->assertSame($before, DB::table('residents')->orderBy('id')->get()->toJson());
        $new = $this->next($base);
        $this->assertSame(sprintf('RS-%04d-04001', $namespace), $new->official_resident_code);
        $this->assertSame(0, DB::table('resident_lifecycle_events')->count());
    }

    public function test_first_use_self_initializes_from_all_physical_codes_and_does_not_depend_on_current_location(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        DB::table('residents')->where('id', $base->id)->update(['official_resident_code' => sprintf('RS-%04d-03002', $namespace)]);
        $oldHousehold = $base->household_id;
        $other = $this->residentFixture(['first_name' => 'Different fixture']);
        DB::table('residents')->where('id', $base->id)->update(['household_id' => $other->household_id, 'deleted_at' => now()]);
        DB::table('resident_code_sequences')->where('origin_barangay_id', $namespace)->delete();
        $new = $this->next($base, ['household_id' => $oldHousehold]);
        $this->assertSame(sprintf('RS-%04d-03003', $namespace), $new->official_resident_code);
    }

    public function test_explicit_codes_are_preserved_and_standard_reservations_only_move_forward(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        $custom = $this->next($base, ['official_resident_code' => 'PS-TEST-5']);
        $this->assertSame('PS-TEST-5', $custom->official_resident_code);
        $high = sprintf('RS-%04d-3500', $namespace);
        $this->assertSame($high, $this->next($base, ['official_resident_code' => $high])->official_resident_code);
        $this->next($base, ['official_resident_code' => sprintf('RS-%04d-01000', $namespace)]);
        $this->assertSame(3500, $this->high($namespace));
        $this->assertSame(sprintf('RS-%04d-03501', $namespace), $this->next($base)->official_resident_code);
        $this->expectException(UniqueConstraintViolationException::class);
        $this->next($base, ['official_resident_code' => 'PS-TEST-5']);
    }

    public function test_duplicate_explicit_standard_code_fails_without_alternate_identity_or_sequence_change(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        try {
            $this->next($base, ['official_resident_code' => $base->official_resident_code]);
            $this->fail('Duplicate standard code was accepted.');
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
        $this->assertSame(1, Resident::count());
        $this->assertSame(1, $this->high($namespace));
        $this->assertSame(sprintf('RS-%04d-00002', $namespace), $this->next($base)->official_resident_code);
    }

    public static function registryWriters(): array
    {
        return [['secretary'], ['admin']];
    }

    #[DataProvider('registryWriters')]
    public function test_existing_registry_creation_route_receives_code_inside_its_business_transaction(string $role): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => $role === 'secretary' ? $namespace : null]);
        $data = $base->only(['household_id', 'last_name', 'birth_place', 'sex', 'civil_status', 'citizenship', 'resident_status', 'is_active']);
        $data += ['first_name' => 'Route creation', 'birth_date' => $base->birth_date->toDateString(), 'relationship_to_head' => 'Daughter'];
        $response = $this->actingAs($user)->post(route($role.'.residents.store'), $data)->assertSessionHasNoErrors();
        $created = Resident::where('first_name', 'Route creation')->sole();
        $response->assertRedirect(route($role.'.residents.show', $created));
        $this->assertSame(sprintf('RS-%04d-00002', $namespace), $created->official_resident_code);
        $this->assertSame(2, $this->high($namespace));
        $this->assertSame(0, $created->lifecycle_version);
        $this->assertSame(0, DB::table('resident_lifecycle_events')->count());
    }

    public function test_code_is_available_before_insert_and_cancelled_save_can_retry_without_a_second_identity(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        $dispatcher = Resident::getEventDispatcher();
        Resident::setEventDispatcher(clone $dispatcher);
        $cancel = true;
        $seen = [];
        try {
            Resident::creating(function (Resident $resident) use (&$cancel, &$seen) {
                $seen[] = $resident->official_resident_code;
                $this->assertFalse($resident->exists);

                return $cancel ? false : null;
            });
            $attributes = $base->getAttributes();
            unset($attributes['id'], $attributes['official_resident_code']);
            $candidate = new Resident(array_replace($attributes, ['first_name' => 'Cancelled creation']));
            $this->assertFalse($candidate->save());
            $this->assertFalse($candidate->exists);
            $this->assertNull($candidate->official_resident_code);
            $this->assertDatabaseMissing('residents', ['first_name' => 'Cancelled creation']);
            $cancel = false;
            $this->assertTrue($candidate->save());
            $this->assertSame(sprintf('RS-%04d-00002', $namespace), $seen[0]);
            $this->assertSame(sprintf('RS-%04d-00003', $namespace), $seen[1]);
            $this->assertSame($seen[1], $candidate->official_resident_code);
        } finally {
            Resident::setEventDispatcher($dispatcher);
        }
    }

    public function test_explicit_origin_can_differ_from_current_barangay_without_reserving_current_namespace(): void
    {
        $base = $this->residentFixture();
        $other = $this->residentFixture();
        $origin = $this->namespace($other);
        $explicit = sprintf('RS-%04d-06000', $origin);
        $this->assertSame($explicit, $this->next($base, ['official_resident_code' => $explicit])->official_resident_code);
        $this->assertSame(6000, $this->high($origin));
        $this->assertSame(1, $this->high($this->namespace($base)));
        $this->assertSame(sprintf('RS-%04d-06001', $origin), $this->next($other)->official_resident_code);
    }

    public function test_historical_uncoded_and_malformed_records_are_never_backfilled_on_update_or_initialization(): void
    {
        $base = $this->residentFixture();
        foreach ([null, '', 'RS-legacy'] as $code) {
            DB::table('residents')->where('id', $base->id)->update(['official_resident_code' => $code]);
            $base->refresh()->update(['first_name' => 'Historical '.($code ?? 'null')]);
            app(ResidentCodeAllocator::class)->initialize();
            $this->assertSame($code, $base->fresh()->official_resident_code);
        }
        $this->assertSame(1, app(ResidentCodeAllocator::class)->observed()['malformed_standard_codes']);
        $this->assertSame(0, DB::table('resident_lifecycle_events')->count());
    }

    #[DataProvider('malformedCodes')]
    public function test_new_malformed_standard_like_codes_fail_without_fallback_identity(string $code): void
    {
        $base = $this->residentFixture();
        $this->expectException(ValidationException::class);
        $this->next($base, ['official_resident_code' => $code]);
    }

    public static function malformedCodes(): array
    {
        return [['RS-0027-wrong'], ['RS-27-00001'], ['RS-0000-00001'], ['RS-0027-00000'], ['RS-0027-99999999999999999999'], ['rs-0027-00002']];
    }

    public function test_ordinary_updates_and_household_or_purok_changes_preserve_code_and_version(): void
    {
        $resident = $this->residentFixture();
        $code = $resident->official_resident_code;
        $originalPurok = $resident->household->purok_id;
        $other = $this->residentFixture();
        $resident->update(['first_name' => 'Renamed', 'resident_status' => 'moved_out', 'household_id' => $other->household_id]);
        $this->assertSame($code, $resident->fresh()->official_resident_code);
        $other->household->update(['purok_id' => $originalPurok, 'household_no' => 'relocated-fixture']);
        $this->assertSame($code, $resident->fresh()->official_resident_code);
        $this->assertSame(0, $resident->fresh()->lifecycle_version);
        $this->assertSame(0, DB::table('resident_lifecycle_events')->count());
        $this->expectException(ValidationException::class);
        $resident->update(['official_resident_code' => 'replacement']);
    }

    public function test_failed_insert_restores_unsaved_model_and_sequence_and_can_retry_safely(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        $attributes = $base->getAttributes();
        unset($attributes['id'], $attributes['official_resident_code']);
        $candidate = new Resident($attributes);
        try {
            $candidate->save();
            $this->fail('Duplicate resident identity was accepted.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
        $this->assertFalse($candidate->exists);
        $this->assertNull($candidate->official_resident_code);
        $this->assertSame(1, $this->high($namespace));
        $candidate->first_name = 'Valid retry';
        $candidate->save();
        $this->assertSame(sprintf('RS-%04d-00002', $namespace), $candidate->official_resident_code);
        $this->assertSame(2, $this->high($namespace));
    }

    public function test_nested_save_never_commits_outer_transaction_and_rollback_leaves_no_issued_identity(): void
    {
        $base = $this->residentFixture();
        $level = DB::transactionLevel();
        $namespace = $this->namespace($base);
        DB::beginTransaction();
        try {
            $new = DB::transaction(fn () => $this->next($base));
            $this->assertSame($level + 1, DB::transactionLevel());
            $id = $new->id;
            $this->assertSame(2, $this->high($namespace));
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseMissing('residents', ['id' => $id]);
        $this->assertSame(1, $this->high($namespace));
        $this->assertSame(sprintf('RS-%04d-00002', $namespace), $this->next($base)->official_resident_code);
    }

    public function test_invalid_issuance_scope_fails_before_insert_and_sequence_survives_barangay_removal(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        try {
            $this->next($base, ['household_id' => 99999999]);
            $this->fail('Invalid namespace accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('official_resident_code', $exception->errors());
        }
        $base->household->purok->barangay->forceDelete();
        $this->assertSame(1, $this->high($namespace));
        $this->assertSame(0, PoocPopulation::operationalCounts()['resident_lifecycle_events']);
    }

    public function test_preflight_reports_behind_sequences_read_only_then_initialization_repairs_only_metadata(): void
    {
        $base = $this->residentFixture();
        $namespace = $this->namespace($base);
        DB::table('resident_code_sequences')->where('origin_barangay_id', $namespace)->update(['last_value' => 0]);
        $before = DB::table('residents')->get()->toJson();
        $this->assertSame(1, Artisan::call('residents:lifecycle-preflight', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertContains('resident_code_sequence_behind_issuance', array_column($report['blockers'], 'reason'));
        $this->assertSame(0, $this->high($namespace));
        app(ResidentCodeAllocator::class)->initialize();
        $this->assertSame(0, Artisan::call('residents:lifecycle-preflight', ['--json' => true]));
        $this->assertSame($before, DB::table('residents')->get()->toJson());
        $this->assertArrayNotHasKey('resident_code_sequences', PoocPopulation::operationalCounts());
        $this->assertArrayHasKey('resident_lifecycle_events', PoocPopulation::operationalCounts());
        $migration = require database_path('migrations/2026_10_05_000003_create_resident_code_sequences_table.php');
        $this->expectException(RuntimeException::class);
        $migration->down();
    }
}
