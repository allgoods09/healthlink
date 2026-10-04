<?php

namespace Tests\Feature\Database;

use App\Models\ArchivedRecord;
use App\Models\ResidentLifecycleEvent as Event;
use App\Models\User;
use App\Support\Lifecycle\LifecycleEventRecorder;
use App\Support\Lifecycle\RegistryCaptureBootstrap;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class RegistryCaptureBootstrapTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    private function bootstrap(): RegistryCaptureBootstrap
    {
        return app(RegistryCaptureBootstrap::class);
    }

    private function fingerprints(): array
    {
        $result = [];
        foreach (['residents', 'households', 'puroks', 'barangays', 'resident_code_sequences', 'archived_records', 'audit_logs'] as $table) {
            $result[$table] = hash('sha256', DB::table($table)->orderBy($table === 'resident_code_sequences' ? 'origin_barangay_id' : 'id')->get()->toJson());
        }

        return $result;
    }

    public function test_default_command_and_inventory_are_read_only_and_report_legacy_observations(): void
    {
        $resident = $this->residentFixture(['resident_status' => 'relocated', 'is_active' => true]);
        $archived = $this->residentFixture(['is_active' => false]);
        $archived->delete();
        $before = $this->fingerprints();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $report = $this->bootstrap()->inventory(batchSize: 1);
        $this->assertSame(2, $report['total_eligible']);
        $this->assertSame(0, $report['already_captured']);
        $this->assertSame(2, $report['would_create']);
        $this->assertSame(2, $report['estimated_batch_count']);
        $this->assertSame(1, $report['ambiguous_legacy_classifications']['legacy_relocated_requires_review']);
        $this->assertSame(1, $report['ambiguous_legacy_classifications']['relocated_but_active']);
        $this->assertSame(1, $report['ambiguous_legacy_classifications']['soft_deleted']);
        $this->assertSame([], $report['blockers']);
        $this->artisan('residents:lifecycle-bootstrap')->expectsOutputToContain('DRY RUN')->assertSuccessful();
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/^\s*(select|show)\b/i', $sql);
        }
        $this->assertSame($before, $this->fingerprints());
        $this->assertSame(0, Event::count());
        $this->assertSame('relocated', $resident->fresh()->resident_status);
    }

    public function test_apply_payload_is_truthful_utc_actorless_and_preserves_every_resident_field(): void
    {
        $resident = $this->residentFixture(['resident_status' => 'relocated', 'is_active' => true, 'contact_number' => 'Private contact']);
        $archived = $this->residentFixture(['resident_status' => 'deceased', 'is_active' => false, 'date_of_death' => '2024-01-01']);
        $archived->delete();
        $resident->household->delete();
        DB::table('residents')->where('id', $resident->id)->update(['created_at' => '2010-01-01 00:00:00', 'lifecycle_version' => 7]);
        $before = $this->fingerprints();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 13:14:15.123456', 'Asia/Manila'));
        try {
            $result = $this->bootstrap()->apply(batchSize: 1);
            $this->assertSame(2, $result['created']);
            $this->assertSame(2, $result['batches_completed']);
            $this->assertSame($before, $this->fingerprints());
            $this->assertSame(7, $resident->fresh()->lifecycle_version);
            foreach (Event::all() as $event) {
                $this->assertSame(Event::REGISTRY_CAPTURE, $event->event_type);
                $this->assertSame(Event::REGISTRY_CAPTURE, $event->event_key);
                $this->assertSame(Event::REGISTRY_CAPTURE, $event->provenance);
                $this->assertSame('Existing Registry Record Captured', $event->remarks);
                $this->assertNull($event->effective_date);
                $this->assertNull($event->actor_user_id);
                $this->assertNull($event->actor_name_snapshot);
                $this->assertNull($event->actor_role_snapshot);
                $this->assertSame('2026-10-05 05:14:15.123456', $event->recorded_at->format('Y-m-d H:i:s.u'));
                $this->assertSame('UTC', $event->recorded_at->timezoneName);
                $this->assertNull($event->destination_barangay_id);
                $this->assertSame('observed_at_capture', $event->metadata['context_semantics']);
                $this->assertSame(1, $event->metadata['capture_schema_version']);
                $this->assertSame($archived->id, $event->metadata['bootstrap']['through_id']);
                foreach (['birth_date', 'contact_number', 'philsys_card_no', 'first_name', 'last_name', 'socioeconomic', 'clinical'] as $field) {
                    $this->assertStringNotContainsString('"'.$field.'"', json_encode($event->metadata));
                }
            }
            $event = Event::where('resident_id', $resident->id)->sole();
            $household = $resident->household;
            $this->assertSame($household->id, $event->source_household_id);
            $this->assertSame($household->household_no, $event->source_household_no_snapshot);
            $this->assertSame($household->purok_id, $event->source_purok_id);
            $this->assertSame($household->purok->purok_number, $event->source_purok_number_snapshot);
            $this->assertSame($household->purok->purok_name, $event->source_purok_name_snapshot);
            $this->assertSame($household->purok->barangay_id, $event->source_barangay_id);
            $this->assertSame($household->purok->barangay->name, $event->source_barangay_name_snapshot);
            $this->assertSame('relocated', $event->metadata['observed']['resident_status']);
            $this->assertTrue($event->metadata['observed']['is_active']);
            $this->assertFalse($event->metadata['observed']['soft_deleted']);
            $this->assertSame(['household'], $event->metadata['observed']['soft_deleted_context']);
            $this->assertSame('2010-01-01 00:00:00', $event->metadata['observed']['legacy_dates']['created_at']);
            $this->assertTrue(Event::where('resident_id', $archived->id)->sole()->metadata['observed']['soft_deleted']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_explicit_apply_command_and_rerun_preserve_first_capture_even_after_profile_and_context_edits(): void
    {
        $resident = $this->residentFixture();
        $this->artisan('residents:lifecycle-bootstrap', ['--apply' => true, '--batch-size' => '1'])
            ->expectsOutputToContain('APPLY')->assertSuccessful();
        $before = DB::table('resident_lifecycle_events')->get()->toJson();
        $resident->update(['first_name' => 'Changed later', 'resident_status' => 'moved_out', 'is_active' => false]);
        $resident->household->purok->barangay->update(['name' => 'Later context name']);
        $resident->household->update(['household_no' => 'Renumbered']);
        $this->assertSame(0, $this->bootstrap()->apply()['created']);
        $this->assertSame($before, DB::table('resident_lifecycle_events')->get()->toJson());
        $this->assertSame(1, Event::count());
        $this->assertSame(0, $resident->fresh()->lifecycle_version);
    }

    public function test_interruption_resumes_original_cutoff_and_never_silently_includes_later_residents(): void
    {
        $first = $this->residentFixture();
        $second = $this->residentFixture();
        $third = $this->residentFixture();
        $late = null;
        $level = DB::transactionLevel();
        try {
            $this->bootstrap()->apply(batchSize: 2, afterBatch: function ($progress) use (&$late, $level, $second): void {
                $this->assertSame($level, DB::transactionLevel());
                $this->assertSame(2, $progress['created']);
                $this->assertSame($second->id, $progress['last_committed_id']);
                $late = $this->residentFixture();
                throw new RuntimeException('Simulated interruption after committed batch.');
            });
            $this->fail('Interruption was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated interruption after committed batch.', $exception->getMessage());
        }
        $this->assertSame(2, Event::count());
        $report = $this->bootstrap()->inventory();
        $this->assertSame($third->id, $report['through_id']);
        $this->assertSame('retained_capture', $report['cutoff_source']);
        $this->assertSame(3, $report['total_eligible']);
        $this->assertSame(1, $report['would_create']);
        $this->assertSame(1, $this->bootstrap()->apply()['created']);
        $this->assertDatabaseMissing('resident_lifecycle_events', ['resident_id' => $late->id]);
        $this->assertSame(0, $this->bootstrap()->apply()['created']);
        $this->assertSame(1, Event::where('resident_id', $first->id)->count());
    }

    public function test_explicit_bounded_window_and_midrun_creation_are_respected(): void
    {
        $first = $this->residentFixture();
        $second = $this->residentFixture();
        $last = $this->residentFixture();
        $late = null;
        $result = $this->bootstrap()->apply($second->id, 1, function ($progress) use (&$late): void {
            if ($progress['batches_completed'] === 1) {
                $late = $this->residentFixture();
            }
        });
        $this->assertSame(2, $result['created']);
        $this->assertSame($second->id, $result['through_id']);
        $this->assertSame([$first->id, $second->id], Event::orderBy('resident_id')->pluck('resident_id')->all());
        $this->assertDatabaseMissing('resident_lifecycle_events', ['resident_id' => $last->id]);
        $this->assertDatabaseMissing('resident_lifecycle_events', ['resident_id' => $late->id]);
    }

    public function test_failed_batch_rolls_back_all_its_events_but_preserves_previous_batches(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->residentFixture();
        }
        $before = $this->fingerprints();
        $real = new LifecycleEventRecorder;
        $calls = 0;
        $recorder = $this->mock(LifecycleEventRecorder::class);
        $recorder->shouldReceive('record')->andReturnUsing(function (...$args) use ($real, &$calls) {
            $calls++;
            if ($calls === 4) {
                throw new RuntimeException('Simulated recorder failure.');
            }

            return $real->record(...$args);
        });
        try {
            (new RegistryCaptureBootstrap($recorder))->apply(batchSize: 2);
            $this->fail('Recorder failure did not roll back the batch.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated recorder failure.', $exception->getMessage());
        }
        $this->assertSame(2, Event::count());
        $this->assertSame($before, $this->fingerprints());
        $result = (new RegistryCaptureBootstrap($real))->apply(batchSize: 2);
        $this->assertSame(3, $result['created']);
        $this->assertSame(5, Event::count());
    }

    public function test_failure_before_first_commit_can_resume_with_the_explicit_original_cutoff(): void
    {
        $first = $this->residentFixture();
        $last = $this->residentFixture();
        $recorder = $this->mock(LifecycleEventRecorder::class);
        $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('First batch failed.'));
        try {
            (new RegistryCaptureBootstrap($recorder))->apply($last->id, 1);
            $this->fail('First batch failure was swallowed.');
        } catch (RuntimeException) {
            $this->assertSame(0, Event::count());
        }
        $late = $this->residentFixture();
        $result = (new RegistryCaptureBootstrap(new LifecycleEventRecorder))->apply($last->id, 1);
        $this->assertSame(2, $result['created']);
        $this->assertSame([$first->id, $last->id], Event::orderBy('resident_id')->pluck('resident_id')->all());
        $this->assertDatabaseMissing('resident_lifecycle_events', ['resident_id' => $late->id]);
    }

    public static function missingChains(): array
    {
        return [['household'], ['purok'], ['barangay']];
    }

    #[DataProvider('missingChains')]
    public function test_missing_context_is_captured_truthfully_without_fabrication_or_exclusion(string $missing): void
    {
        $resident = $this->residentFixture();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Schema::disableForeignKeyConstraints();
        try {
            match ($missing) {
                'household' => DB::table('residents')->where('id', $resident->id)->update(['household_id' => 99999999]),
                'purok' => DB::table('households')->where('id', $resident->household_id)->update(['purok_id' => 99999999]),
                'barangay' => DB::table('puroks')->where('id', $resident->household->purok_id)->update(['barangay_id' => 99999999]),
            };
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $before = $this->fingerprints();
        $report = $this->bootstrap()->inventory();
        $this->assertSame(1, $report['missing_context']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame(1, $this->bootstrap()->apply()['created']);
        $event = Event::sole();
        $this->assertNull($event->{'source_'.$missing.'_id'});
        $this->assertContains('missing_'.$missing, $event->metadata['observed']['missing_context']);
        $this->assertSame($before, $this->fingerprints());
    }

    public function test_context_deletion_nulls_references_but_never_changes_observed_snapshots(): void
    {
        $resident = $this->residentFixture();
        $oldBarangay = $resident->household->purok->barangay;
        $this->bootstrap()->apply();
        $event = Event::sole();
        $snapshots = array_filter($event->getRawOriginal(), fn ($key) => str_ends_with($key, '_snapshot'), ARRAY_FILTER_USE_KEY);
        $other = $this->residentFixture(['first_name' => 'Other person']);
        $resident->update(['household_id' => $other->household_id]);
        $oldBarangay->forceDelete();
        $event->refresh();
        $this->assertNull($event->source_barangay_id);
        $this->assertNull($event->source_purok_id);
        $this->assertNull($event->source_household_id);
        $this->assertSame($snapshots, array_filter($event->getRawOriginal(), fn ($key) => str_ends_with($key, '_snapshot'), ARRAY_FILTER_USE_KEY));
    }

    public function test_captured_household_can_still_be_archived_and_restored_but_resident_cannot_be_hard_deleted(): void
    {
        $resident = $this->residentFixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->bootstrap()->apply();
        $event = Event::sole()->getRawOriginal();
        $this->actingAs($admin)->post(route('admin.archive.store'), ['table' => 'households', 'record_id' => $resident->household_id,
            'reason' => 'Test archive after registry capture'])->assertRedirect(route('admin.archive.index'));
        $this->assertTrue($resident->fresh()->trashed());
        $archive = ArchivedRecord::sole();
        $this->patch(route('admin.archive.restore', $archive))->assertRedirect(route('admin.archive.index'))->assertSessionHasNoErrors();
        $this->assertFalse($resident->fresh()->trashed());
        $this->assertFalse($resident->household()->withTrashed()->firstOrFail()->trashed());
        $this->assertSame($event, Event::sole()->getRawOriginal());
        $this->assertSame(0, $resident->fresh()->lifecycle_version);
        try {
            $resident->forceDelete();
            $this->fail('Capture history was erased by hard deletion.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
        $this->assertSame(1, Event::count());
    }

    public function test_preflight_reports_read_only_coverage_and_other_event_counts_without_blocking_missing_history(): void
    {
        $first = $this->residentFixture();
        $second = $this->residentFixture();
        $this->bootstrap()->apply($first->id);
        app(LifecycleEventRecorder::class)->record($second, Event::HOUSEHOLD_CHANGED, 'fixture:other');
        $before = DB::table('resident_lifecycle_events')->orderBy('id')->get()->toJson();
        $this->assertSame(0, Artisan::call('residents:lifecycle-preflight', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['history']['with_registry_capture']);
        $this->assertSame(1, $report['history']['missing_registry_capture']);
        $this->assertSame(1, $report['history']['other_event_counts'][Event::HOUSEHOLD_CHANGED]);
        $this->assertSame([], $report['history']['blockers']);
        $this->assertSame($before, DB::table('resident_lifecycle_events')->orderBy('id')->get()->toJson());
    }

    public static function invalidBaselines(): array
    {
        return [['type'], ['date'], ['provenance'], ['actor'], ['duplicate']];
    }

    #[DataProvider('invalidBaselines')]
    public function test_incompatible_or_duplicate_history_blocks_capture_instead_of_rewriting_it(string $problem): void
    {
        $resident = $this->residentFixture();
        $recorder = app(LifecycleEventRecorder::class);
        $recorder->record($resident, $problem === 'type' ? Event::RESIDENT_REGISTERED : Event::REGISTRY_CAPTURE, Event::REGISTRY_CAPTURE,
            actor: $problem === 'actor' ? User::factory()->create() : null,
            effectiveDate: $problem === 'date' ? '2026-01-01' : null,
            provenance: $problem === 'provenance' ? 'unrelated' : Event::REGISTRY_CAPTURE);
        if ($problem === 'duplicate') {
            $recorder->record($resident, Event::REGISTRY_CAPTURE, 'fixture:second-baseline', provenance: Event::REGISTRY_CAPTURE);
        }
        $before = DB::table('resident_lifecycle_events')->orderBy('id')->get()->toJson();
        $this->assertNotEmpty($this->bootstrap()->inventory()['blockers']);
        $this->assertSame(1, Artisan::call('residents:lifecycle-preflight', ['--json' => true]));
        try {
            $this->bootstrap()->apply();
            $this->fail('Incompatible baseline was ignored.');
        } catch (LogicException) {
            $this->assertSame($before, DB::table('resident_lifecycle_events')->orderBy('id')->get()->toJson());
        }
    }

    public static function invalidOptions(): array
    {
        return [['--batch-size', '0'], ['--batch-size', '1001'], ['--batch-size', 'bad'], ['--through-id', '-1'], ['--through-id', '9999999999999999999999']];
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_apply_options_fail_without_writes(string $option, string $value): void
    {
        $this->residentFixture();
        $before = $this->fingerprints();
        $this->artisan('residents:lifecycle-bootstrap', ['--apply' => true, $option => $value])->assertFailed();
        $this->assertSame(0, Event::count());
        $this->assertSame($before, $this->fingerprints());
    }

    public function test_empty_registry_is_a_safe_noop_and_missing_recorder_schema_is_a_blocker(): void
    {
        $this->assertSame(0, $this->bootstrap()->inventory()['estimated_batch_count']);
        $this->assertSame(0, $this->bootstrap()->apply()['created']);
        Schema::rename('resident_lifecycle_events', 'l1d_test_events');
        try {
            $this->assertSame(['available' => false, 'blockers' => []], $this->bootstrap()->coverage());
            $this->artisan('residents:lifecycle-bootstrap', ['--apply' => true])->assertFailed();
            $this->assertSame(0, DB::table('l1d_test_events')->count());
        } finally {
            Schema::rename('l1d_test_events', 'resident_lifecycle_events');
        }
    }
}
