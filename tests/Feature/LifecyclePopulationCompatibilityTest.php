<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BarangayCertificate;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramAttendance;
use App\Models\FeedingProgramEnrollment;
use App\Models\FeedingProgramProgressLog;
use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\InfantFeedingLog;
use App\Models\MaternalNutritionHistory;
use App\Models\MaternalNutritionProfile;
use App\Models\MicronutrientSupplementationLog;
use App\Models\OptMeasurement;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\CurrentRbiEligibility;
use App\Support\HouseholdHeadManager;
use App\Support\MobileBootstrapPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LifecyclePopulationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Household $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
        $this->home = $this->household(Purok::factory()->create(), '001');
    }

    public static function historicalStates(): array
    {
        return [[Resident::STATUS_DECEASED], [Resident::STATUS_MOVED_OUT], [Resident::STATUS_RELOCATED]];
    }

    public function test_pending_household_correction_keeps_current_membership_and_mobile_identity_without_applying_changes(): void
    {
        $resident = $this->resident('PendingCorrection', ['is_active' => false]);
        $target = $this->household($this->home->purok, '002');
        $bhw = $this->user('bhw');
        $secretary = $this->user('secretary');
        $correction = ProfileUpdateRequest::create([
            'barangay_id' => $this->home->purok->barangay_id, 'submitted_by_user_id' => $bhw->id,
            'subject_type' => ProfileUpdateRequest::SUBJECT_RESIDENT, 'subject_id' => $resident->id,
            'mobile_submission_key' => '00000000-0000-4000-8000-000000000301',
            'current_snapshot' => ['household_id' => $this->home->id],
            'proposed_changes' => ['household_id' => $target->id],
            'request_reason' => 'Check household attachment before applying correction',
            'request_status' => ProfileUpdateRequest::STATUS_PENDING,
        ]);
        $before = $this->snapshot();
        $payload = app(MobileBootstrapPayload::class)->build($bhw);
        $this->assertSame([$resident->id], Resident::currentPopulation()->pluck('id')->all());
        $this->assertSame(1, $this->home->currentMemberCount());
        $this->assertTrue($target->isVacant());
        $this->actingAs($secretary)->get(route('secretary.update-requests.show', $correction))->assertOk();
        $this->get(route('secretary.residents.index'))->assertOk()->assertSee('PendingCorrection');
        $mobileResident = collect($payload['residents'])->sole();
        $this->assertSame($resident->id, $mobileResident['id']);
        $this->assertSame($this->home->id, $mobileResident['household_id']);
        $this->assertFalse($mobileResident['is_active']);
        $this->assertSame('submitted', $mobileResident['verification_status']);
        $this->assertArrayNotHasKey('resident_status', $mobileResident);
        $this->assertSame($payload, app(MobileBootstrapPayload::class)->build($bhw));
        $this->assertDatabaseCount('resident_drafts', 0);
        $this->assertDatabaseCount('household_drafts', 0);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_locked_head_review_preserves_all_attachments_but_only_selects_current_members(): void
    {
        $current = $this->resident('Current');
        $unavailable = $this->resident('Unavailable', ['is_active' => false]);
        foreach (self::historicalStates() as [$status]) {
            $this->resident($status, ['resident_status' => $status]);
        }
        $deleted = $this->resident('Deleted');
        $deleted->delete();
        $this->home->update(['head_resident_id' => $deleted->id]);
        $this->actingAs($this->user('secretary'));
        $before = $this->snapshot();
        DB::enableQueryLog();
        try {
            app(HouseholdHeadManager::class)->locked([$this->home->id], function ($households) use ($current, $unavailable): void {
                $home = $households[$this->home->id];
                $this->assertGreaterThan(0, DB::transactionLevel());
                $this->assertCount(6, $home->residents);
                $this->assertSame([$current->id, $unavailable->id], $home->currentMembers->modelKeys());
                $this->assertSame(2, $home->currentMemberCount());
                $this->assertFalse($home->isVacant());
                $this->assertNull($home->currentHeadResident());
            });
            $locks = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains(strtolower($sql), 'for update'));
            $this->assertTrue($locks->contains(fn ($sql) => str_contains($sql, '`households`')));
            $this->assertTrue($locks->contains(fn ($sql) => str_contains($sql, '`residents`')));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_mobile_keeps_broad_membership_and_availability_for_every_lifecycle_state(): void
    {
        $bhw = $this->user('bhw');
        $secretary = $this->user('secretary');
        $residents = [];
        foreach ([Resident::STATUS_ACTIVE, Resident::STATUS_DECEASED, Resident::STATUS_MOVED_OUT, Resident::STATUS_RELOCATED] as $status) {
            foreach ([true, false] as $available) {
                $attributes = ['resident_status' => $status, 'is_active' => $available];
                $resident = $this->resident($status.($available ? 'Available' : 'Unavailable'), $attributes);
                $residents[$resident->id] = $resident;
                $this->resident('Deleted'.$resident->first_name, $attributes)->delete();
            }
        }
        $foreign = $this->resident('ForeignMobile', [
            'household_id' => $this->household(Purok::factory()->create(), '001')->id,
        ]);
        $before = $this->snapshot();
        $payload = app(MobileBootstrapPayload::class)->build($bhw);
        $this->assertCount(8, $payload['residents']);
        $this->assertSame(8, $payload['households'][0]['resident_count']);
        $this->assertSame(2, $this->home->currentMemberCount());
        foreach ($payload['residents'] as $row) {
            $this->assertArrayHasKey($row['id'], $residents);
            $this->assertSame($residents[$row['id']]->is_active, $row['is_active']);
            $this->assertSame($this->home->id, $row['household_id']);
            $this->assertSame('approved', $row['verification_status']);
            $this->assertArrayNotHasKey('resident_status', $row);
            $this->assertNotSame($foreign->id, $row['id']);
        }
        foreach (['secretary' => $secretary, 'bhw' => $bhw] as $role => $user) {
            $this->actingAs($user)->get(route($role.'.residents.index'))->assertOk()
                ->assertViewHas('residents', fn ($rows) => $rows->total() === 2);
            $this->get(route($role.'.households.show', $this->home))->assertOk();
        }
        $this->assertSame($payload, app(MobileBootstrapPayload::class)->build($bhw));
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('historicalStates')]
    public function test_nutrition_history_and_exports_remain_visible_without_mutation(string $status): void
    {
        $bns = $this->user('bns');
        $mother = $this->resident('HistoricalMother', ['birth_date' => '1995-01-01']);
        $child = $this->resident('HistoricalChild');
        MaternalNutritionProfile::create(['resident_id' => $mother->id, 'barangay_id' => $this->home->purok->barangay_id,
            'updated_by_user_id' => $bns->id, 'is_currently_lactating' => true, 'last_status_updated_at' => now()]);
        MaternalNutritionHistory::create(['resident_id' => $mother->id, 'recorded_by_user_id' => $bns->id,
            'event_type' => MaternalNutritionHistory::EVENT_PRENATAL_WEIGHT_CHECK, 'event_date' => '2026-07-01',
            'weight_kg' => 55, 'notes' => 'PrenatalHistorySentinel']);
        InfantFeedingLog::create(['resident_id' => $child->id, 'mother_resident_id' => $mother->id,
            'recorded_by_user_id' => $bns->id, 'observed_on' => '2026-07-01',
            'feeding_method' => InfantFeedingLog::METHOD_MIXED_FEEDING, 'notes' => 'InfantHistorySentinel']);
        MicronutrientSupplementationLog::create(['resident_id' => $child->id, 'barangay_id' => $this->home->purok->barangay_id,
            'distributed_by_user_id' => $bns->id, 'administered_on' => '2026-07-01',
            'supplement_type' => MicronutrientSupplementationLog::TYPE_VITAMIN_A,
            'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_TODDLER, 'remarks' => 'SupplementHistorySentinel']);
        $measurement = OptMeasurement::create(['resident_id' => $child->id, 'barangay_id' => $this->home->purok->barangay_id,
            'measured_by_user_id' => $bns->id, 'measurement_date' => '2026-07-01', 'age_in_months' => 18,
            'sex_snapshot' => 'Female', 'weight_kg' => 10, 'height_cm' => 80, 'measurement_posture' => 'standing',
            'weight_for_age_status' => 'Underweight']);
        $flag = ChildNutritionAssessmentFlag::create(['resident_id' => $child->id, 'barangay_id' => $this->home->purok->barangay_id,
            'purok_id' => $this->home->purok_id, 'flagged_by_user_id' => $this->user('bhw')->id,
            'flag_status' => ChildNutritionAssessmentFlag::STATUS_OPEN, 'flag_reason' => 'FlagHistorySentinel', 'flagged_at' => now()]);
        $program = $this->program($bns);
        $enrollment = FeedingProgramEnrollment::create(['feeding_program_id' => $program->id, 'resident_id' => $child->id,
            'enrolled_by_user_id' => $bns->id, 'enrolled_on' => '2026-07-01', 'is_active' => true]);
        FeedingProgramAttendance::create(['enrollment_id' => $enrollment->id, 'attendance_date' => '2026-07-02',
            'attendance_status' => FeedingProgramAttendance::STATUS_PRESENT, 'notes' => 'AttendanceHistorySentinel']);
        FeedingProgramProgressLog::create(['enrollment_id' => $enrollment->id, 'logged_by_user_id' => $bns->id,
            'logged_on' => '2026-07-03', 'week_number' => 1, 'weight_kg' => 10.2, 'remarks' => 'ProgressHistorySentinel']);
        $mother->update(['resident_status' => $status]);
        $child->update(['resident_status' => $status]);
        AuditLog::logMutation('created', $bns, $measurement);
        $before = $this->snapshot();
        $this->actingAs($bns)->get(route('bns.maternal.show', $mother))->assertOk()
            ->assertSee('PrenatalHistorySentinel')->assertSee('InfantHistorySentinel')->assertSee($child->first_name);
        $this->get(route('bns.micronutrients.index'))->assertOk()->assertSee('SupplementHistorySentinel');
        $this->get(route('bns.watchlist.index'))->assertOk()->assertSee('FlagHistorySentinel')
            ->assertViewHas('watchlist', fn ($rows) => $rows->contains('id', $measurement->id));
        $this->get(route('bns.feeding-programs.show', $program))->assertOk()
            ->assertSee('AttendanceHistorySentinel')->assertSee('ProgressHistorySentinel')
            ->assertViewHas('enrollments', fn ($rows) => $rows->contains('id', $enrollment->id));
        foreach (['bns.micronutrients.export' => $child->first_name, 'bns.watchlist.export' => $child->first_name,
            'bns.opt-measurements.export' => $child->first_name, 'bns.maternal.export' => $mother->first_name,
            'bns.feeding-programs.export' => $program->name] as $route => $name) {
            $this->assertStringContainsString($name, $this->get(route($route, ['format' => 'csv']))->assertOk()->streamedContent());
        }
        $foreignBns = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => Purok::factory()->create()->barangay_id]);
        $this->actingAs($foreignBns)->get(route('bns.maternal.show', $mother))->assertNotFound();
        $this->get(route('bns.feeding-programs.show', $program))->assertNotFound();
        $this->get(route('bns.opt-measurements.show', $measurement))->assertNotFound();
        $this->get(route('bns.micronutrients.index'))->assertOk()->assertDontSee($child->first_name);
        $this->get(route('bns.watchlist.index'))->assertOk()->assertDontSee('FlagHistorySentinel');
        $this->assertSame(ChildNutritionAssessmentFlag::STATUS_OPEN, $flag->fresh()->flag_status);
        $this->assertHistoryUnchanged($before, [MicronutrientSupplementationLog::class, OptMeasurement::class,
            OptMeasurement::class, MaternalNutritionProfile::class, FeedingProgram::class]);
    }

    #[DataProvider('historicalStates')]
    public function test_vacant_household_visit_photos_and_issued_certificate_exports_remain_authorized(string $status): void
    {
        Storage::fake('local');
        $resident = $this->resident('IssuedRecipient', ['is_active' => false]);
        $bhw = $this->user('bhw');
        $secretary = $this->user('secretary');
        $admin = $this->user('admin');
        $path = 'visit-photos/2026/07/00000000-0000-4000-8000-000000000401.jpg';
        Storage::disk('local')->put($path, 'private-history-photo');
        $visit = FieldVisit::create(['mobile_uuid' => '00000000-0000-4000-8000-000000000402',
            'household_id' => $this->home->id, 'recorded_by_user_id' => $bhw->id,
            'visited_at' => '2026-07-01 09:00:00', 'notes' => 'VacantHistoryVisit',
            'photos' => [['path' => $path, 'mime_type' => 'image/jpeg']], 'source' => 'mobile']);
        $certificate = BarangayCertificate::create(['barangay_id' => $this->home->purok->barangay_id,
            'certificate_type' => 'barangay_clearance', 'recipient_type' => 'resident', 'resident_id' => $resident->id,
            'certificate_no' => 'COMPAT-001', 'issued_to_name' => $resident->formal_name, 'purpose' => 'Historical issuance',
            'issued_at' => '2026-07-01 12:00:00', 'issued_by_user_id' => $secretary->id,
            'signatory_name_at_issuance' => $secretary->display_name]);
        $this->home->update(['head_resident_id' => $resident->id]);
        $resident->update(['resident_status' => $status]);
        AuditLog::logMutation('created', $secretary, $certificate);
        $before = $this->snapshot();
        $this->assertTrue($this->home->isVacant());
        $this->assertNull($this->home->fresh()->currentHeadResident());
        foreach (['secretary' => $secretary, 'bhw' => $bhw, 'admin' => $admin] as $role => $user) {
            $this->actingAs($user)->get(route($role.'.households.show', $this->home))->assertOk()->assertSee('VacantHistoryVisit');
            $this->get(route($role.'.visits.show', $visit))->assertOk()->assertDontSee($path);
            $this->get(route($role.'.visits.photo', [$visit, 0]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        }
        $this->actingAs($secretary)->get(route('secretary.certificates.index'))->assertOk()->assertSee($certificate->certificate_no);
        $this->get(route('secretary.certificates.pdf', $certificate))->assertOk();
        $this->assertStringContainsString($resident->first_name,
            $this->get(route('secretary.certificates.export', ['format' => 'csv']))->assertOk()->streamedContent());
        $foreign = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => Purok::factory()->create()->barangay_id]);
        $this->actingAs($foreign)->get(route('secretary.visits.photo', [$visit, 0]))->assertForbidden();
        $this->get(route('secretary.certificates.show', $certificate))->assertForbidden();
        $this->assertSame($resident->id, $this->home->fresh()->head_resident_id);
        $this->assertHistoryUnchanged($before, [BarangayCertificate::class]);
    }

    public function test_current_but_unavailable_does_not_bypass_age_sex_scope_or_duplicate_enrollment(): void
    {
        $bns = $this->user('bns');
        $child = $this->resident('EligibleUnavailable', ['is_active' => false]);
        $adult = $this->resident('AdultUnavailable', ['is_active' => false, 'birth_date' => '1990-01-01', 'sex' => 'Male']);
        $foreign = $this->resident('ForeignUnavailable', ['is_active' => false,
            'household_id' => $this->household(Purok::factory()->create(), '001')->id]);
        $program = $this->program($bns);
        $url = route('bns.feeding-programs.enrollments.store', $program);
        $this->actingAs($bns)->post($url, ['resident_id' => $child->id, 'enrolled_on' => '2026-07-14'])->assertSessionHasNoErrors();
        foreach ([$child, $adult, $foreign] as $invalid) {
            $this->post($url, ['resident_id' => $invalid->id, 'enrolled_on' => '2026-07-14'])->assertSessionHasErrors('resident_id');
        }
        $this->assertDatabaseCount('feeding_program_enrollments', 1);
        $this->post(route('bns.maternal.profile.store'), ['resident_id' => $adult->id, 'is_currently_pregnant' => true])
            ->assertSessionHasErrors('resident_id');
        $this->post(route('bns.micronutrients.store'), ['resident_id' => $adult->id, 'administered_on' => '2026-07-14',
            'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_TODDLER,
            'supplement_type' => MicronutrientSupplementationLog::TYPE_VITAMIN_A])->assertSessionHasErrors('resident_id');
        $this->assertDatabaseCount('maternal_nutrition_profiles', 0);
        $this->assertDatabaseCount('micronutrient_supplementation_logs', 0);
    }

    public function test_parent_availability_stays_independent_of_population_and_rbi_eligibility(): void
    {
        $resident = $this->resident('UnavailableParent', ['is_active' => false]);
        $this->home->update(['is_active' => false]);
        $this->actingAs($this->user('secretary'));
        $before = $this->snapshot();
        $this->assertTrue($resident->isCurrentPopulation());
        $this->assertSame(1, $this->home->currentMemberCount());
        $this->assertFalse($this->home->isVacant());
        CurrentRbiEligibility::ensureResident($resident);
        CurrentRbiEligibility::ensureHousehold($this->home);
        $form = $this->get(route('secretary.certificates.create'))->assertOk();
        $this->post(route('secretary.certificates.store'), [
            'certificate_type' => BarangayCertificate::TYPE_CLEARANCE,
            'recipient_type' => BarangayCertificate::RECIPIENT_HOUSEHOLD,
            'household_id' => $this->home->id, 'purpose' => 'Parent availability check',
            'issued_at' => '2026-07-15T08:00', 'review_token' => $form->viewData('reviewToken'),
        ])->assertSessionHasErrors('household_id');
        $this->assertDatabaseCount('barangay_certificates', 0);
        $this->assertSame($before, $this->snapshot());
    }

    private function household(Purok $purok, string $number): Household
    {
        return Household::create(['purok_id' => $purok->id, 'household_no' => $number,
            'household_address' => 'Synthetic compatibility fixture', 'is_active' => true]);
    }

    private function resident(string $name, array $overrides = []): Resident
    {
        return Resident::create(array_merge(['household_id' => $this->home->id, 'first_name' => $name,
            'last_name' => 'Compatibility', 'birth_date' => '2025-01-01', 'birth_place' => 'Tubigon',
            'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Child', 'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true], $overrides));
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'assigned_barangay_id' => $this->home->purok->barangay_id,
            'assigned_purok_id' => $role === 'bhw' ? $this->home->purok_id : null]);
    }

    private function program(User $bns): FeedingProgram
    {
        return FeedingProgram::create(['barangay_id' => $this->home->purok->barangay_id,
            'created_by_user_id' => $bns->id, 'name' => 'CompatibilityFeedingProgram',
            'starts_on' => '2026-07-01', 'program_status' => FeedingProgram::STATUS_ACTIVE]);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['residents', 'households', 'resident_lifecycle_events', 'profile_update_requests',
            'resident_drafts', 'household_drafts', 'maternal_nutrition_profiles', 'maternal_nutrition_histories',
            'infant_feeding_logs', 'micronutrient_supplementation_logs', 'opt_measurements',
            'child_nutrition_assessment_flags', 'feeding_program_enrollments', 'feeding_program_attendances',
            'feeding_program_progress_logs', 'field_visits', 'barangay_certificates', 'audit_logs'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    private function assertHistoryUnchanged(array $before, array $exportModels): void
    {
        $after = $this->snapshot();
        $oldAudits = json_decode($before['audit_logs'], true);
        $audits = json_decode($after['audit_logs'], true);
        $this->assertSame($oldAudits, array_slice($audits, 0, count($oldAudits)));
        // Existing exports append audit events; they must not rewrite history or registry records.
        $exports = array_slice($audits, count($oldAudits));
        $this->assertEqualsCanonicalizing($exportModels, array_column($exports, 'model_type'));
        foreach ($exports as $export) {
            $this->assertSame('exported', $export['event_type']);
            $this->assertNull($export['model_id']);
            $this->assertSame(1, json_decode($export['metadata'], true)['record_count']);
        }
        unset($before['audit_logs'], $after['audit_logs']);
        $this->assertSame($before, $after);
    }
}
