<?php

namespace Tests\Feature\Bns;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\ChildNutritionProfile;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramEnrollment;
use App\Models\Household;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptCycle;
use App\Models\OptCycleEntry;
use App\Models\OptMeasurement;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\Nutrition\GrowthAssessmentService;
use App\Support\Nutrition\LegacyOptInventory;
use App\Support\Nutrition\OptCycleDataset;
use App\Support\Nutrition\OptCycleReporting;
use App\Support\Nutrition\OptCycleWorkflow;
use Database\Seeders\MockOperationalDataSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OptCycleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $bns;

    private Barangay $barangay;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 27)->startOfDay());
        $this->barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $this->barangay->id]);
        $this->household = Household::create(['purok_id' => $purok->id, 'household_no' => '12', 'household_address' => 'Pilot address', 'is_active' => true]);
        $this->bns = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => $this->barangay->id]);
    }

    private function child(array $values = []): Resident
    {
        return Resident::create($values + ['household_id' => $this->household->id, 'first_name' => 'Ana', 'last_name' => 'Santos',
            'birth_date' => '2024-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Child', 'is_active' => true, 'resident_status' => 'active']);
    }

    private function cycle(string $round = 'july'): OptCycle
    {
        return app(OptCycleWorkflow::class)->create($this->bns, ['year' => 2026, 'round' => $round,
            'reference_date' => $round === 'july' ? '2026-07-01' : '2026-01-01']);
    }

    private function measurement(array $values = []): array
    {
        return $values + ['measurement_date' => '2026-07-02', 'weight_kg' => 10.5, 'height_cm' => 85, 'measurement_posture' => 'standing'];
    }

    public function test_complete_roster_uses_reference_date_and_active_barangay_scope(): void
    {
        $newborn = $this->child(['first_name' => 'Newborn', 'birth_date' => '2026-07-01']);
        $almostFive = $this->child(['first_name' => 'AlmostFive', 'birth_date' => '2021-07-02']);
        $this->child(['first_name' => 'Five', 'birth_date' => '2021-07-01']);
        $this->child(['first_name' => 'Future', 'birth_date' => '2026-07-02']);
        $unavailable = $this->child(['first_name' => 'Inactive', 'is_active' => false]);
        $this->child(['first_name' => 'Moved', 'resident_status' => 'relocated']);
        $other = Purok::factory()->create();
        $foreignHouse = Household::create(['purok_id' => $other->id, 'household_no' => '99', 'household_address' => 'Other address', 'is_active' => true]);
        $this->child(['first_name' => 'Other', 'household_id' => $foreignHouse->id]);
        $cycle = $this->cycle();
        $this->assertEqualsCanonicalizing([$newborn->id, $almostFive->id, $unavailable->id], $cycle->entries->pluck('resident_key')->all());
        $this->assertSame('July 2026 OPT+', $cycle->title);
        $this->assertSame('Unmeasured', $cycle->entries->first()->measurement_status);
    }

    public function test_snapshots_survive_profile_edits_moves_deletion_and_new_registration(): void
    {
        $child = $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $child->update(['first_name' => 'Changed', 'resident_status' => 'relocated']);
        $this->household->update(['household_address' => 'Changed address']);
        $this->child(['first_name' => 'NewChild']);
        $child->delete();
        $this->assertSame('Ana', $entry->fresh()->first_name);
        $this->assertSame('Pilot address', $entry->fresh()->address);
        $this->assertSame(1, $cycle->entries()->count());
        $child->forceDelete();
        $this->assertNull($entry->fresh()->resident_id);
        $this->assertSame($child->id, $entry->fresh()->resident_key);
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.show', $cycle))->assertOk()->assertSee('Santos, Ana');
    }

    public function test_duplicate_cycles_rejected_but_january_and_july_are_independent(): void
    {
        $this->cycle();
        $this->cycle('january');
        $response = $this->actingAs($this->bns)->post(route('bns.opt-cycles.store'), ['year' => 2026, 'round' => 'july', 'reference_date' => '2026-07-02']);
        $response->assertSessionHasErrors('round');
        $this->assertSame(2, OptCycle::count());
    }

    public function test_database_unique_constraint_protects_concurrent_duplicate_insert(): void
    {
        $cycle = $this->cycle();
        $this->expectException(UniqueConstraintViolationException::class);
        $cycle->replicate()->save();
    }

    public function test_capture_is_atomic_if_entry_creation_fails(): void
    {
        $this->child();
        OptCycleEntry::creating(fn () => throw new \RuntimeException('Capture failure'));
        try {
            $this->cycle();
            $this->fail('Expected capture failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Capture failure', $exception->getMessage());
            $this->assertSame(0, OptCycle::count());
            $this->assertSame(0, OptCycleEntry::count());
        } finally {
            OptCycleEntry::flushEventListeners();
        }
    }

    public function test_caregiver_persists_for_future_cycles_without_rewriting_history(): void
    {
        $child = $this->child();
        $january = $this->cycle('january');
        $entry = $january->entries()->firstOrFail();
        $workflow = app(OptCycleWorkflow::class);
        $workflow->confirmProfile($this->bns, $january, $entry, ['caregiver_name' => 'Maria Santos', 'ip_membership' => 'no',
            'update_cycle_snapshot' => true, 'correction_reason' => 'Caregiver confirmed during this cycle.']);
        $workflow->confirmProfile($this->bns, $january, $entry, ['caregiver_name' => 'Elena Santos', 'caregiver_relationship' => 'Guardian', 'ip_membership' => 'no']);
        $julyEntry = $this->cycle()->entries()->firstOrFail();
        $this->assertSame('Maria Santos', $entry->fresh()->caregiver_name);
        $this->assertSame('Elena Santos', $julyEntry->caregiver_name);
        $this->assertSame('Elena Santos', $child->fresh()->childNutritionProfile->caregiver_name);
        $this->assertSame([], $julyEntry->readiness_issues);
        $this->assertTrue(AuditLog::where('model_type', OptCycleEntry::class)->exists());
    }

    public function test_registered_caregiver_is_scoped_and_name_is_snapshotted(): void
    {
        $this->child();
        $mother = $this->child(['first_name' => 'Mother', 'birth_date' => '1990-01-01']);
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.caregiver', [$cycle, $entry]), [
            'caregiver_resident_id' => $mother->id, 'ip_membership' => 'no', 'update_cycle_snapshot' => 1, 'correction_reason' => 'Mother confirmed',
        ])->assertRedirect();
        $mother->update(['first_name' => 'Renamed']);
        $this->assertSame('Mother Santos', $entry->fresh()->caregiver_name);
        $this->assertSame('Mother Santos', ChildNutritionProfile::firstOrFail()->caregiver_name);
    }

    public function test_measurements_can_be_corrected_without_duplicate_storage_or_flag_closure(): void
    {
        $child = $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $child->update(['birth_date' => '1990-01-01']);
        $url = route('bns.opt-cycles.measure', [$cycle, $entry]);
        $this->actingAs($this->bns)->put($url, $this->measurement())->assertRedirect();
        $this->actingAs($this->bns)->put($url, $this->measurement(['weight_kg' => 11]))->assertRedirect();
        $this->assertSame(1, OptMeasurement::count());
        $this->assertEquals(11, $entry->fresh()->measurement->weight_kg);
        $this->assertSame('Measured', $entry->fresh()->measurement_status);
        $this->assertNotEmpty($entry->fresh()->readiness_issues);
        $this->assertSame($entry->measurement->id, $child->fresh()->latestOptMeasurement->id);
        $this->assertSame(30, $entry->fresh()->measurement->age_in_months);
        $this->assertSame('in_progress', $cycle->fresh()->status);
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.entry', [$cycle, $entry]))->assertOk();
    }

    public function test_reference_assessment_failure_does_not_lose_raw_measurement(): void
    {
        $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $this->mock(GrowthAssessmentService::class, fn ($mock) => $mock->shouldReceive('assess')->andThrow(new \InvalidArgumentException('No row')));
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.measure', [$cycle, $entry]), $this->measurement())->assertRedirect();
        $measurement = $entry->fresh()->measurement;
        $this->assertEquals(10.5, $measurement->weight_kg);
        $this->assertNull($measurement->weight_for_age_status);
        $this->assertNotNull($measurement->assessment_error);
    }

    public function test_completion_requires_incomplete_confirmation_and_locks_until_reopened(): void
    {
        $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $this->actingAs($this->bns)->post(route('bns.opt-cycles.complete', $cycle))->assertSessionHasErrors('completion_note');
        $this->actingAs($this->bns)->post(route('bns.opt-cycles.complete', $cycle), ['confirm_unmeasured' => 1, 'completion_note' => 'Child unavailable'])->assertRedirect();
        $this->assertSame('completed', $cycle->fresh()->status);
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.measure', [$cycle, $entry]), $this->measurement())->assertSessionHasErrors('cycle');
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.caregiver', [$cycle, $entry]), ['caregiver_name' => 'Name', 'ip_membership' => 'no'])->assertSessionHasErrors('cycle');
        $this->actingAs($this->bns)->post(route('bns.opt-cycles.reopen', $cycle))->assertSessionHasErrors('reopening_reason');
        $this->actingAs($this->bns)->post(route('bns.opt-cycles.reopen', $cycle), ['reopening_reason' => 'Correct measurement'])->assertRedirect();
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.measure', [$cycle, $entry]), $this->measurement())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Correct measurement', $cycle->fresh()->reopening_reason);
    }

    public function test_authorization_rejects_other_barangay_nested_entries_and_non_bns(): void
    {
        $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $january = $this->cycle('january');
        $otherBns = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        foreach (['show', 'export'] as $route) {
            $this->actingAs($otherBns)->get(route('bns.opt-cycles.'.$route, ['optCycle' => $cycle->id, 'format' => 'csv']))->assertNotFound();
        }
        $this->actingAs($otherBns)->put(route('bns.opt-cycles.measure', [$cycle, $entry]), $this->measurement())->assertNotFound();
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.entry', [$january, $entry]))->assertNotFound();
        $unassigned = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => null]);
        $this->actingAs($unassigned)->get(route('bns.opt-cycles.index'))->assertForbidden();
        $phn = User::factory()->create(['role' => 'phn']);
        $this->actingAs($phn)->get(route('bns.opt-cycles.show', $cycle))->assertForbidden();
    }

    public function test_reference_metadata_is_immutable_and_validation_rejects_future_dates(): void
    {
        $this->actingAs($this->bns)->post(route('bns.opt-cycles.store'), ['year' => 2027, 'round' => 'january', 'reference_date' => '2027-01-01'])->assertSessionHasErrors('reference_date');
        $this->actingAs($this->bns)->post(route('bns.opt-cycles.store'), ['year' => 2026, 'round' => 'july', 'reference_date' => '2026-01-01'])->assertSessionHasErrors('reference_date');
        $cycle = $this->cycle();
        $this->expectException(\LogicException::class);
        $cycle->update(['reference_date' => '2026-07-02']);
    }

    public function test_export_matches_search_filters_order_without_pagination(): void
    {
        foreach (range(1, 20) as $number) {
            $this->child(['first_name' => sprintf('Child%02d', $number)]);
        }
        $cycle = $this->cycle();
        $filters = ['search' => 'Child', 'purok_key' => $this->household->purok_id, 'measurement_status' => 'unmeasured', 'readiness' => 'incomplete', 'sort' => 'name', 'direction' => 'desc'];
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.show', ['optCycle' => $cycle->id] + $filters))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->count() === 15);
        $csv = $this->actingAs($this->bns)->get(route('bns.opt-cycles.export', ['optCycle' => $cycle->id, 'format' => 'csv', 'page' => 2] + $filters))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(21, $lines);
        $this->assertSame('Santos, Child20', $lines[1][3]);
        $this->assertSame('Santos, Child01', $lines[20][3]);
        $this->assertStringNotContainsString('resident_id', $csv);
        $this->assertSame(app(OptCycleDataset::class)->rows($cycle, $filters)->pluck('child')->all(), array_column(array_slice($lines, 1), 3));
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.export', ['optCycle' => $cycle->id, 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.export', ['optCycle' => $cycle->id, 'format' => 'xlsx']))->assertOk();
    }

    public function test_measured_readiness_and_full_cycle_completion_are_independent(): void
    {
        $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement());
        $dataset = app(OptCycleDataset::class);
        $this->assertSame(1, $dataset->query($cycle, ['measurement_status' => 'measured', 'readiness' => 'incomplete'])->count());
        $this->assertSame(0, $dataset->query($cycle, ['readiness' => 'ready'])->count());
        $this->actingAs($this->bns)->post(route('bns.opt-cycles.complete', $cycle))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $cycle->fresh()->status);
    }

    public function test_flags_require_explicit_scoped_resolution_and_measurement_does_not_close_them(): void
    {
        $child = $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $attributes = ['resident_id' => $child->id, 'barangay_id' => $this->barangay->id, 'purok_id' => $this->household->purok_id,
            'flagged_by_user_id' => $this->bns->id, 'flag_status' => 'open', 'flag_reason' => 'Review needed', 'flagged_at' => now()];
        $flag = ChildNutritionAssessmentFlag::create($attributes);
        $otherFlag = ChildNutritionAssessmentFlag::create($attributes);
        $measurement = app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement());
        $this->assertSame('open', $flag->fresh()->flag_status);
        $otherBns = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $this->actingAs($otherBns)->post(route('bns.watchlist.resolve', $flag), ['resolution_note' => 'Reviewed'])->assertNotFound();
        $otherChild = $this->child(['first_name' => 'Other']);
        $otherEntry = $this->cycle('january')->entries()->where('resident_key', $otherChild->id)->firstOrFail();
        $unrelated = app(OptCycleWorkflow::class)->measure($this->bns, $otherEntry->cycle, $otherEntry, $this->measurement());
        $this->actingAs($this->bns)->post(route('bns.watchlist.resolve', $flag), ['resolution_note' => 'Reviewed', 'resolved_measurement_id' => $unrelated->id])->assertNotFound();
        $this->actingAs($this->bns)->post(route('bns.watchlist.resolve', $flag), ['resolution_note' => 'Assessed; follow-up discussed', 'resolved_measurement_id' => $measurement->id])->assertRedirect();
        $this->assertSame('closed', $flag->fresh()->flag_status);
        $this->assertSame($measurement->id, $flag->fresh()->resolved_measurement_id);
        $this->assertSame('open', $otherFlag->fresh()->flag_status);
    }

    public function test_feeding_does_not_need_opt_and_copying_reference_is_explicit_with_provenance(): void
    {
        $child = $this->child();
        $program = FeedingProgram::create(['barangay_id' => $this->barangay->id, 'created_by_user_id' => $this->bns->id,
            'name' => 'Independent feeding program', 'program_status' => 'active']);
        $this->actingAs($this->bns)->post(route('bns.feeding-programs.enrollments.store', $program),
            ['resident_id' => $child->id, 'enrolled_on' => '2026-09-27'])->assertRedirect()->assertSessionHasNoErrors();
        $enrollment = FeedingProgramEnrollment::firstOrFail();
        $this->assertNull($enrollment->baseline_weight_kg);
        $this->assertNull($enrollment->baseline_opt_measurement_id);
        $this->actingAs($this->bns)->get(route('bns.feeding-programs.show', $program))->assertOk()->assertDontSee('Suggested TCL Enrollments');
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $measurement = app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement());
        $program2 = $program->replicate();
        $program2->name = 'Explicit copy program';
        $program2->save();
        $this->actingAs($this->bns)->post(route('bns.feeding-programs.enrollments.store', $program2),
            ['resident_id' => $child->id, 'enrolled_on' => '2026-09-27', 'use_latest_opt_baseline' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $copied = $program2->enrollments()->firstOrFail();
        $this->assertSame($measurement->id, $copied->baseline_opt_measurement_id);
        $this->assertSame('2026-07-02', $copied->baseline_provenance['measurement_date']);
        app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement(['weight_kg' => 12]));
        $this->assertEquals(10.5, $copied->fresh()->baseline_weight_kg);
        $this->assertEquals(10.5, $copied->fresh()->baseline_provenance['weight_kg']);
        $this->assertNull($enrollment->fresh()->baseline_weight_kg);
    }

    public function test_legacy_records_and_ids_are_preserved_without_guessed_mappings(): void
    {
        $child = $this->child();
        $campaign = NutritionCampaignPeriod::create(['barangay_id' => $this->barangay->id, 'created_by_user_id' => $this->bns->id,
            'name' => 'OPT+ 2026 Q3', 'campaign_type' => 'opt_plus', 'is_active' => true]);
        $legacy = OptMeasurement::create($this->measurement() + ['resident_id' => $child->id, 'barangay_id' => $this->barangay->id,
            'campaign_period_id' => $campaign->id, 'measured_by_user_id' => $this->bns->id, 'age_in_months' => 30, 'sex_snapshot' => 'Female']);
        $this->cycle();
        $report = app(LegacyOptInventory::class)->report();
        $this->assertSame(1, $report['legacy_measurements']);
        $this->assertSame('ambiguous_keep_legacy', $report['campaigns'][0]['classification']);
        $this->assertFalse($report['conversion_performed']);
        $this->assertNull($legacy->fresh()->opt_cycle_entry_id);
        $this->actingAs($this->bns)->get(route('bns.opt-measurements.show', $legacy))->assertOk();
        $this->actingAs($this->bns)->get(route('bns.opt-measurements.create'))->assertRedirect(route('bns.opt-cycles.index'));
        $this->actingAs($this->bns)->post(route('bns.opt-measurements.store'), $this->measurement() + ['resident_id' => $child->id, 'campaign_period_id' => $campaign->id])->assertGone();
        $this->actingAs($this->bns)->post(route('bns.campaign-periods.store'), ['name' => 'Invented OPT', 'campaign_type' => 'opt_plus', 'is_active' => 0])->assertSessionHasErrors('campaign_type');
        $this->assertSame($legacy->id, $child->fresh()->latestOptMeasurement->id);
    }

    public function test_bhw_phn_and_mho_can_still_read_latest_cycle_measurement(): void
    {
        $child = $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $measurement = app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement());
        foreach (['bhw', 'phn', 'mho'] as $role) {
            $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => $this->barangay->id, 'assigned_purok_id' => $this->household->purok_id]);
            $this->actingAs($user)->get(route($role.'.residents.show', $child))->assertOk()
                ->assertViewHas('resident', fn ($resident) => $resident->latestOptMeasurement->id === $measurement->id);
        }
    }

    public function test_admin_and_bns_reporting_use_captured_latest_cycle_denominator(): void
    {
        $this->child();
        $january = $this->cycle('january');
        $this->child(['first_name' => 'Second']);
        $july = $this->cycle();
        app(OptCycleWorkflow::class)->measure($this->bns, $july, $july->entries()->firstOrFail(), $this->measurement());
        $summary = OptCycleReporting::latestSummary($this->barangay->id);
        $this->assertSame(2, $summary['eligible']);
        $this->assertSame(1, $summary['measured']);
        $this->assertSame(50.0, $summary['coverage']);
        $this->actingAs($this->bns)->get(route('bns.dashboard'))->assertOk()->assertSee('Latest OPT+ Cycle Coverage');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.oversight.nutrition', ['barangay_id' => $this->barangay->id]))->assertOk()->assertViewHas('cycleSummary', fn ($s) => $s['eligible'] === 2);
        $csv = $this->actingAs($admin)->get(route('admin.oversight.nutrition.export', ['dataset' => 'campaigns', 'format' => 'csv', 'barangay_id' => $this->barangay->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('July 2026 OPT+', $csv);
        $this->assertStringContainsString('January 2026 OPT+', $csv);
        $this->assertStringContainsString('Eligible Children', $csv);
    }

    public function test_service_validates_measurements_and_cycle_metadata_without_http(): void
    {
        $child = $this->child(['birth_date' => '2026-07-01']);
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        foreach ([['weight_kg' => 0], ['height_cm' => null], ['measurement_posture' => 'unknown'],
            ['measurement_date' => '2026-06-30'], ['measurement_date' => '2026-09-28']] as $invalid) {
            try {
                app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement($invalid));
                $this->fail('Invalid raw input must be rejected.');
            } catch (ValidationException) {
                $this->assertSame(0, OptMeasurement::count());
            }
        }
        try {
            app(OptCycleWorkflow::class)->create($this->bns, ['year' => 2026, 'round' => 'january', 'reference_date' => '2026-07-01']);
            $this->fail('Mismatched pilot reference date must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(1, OptCycle::count());
        }
    }

    public function test_caregiver_scope_and_failed_snapshot_correction_are_atomic(): void
    {
        $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $foreignPurok = Purok::factory()->create();
        $foreignHouse = Household::create(['purok_id' => $foreignPurok->id, 'household_no' => 'Other', 'household_address' => 'Other address', 'is_active' => true]);
        $foreignCaregiver = $this->child(['household_id' => $foreignHouse->id, 'birth_date' => '1990-01-01']);
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.caregiver', [$cycle, $entry]),
            ['caregiver_resident_id' => $foreignCaregiver->id, 'ip_membership' => 'no'])->assertNotFound();
        try {
            app(OptCycleWorkflow::class)->confirmProfile($this->bns, $cycle, $entry,
                ['caregiver_name' => 'Uncommitted', 'ip_membership' => 'no', 'update_cycle_snapshot' => true]);
            $this->fail('A snapshot correction requires a reason.');
        } catch (ValidationException) {
            $this->assertSame(0, ChildNutritionProfile::count());
            $this->assertNull($entry->fresh()->caregiver_name);
        }
    }

    public function test_measurement_history_survives_hard_deleted_child_and_recorder(): void
    {
        $child = $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $measurement = app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement());
        $child->forceDelete();
        $this->bns->forceDelete();
        $this->assertNull($measurement->fresh()->resident_id);
        $this->assertNull($measurement->fresh()->measured_by_user_id);
        $this->assertEquals(10.5, $entry->fresh()->measurement->weight_kg);
        $this->assertSame('Ana', $entry->fresh()->first_name);
    }

    public function test_ready_filter_multiword_search_and_each_sort_share_export_order(): void
    {
        $this->child();
        $this->child(['first_name' => 'Ana', 'middle_name' => 'B', 'birth_date' => '2023-01-01']);
        $cycle = $this->cycle();
        foreach ($cycle->entries as $index => $entry) {
            app(OptCycleWorkflow::class)->confirmProfile($this->bns, $cycle, $entry,
                ['caregiver_name' => 'Confirmed Guardian', 'ip_membership' => 'no', 'update_cycle_snapshot' => true, 'correction_reason' => 'Confirmed']);
            app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement(['measurement_date' => '2026-07-0'.($index + 2)]));
        }
        foreach (array_keys(OptCycleDataset::SORTS) as $sort) {
            foreach (['asc', 'desc'] as $direction) {
                $filters = ['search' => 'Ana Santos', 'readiness' => 'ready', 'measurement_status' => 'measured', 'sort' => $sort, 'direction' => $direction];
                $listing = $this->actingAs($this->bns)->get(route('bns.opt-cycles.show', ['optCycle' => $cycle->id] + $filters))->assertOk();
                $names = $listing->viewData('entries')->pluck('child_name')->all();
                $csv = $this->get(route('bns.opt-cycles.export', ['optCycle' => $cycle->id, 'format' => 'csv'] + $filters))->assertOk()->streamedContent();
                $lines = array_map('str_getcsv', explode("\n", trim($csv)));
                $this->assertCount(3, $lines);
                $this->assertSame($names, array_column(array_slice($lines, 1), 3));
            }
        }
    }

    public function test_demo_nutrition_seeding_uses_cycles_and_preserves_existing_baseline(): void
    {
        $children = collect(range(1, 7))->map(fn ($n) => $this->child(['first_name' => 'Demo'.$n]));
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $this->barangay->id]);
        $method = new \ReflectionMethod(MockOperationalDataSeeder::class, 'seedNutritionArtifacts');
        $arguments = [$this->barangay, $this->bns, $bhw, $children, null, $children->first(), 0];
        $method->invoke(new MockOperationalDataSeeder, ...$arguments);
        $this->assertSame(1, OptCycle::count());
        $this->assertSame(7, OptCycleEntry::count());
        $this->assertSame(5, OptMeasurement::whereNotNull('opt_cycle_entry_id')->count());
        $this->assertSame(0, NutritionCampaignPeriod::where('campaign_type', 'opt_plus')->count());
        $enrollment = FeedingProgramEnrollment::firstOrFail();
        $enrollment->update(['baseline_weight_kg' => 9.75]);
        $method->invoke(new MockOperationalDataSeeder, ...$arguments);
        $this->assertSame(5, OptMeasurement::count());
        $this->assertEquals(9.75, $enrollment->fresh()->baseline_weight_kg);
    }

    public function test_simple_measurement_form_records_missing_caregiver_in_one_save(): void
    {
        $child = $this->child();
        $cycle = $this->cycle('january');
        $entry = $cycle->entries()->firstOrFail();
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.entry', [$cycle, $entry]))->assertOk()
            ->assertSee('Mother / Caregiver')->assertSee('Save Measurement')
            ->assertDontSee('Confirmed IP Membership')->assertDontSee('Snapshot Correction Reason')
            ->assertDontSee('Also explicitly correct')->assertDontSee('Missing input confirmation/data');
        $this->put(route('bns.opt-cycles.measure', [$cycle, $entry]), $this->measurement() + ['caregiver_name' => 'Maria Santos'])
            ->assertRedirect(route('bns.opt-cycles.show', $cycle))->assertSessionHasNoErrors();
        $this->assertSame('Maria Santos', $child->fresh()->childNutritionProfile->caregiver_name);
        $this->assertSame('Maria Santos', $entry->fresh()->caregiver_name);
        $this->assertNull($entry->fresh()->ip_membership);
        $this->assertSame('Measured', $entry->fresh()->measurement_status);
        $this->assertSame('Maria Santos', $this->cycle()->entries()->firstOrFail()->caregiver_name);
        $this->assertTrue(AuditLog::where('model_type', OptCycleEntry::class)->exists());
        $this->get(route('bns.opt-cycles.show', $cycle))->assertOk()->assertDontSee('Missing input confirmation/data')
            ->assertDontSee('Not confirmed')->assertSee('incomplete export information');
    }

    public function test_normal_caregiver_change_preserves_both_historical_cycles_and_ip(): void
    {
        $child = $this->child();
        ChildNutritionProfile::create(['resident_id' => $child->id, 'caregiver_name' => 'Maria Santos', 'caregiver_relationship' => 'Mother',
            'caregiver_confirmed_at' => now(), 'ip_membership' => true, 'ip_confirmed_at' => now()]);
        $january = $this->cycle('january');
        $july = $this->cycle();
        $entry = $july->entries()->firstOrFail();
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.measure', [$july, $entry]), $this->measurement() + ['caregiver_name' => 'Elena Santos'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Maria Santos', $january->entries()->firstOrFail()->caregiver_name);
        $this->assertSame('Maria Santos', $entry->fresh()->caregiver_name);
        $this->assertSame('Elena Santos', $child->fresh()->childNutritionProfile->caregiver_name);
        $this->assertTrue($child->childNutritionProfile->ip_membership);
        $this->assertNull($child->childNutritionProfile->caregiver_relationship);
        $this->get(route('bns.opt-cycles.entry', [$july, $entry]))->assertOk()->assertSee('Elena Santos');
        $this->travelTo(now()->setDate(2027, 1, 2));
        $next = app(OptCycleWorkflow::class)->create($this->bns, ['year' => 2027, 'round' => 'january', 'reference_date' => '2027-01-01']);
        $this->assertSame('Elena Santos', $next->entries()->firstOrFail()->caregiver_name);
    }

    public function test_registered_caregiver_combined_save_and_failed_measurement_roll_back_together(): void
    {
        $child = $this->child();
        $mother = $this->child(['first_name' => 'Mother', 'birth_date' => '1990-01-01']);
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.measure', [$cycle, $entry]), $this->measurement() +
            ['caregiver_name' => 'Mother Santos', 'caregiver_resident_id' => $mother->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($mother->id, $child->fresh()->childNutritionProfile->caregiver_resident_id);
        $this->assertSame('Mother Santos', $entry->fresh()->caregiver_name);
        $mother->update(['first_name' => 'Renamed']);
        $this->put(route('bns.opt-cycles.measure', [$cycle, $entry]), $this->measurement() +
            ['caregiver_name' => 'Mother Santos', 'caregiver_resident_id' => $mother->id])->assertSessionHasNoErrors();
        $this->assertSame('Mother Santos', $child->fresh()->childNutritionProfile->caregiver_name);
        OptMeasurement::updating(fn () => throw new \RuntimeException('Save failure'));
        try {
            app(OptCycleWorkflow::class)->measure($this->bns, $cycle, $entry, $this->measurement() + ['caregiver_name' => 'Uncommitted']);
            $this->fail('Expected failure.');
        } catch (\RuntimeException) {
            $this->assertSame('Mother Santos', $child->fresh()->childNutritionProfile->caregiver_name);
            $this->assertSame('Mother Santos', $entry->fresh()->caregiver_name);
        } finally {
            OptMeasurement::flushEventListeners();
        }
    }

    public function test_simple_caregiver_save_rejects_foreign_self_and_completed_cycle(): void
    {
        $child = $this->child();
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $foreignPurok = Purok::factory()->create();
        $foreignHouse = Household::create(['purok_id' => $foreignPurok->id, 'household_no' => 'Foreign', 'household_address' => 'Other', 'is_active' => true]);
        $foreign = $this->child(['household_id' => $foreignHouse->id, 'birth_date' => '1990-01-01']);
        $url = route('bns.opt-cycles.measure', [$cycle, $entry]);
        $this->actingAs($this->bns)->put($url, $this->measurement() + ['caregiver_resident_id' => $foreign->id])->assertNotFound();
        $this->put($url, $this->measurement() + ['caregiver_resident_id' => $child->id])->assertSessionHasErrors('caregiver_name');
        app(OptCycleWorkflow::class)->complete($this->bns, $cycle, true, 'Unable to attend');
        $this->put($url, $this->measurement() + ['caregiver_name' => 'Blocked'])->assertSessionHasErrors('cycle');
        $this->assertSame(0, ChildNutritionProfile::count());
        $this->assertSame(0, OptMeasurement::count());
    }

    public function test_deliberate_historical_correction_does_not_update_current_caregiver(): void
    {
        $child = $this->child();
        ChildNutritionProfile::create(['resident_id' => $child->id, 'caregiver_name' => 'Current Guardian', 'caregiver_confirmed_at' => now()]);
        $cycle = $this->cycle();
        $entry = $cycle->entries()->firstOrFail();
        $url = route('bns.opt-cycles.historical-information.update', [$cycle, $entry]);
        $this->actingAs($this->bns)->get(route('bns.opt-cycles.historical-information', [$cycle, $entry]))->assertOk();
        $this->put($url, ['caregiver_name' => 'Historical Mother', 'ip_membership' => 'no'])->assertSessionHasErrors('correction_reason');
        $this->put($url, ['caregiver_name' => 'Historical Mother', 'ip_membership' => 'no', 'correction_reason' => 'Corrected from the original record'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Historical Mother', $entry->fresh()->caregiver_name);
        $this->assertSame('Current Guardian', $child->fresh()->childNutritionProfile->caregiver_name);
        $this->assertTrue(AuditLog::where('model_type', OptCycleEntry::class)->exists());
        app(OptCycleWorkflow::class)->complete($this->bns, $cycle, true, 'Round finished');
        $this->put($url, ['caregiver_name' => 'Blocked', 'ip_membership' => 'no', 'correction_reason' => 'Try changing'])->assertSessionHasErrors('cycle');
        $otherBns = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $this->actingAs($otherBns)->get(route('bns.opt-cycles.historical-information', [$cycle, $entry]))->assertNotFound();
    }

    public function test_cycle_only_snapshots_explicitly_confirmed_ip_information(): void
    {
        $unknown = $this->child();
        $unknown->socioEconomicProfile()->create([]); // The schema defaults is_ip to false, not to confirmed No.
        ChildNutritionProfile::create(['resident_id' => $unknown->id, 'caregiver_name' => 'Maria Santos',
            'caregiver_confirmed_at' => now(), 'ip_membership' => false]);
        $confirmedAt = now()->subDay();
        foreach (['ConfirmedYes' => true, 'ConfirmedNo' => false] as $name => $membership) {
            $child = $this->child(['first_name' => $name]);
            $child->socioEconomicProfile()->create([]);
            ChildNutritionProfile::create(['resident_id' => $child->id, 'caregiver_name' => 'Maria Santos',
                'caregiver_confirmed_at' => now(), 'ip_membership' => $membership, 'ip_confirmed_at' => $confirmedAt]);
        }
        $cycle = $this->cycle();
        $entries = $cycle->entries()->get()->keyBy('first_name');
        $this->assertFalse($unknown->socioEconomicProfile->is_ip);
        $this->assertNull($entries['Ana']->ip_membership);
        $this->assertNull($entries['Ana']->ip_confirmed_at);
        $this->assertTrue($entries['ConfirmedYes']->ip_membership);
        $this->assertFalse($entries['ConfirmedNo']->ip_membership);
        $this->assertEquals($confirmedAt, $entries['ConfirmedYes']->ip_confirmed_at);
        $this->assertEquals($confirmedAt, $entries['ConfirmedNo']->ip_confirmed_at);
        $this->assertSame(1, app(OptCycleDataset::class)->query($cycle, ['readiness' => 'incomplete'])->count());
        $this->assertSame(2, app(OptCycleDataset::class)->query($cycle, ['readiness' => 'ready'])->count());
        $this->actingAs($this->bns)->put(route('bns.opt-cycles.measure', [$cycle, $entries['Ana']]), $this->measurement())
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Measured', $entries['Ana']->fresh()->measurement_status);
        $this->assertContains('IP membership not confirmed', $entries['Ana']->fresh()->readiness_issues);
        $this->get(route('bns.opt-cycles.entry', [$cycle, $entries['Ana']]))->assertOk()->assertDontSee('Confirmed IP Membership');
    }

    public function test_feeding_program_still_works_without_a_campaign_period_or_opt_cycle(): void
    {
        $child = $this->child();
        $this->actingAs($this->bns)->post(route('bns.feeding-programs.store'), [
            'name' => 'Standalone feeding program', 'program_status' => FeedingProgram::STATUS_ACTIVE,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $program = FeedingProgram::firstOrFail();
        $this->assertNull($program->campaign_period_id);
        $this->assertSame(0, NutritionCampaignPeriod::count());
        $this->assertSame(0, OptCycle::count());
        $this->post(route('bns.feeding-programs.enrollments.store', $program), [
            'resident_id' => $child->id, 'enrolled_on' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, FeedingProgramEnrollment::count());
    }
}
