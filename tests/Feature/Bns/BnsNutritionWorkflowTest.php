<?php

namespace Tests\Feature\Bns;

use App\Models\Barangay;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramAttendance;
use App\Models\FeedingProgramEnrollment;
use App\Models\FeedingProgramProgressLog;
use App\Models\Household;
use App\Models\InfantFeedingLog;
use App\Models\MaternalNutritionHistory;
use App\Models\MaternalNutritionProfile;
use App\Models\MicronutrientSupplementationLog;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BnsNutritionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bns_can_create_campaign_period_and_only_one_active_period_per_type_is_allowed(): void
    {
        [$bns, $barangay] = $this->bnsContext();

        $response = $this->actingAs($bns)->post(route('bns.campaign-periods.store'), [
            'name' => 'OPT+ 2026 Q3',
            'campaign_type' => NutritionCampaignPeriod::TYPE_OPT_PLUS,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-09-30',
            'is_active' => '1',
            'notes' => 'Quarterly growth assessment.',
        ]);

        $response->assertRedirect(route('bns.campaign-periods.index'));
        $this->assertDatabaseHas('nutrition_campaign_periods', [
            'barangay_id' => $barangay->id,
            'name' => 'OPT+ 2026 Q3',
            'campaign_type' => NutritionCampaignPeriod::TYPE_OPT_PLUS,
            'is_active' => true,
        ]);

        $duplicateResponse = $this->actingAs($bns)->post(route('bns.campaign-periods.store'), [
            'name' => 'OPT+ 2026 Q4',
            'campaign_type' => NutritionCampaignPeriod::TYPE_OPT_PLUS,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'is_active' => '1',
        ]);

        $duplicateResponse->assertSessionHasErrors('is_active');
    }

    public function test_bns_can_log_opt_measurement_with_who_statuses_and_close_open_flag(): void
    {
        [$bns, $barangay, $purok] = $this->bnsContext();
        $campaignPeriod = NutritionCampaignPeriod::query()->create([
            'barangay_id' => $barangay->id,
            'created_by_user_id' => $bns->id,
            'name' => 'OPT+ 2026 Q3',
            'campaign_type' => NutritionCampaignPeriod::TYPE_OPT_PLUS,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->endOfMonth()->toDateString(),
            'is_active' => true,
        ]);
        $household = $this->createHousehold($purok, '101');
        $resident = $this->createResident($household, [
            'first_name' => 'Luna',
            'last_name' => 'Garcia',
            'sex' => 'Female',
            'birth_date' => now()->subMonths(24)->toDateString(),
        ]);

        $flag = ChildNutritionAssessmentFlag::query()->create([
            'resident_id' => $resident->id,
            'barangay_id' => $barangay->id,
            'purok_id' => $purok->id,
            'flagged_by_user_id' => $bns->id,
            'flag_status' => ChildNutritionAssessmentFlag::STATUS_OPEN,
            'flag_reason' => 'Child appears underweight during field observation.',
            'flagged_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($bns)->post(route('bns.opt-measurements.store'), [
            'resident_id' => $resident->id,
            'campaign_period_id' => $campaignPeriod->id,
            'measurement_date' => now()->toDateString(),
            'weight_kg' => '7.00',
            'height_cm' => '78.00',
            'measurement_posture' => OptMeasurement::POSTURE_STANDING,
            'remarks' => 'BNS follow-up after BHW handoff.',
        ]);

        $measurement = OptMeasurement::query()->firstOrFail();

        $response->assertRedirect(route('bns.opt-measurements.show', $measurement));

        $measurement->refresh();
        $flag->refresh();

        $this->assertSame(24, $measurement->age_in_months);
        $this->assertSame('Female', $measurement->sex_snapshot);
        $this->assertSame('Severely Underweight', $measurement->weight_for_age_status);
        $this->assertSame('Stunted', $measurement->height_for_age_status);
        $this->assertSame('Severely Wasted', $measurement->weight_for_length_height_status);
        $this->assertTrue($measurement->is_target_client);
        $this->assertSame(ChildNutritionAssessmentFlag::STATUS_CLOSED, $flag->flag_status);
        $this->assertSame($measurement->id, $flag->resolved_measurement_id);
        $this->assertSame($bns->id, $flag->closed_by_user_id);
    }

    public function test_bns_blocks_duplicate_opt_measurement_for_same_child_date_and_campaign(): void
    {
        [$bns, $barangay, $purok] = $this->bnsContext();
        $campaignPeriod = NutritionCampaignPeriod::query()->create([
            'barangay_id' => $barangay->id,
            'created_by_user_id' => $bns->id,
            'name' => 'OPT+ 2026 Q3',
            'campaign_type' => NutritionCampaignPeriod::TYPE_OPT_PLUS,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->endOfMonth()->toDateString(),
            'is_active' => true,
        ]);
        $resident = $this->createResident($this->createHousehold($purok, '102'), [
            'sex' => 'Male',
            'birth_date' => now()->subMonths(18)->toDateString(),
        ]);

        OptMeasurement::query()->create([
            'resident_id' => $resident->id,
            'barangay_id' => $barangay->id,
            'campaign_period_id' => $campaignPeriod->id,
            'measured_by_user_id' => $bns->id,
            'measurement_date' => now()->toDateString(),
            'age_in_months' => 18,
            'sex_snapshot' => 'Male',
            'weight_kg' => 8.80,
            'height_cm' => 76.00,
            'measurement_posture' => OptMeasurement::POSTURE_RECUMBENT,
            'weight_for_age_z_score' => -1.1,
            'weight_for_age_status' => 'Normal',
            'height_for_age_z_score' => -0.8,
            'height_for_age_status' => 'Normal',
            'weight_for_length_height_z_score' => -0.5,
            'weight_for_length_height_status' => 'Normal',
        ]);

        $response = $this->actingAs($bns)->post(route('bns.opt-measurements.store'), [
            'resident_id' => $resident->id,
            'campaign_period_id' => $campaignPeriod->id,
            'measurement_date' => now()->toDateString(),
            'weight_kg' => '9.10',
            'height_cm' => '76.40',
            'measurement_posture' => OptMeasurement::POSTURE_RECUMBENT,
        ]);

        $response->assertSessionHasErrors('measurement_date');
        $this->assertSame(1, OptMeasurement::query()->count());
    }

    public function test_watchlist_uses_each_child_latest_measurement_only(): void
    {
        [$bns, $barangay, $purok] = $this->bnsContext();
        $campaignPeriod = NutritionCampaignPeriod::query()->create([
            'barangay_id' => $barangay->id,
            'created_by_user_id' => $bns->id,
            'name' => 'OPT+ 2026 Q3',
            'campaign_type' => NutritionCampaignPeriod::TYPE_OPT_PLUS,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->endOfMonth()->toDateString(),
            'is_active' => true,
        ]);

        $householdA = $this->createHousehold($purok, '103');
        $residentA = $this->createResident($householdA, [
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'sex' => 'Female',
            'birth_date' => now()->subMonths(24)->toDateString(),
        ]);
        $householdB = $this->createHousehold($purok, '104');
        $residentB = $this->createResident($householdB, [
            'first_name' => 'Paolo',
            'last_name' => 'Reyes',
            'sex' => 'Male',
            'birth_date' => now()->subMonths(24)->toDateString(),
        ]);

        OptMeasurement::query()->create([
            'resident_id' => $residentA->id,
            'barangay_id' => $barangay->id,
            'campaign_period_id' => $campaignPeriod->id,
            'measured_by_user_id' => $bns->id,
            'measurement_date' => now()->subDays(2)->toDateString(),
            'age_in_months' => 24,
            'sex_snapshot' => 'Female',
            'weight_kg' => 7.0,
            'height_cm' => 78.0,
            'measurement_posture' => OptMeasurement::POSTURE_STANDING,
            'weight_for_age_z_score' => -4.296,
            'weight_for_age_status' => 'Severely Underweight',
            'height_for_age_z_score' => -2.391,
            'height_for_age_status' => 'Stunted',
            'weight_for_length_height_z_score' => -4.054,
            'weight_for_length_height_status' => 'Severely Wasted',
        ]);

        OptMeasurement::query()->create([
            'resident_id' => $residentB->id,
            'barangay_id' => $barangay->id,
            'campaign_period_id' => $campaignPeriod->id,
            'measured_by_user_id' => $bns->id,
            'measurement_date' => now()->subDays(3)->toDateString(),
            'age_in_months' => 24,
            'sex_snapshot' => 'Male',
            'weight_kg' => 7.0,
            'height_cm' => 78.0,
            'measurement_posture' => OptMeasurement::POSTURE_STANDING,
            'weight_for_age_z_score' => -4.100,
            'weight_for_age_status' => 'Severely Underweight',
            'height_for_age_z_score' => -2.100,
            'height_for_age_status' => 'Stunted',
            'weight_for_length_height_z_score' => -4.000,
            'weight_for_length_height_status' => 'Severely Wasted',
        ]);

        OptMeasurement::query()->create([
            'resident_id' => $residentB->id,
            'barangay_id' => $barangay->id,
            'campaign_period_id' => $campaignPeriod->id,
            'measured_by_user_id' => $bns->id,
            'measurement_date' => now()->subDay()->toDateString(),
            'age_in_months' => 24,
            'sex_snapshot' => 'Male',
            'weight_kg' => 11.0,
            'height_cm' => 84.0,
            'measurement_posture' => OptMeasurement::POSTURE_STANDING,
            'weight_for_age_z_score' => -0.345,
            'weight_for_age_status' => 'Normal',
            'height_for_age_z_score' => -0.532,
            'height_for_age_status' => 'Normal',
            'weight_for_length_height_z_score' => -0.149,
            'weight_for_length_height_status' => 'Normal',
        ]);

        $response = $this->actingAs($bns)->get(route('bns.watchlist.index'));

        $response->assertOk();
        $response->assertSee('Lopez, Maria');
        $response->assertDontSee('Reyes, Paolo');
    }

    public function test_bns_can_manage_feeding_program_enrollment_attendance_and_progress(): void
    {
        [$bns, $barangay, $purok] = $this->bnsContext();
        $child = $this->createResident($this->createHousehold($purok, '105'), [
            'first_name' => 'Nico',
            'last_name' => 'Santos',
            'sex' => 'Male',
            'birth_date' => now()->subMonths(36)->toDateString(),
        ]);
        $feedingCampaign = NutritionCampaignPeriod::query()->create([
            'barangay_id' => $barangay->id,
            'created_by_user_id' => $bns->id,
            'name' => 'Feeding Cycle 2026 Q3',
            'campaign_type' => NutritionCampaignPeriod::TYPE_FEEDING_PROGRAM,
            'starts_on' => now()->subDays(14)->toDateString(),
            'ends_on' => now()->addDays(30)->toDateString(),
            'is_active' => true,
        ]);

        OptMeasurement::query()->create([
            'resident_id' => $child->id,
            'barangay_id' => $barangay->id,
            'campaign_period_id' => null,
            'measured_by_user_id' => $bns->id,
            'measurement_date' => now()->subDays(2)->toDateString(),
            'age_in_months' => 36,
            'sex_snapshot' => 'Male',
            'weight_kg' => 10.40,
            'height_cm' => 90.20,
            'measurement_posture' => OptMeasurement::POSTURE_STANDING,
            'weight_for_age_z_score' => -0.800,
            'weight_for_age_status' => 'Normal',
            'height_for_age_z_score' => -0.400,
            'height_for_age_status' => 'Normal',
            'weight_for_length_height_z_score' => -2.400,
            'weight_for_length_height_status' => 'Wasted',
        ]);

        $programResponse = $this->actingAs($bns)->post(route('bns.feeding-programs.store'), [
            'campaign_period_id' => $feedingCampaign->id,
            'name' => 'Purok 4 Recovery Batch',
            'starts_on' => now()->subDays(7)->toDateString(),
            'ends_on' => now()->addDays(21)->toDateString(),
            'program_status' => FeedingProgram::STATUS_ACTIVE,
            'description' => 'Weekly barangay feeding support for at-risk children.',
        ]);

        $feedingProgram = FeedingProgram::query()->firstOrFail();

        $programResponse->assertRedirect(route('bns.feeding-programs.show', $feedingProgram));
        $this->assertDatabaseHas('feeding_programs', [
            'id' => $feedingProgram->id,
            'barangay_id' => $barangay->id,
            'campaign_period_id' => $feedingCampaign->id,
            'program_status' => FeedingProgram::STATUS_ACTIVE,
        ]);

        $enrollmentResponse = $this->actingAs($bns)->post(route('bns.feeding-programs.enrollments.store', $feedingProgram), [
            'resident_id' => $child->id,
            'enrolled_on' => now()->toDateString(),
        ]);

        $enrollment = FeedingProgramEnrollment::query()->firstOrFail();

        $enrollmentResponse->assertRedirect(route('bns.feeding-programs.show', [
            'feedingProgram' => $feedingProgram,
            'enrollment' => $enrollment->id,
        ]));
        $this->assertDatabaseHas('feeding_program_enrollments', [
            'id' => $enrollment->id,
            'feeding_program_id' => $feedingProgram->id,
            'resident_id' => $child->id,
            'baseline_weight_kg' => 10.40,
            'baseline_nutritional_status' => 'Wasted',
            'is_active' => true,
        ]);

        $attendanceResponse = $this->actingAs($bns)->post(route('bns.feeding-programs.attendances.store', [$feedingProgram, $enrollment]), [
            'attendance_date' => now()->toDateString(),
            'attendance_status' => FeedingProgramAttendance::STATUS_PRESENT,
            'notes' => 'Child attended the feeding session.',
        ]);

        $attendanceResponse->assertRedirect(route('bns.feeding-programs.show', [
            'feedingProgram' => $feedingProgram,
            'enrollment' => $enrollment->id,
        ]));
        $this->assertDatabaseHas('feeding_program_attendances', [
            'enrollment_id' => $enrollment->id,
            'attendance_status' => FeedingProgramAttendance::STATUS_PRESENT,
            'notes' => 'Child attended the feeding session.',
        ]);

        $progressResponse = $this->actingAs($bns)->post(route('bns.feeding-programs.progress.store', [$feedingProgram, $enrollment]), [
            'logged_on' => now()->toDateString(),
            'week_number' => 1,
            'weight_kg' => 10.80,
            'remarks' => 'Improved appetite after week 1.',
        ]);

        $progressResponse->assertRedirect(route('bns.feeding-programs.show', [
            'feedingProgram' => $feedingProgram,
            'enrollment' => $enrollment->id,
        ]));
        $this->assertDatabaseHas('feeding_program_progress_logs', [
            'enrollment_id' => $enrollment->id,
            'week_number' => 1,
            'weight_kg' => 10.80,
            'remarks' => 'Improved appetite after week 1.',
        ]);

        $updateEnrollmentResponse = $this->actingAs($bns)->patch(route('bns.feeding-programs.enrollments.update', [$feedingProgram, $enrollment]), [
            'is_active' => '0',
            'completion_notes' => 'Recovered and graduated from the feeding cycle.',
        ]);

        $updateEnrollmentResponse->assertRedirect(route('bns.feeding-programs.show', [
            'feedingProgram' => $feedingProgram,
            'enrollment' => $enrollment->id,
        ]));
        $this->assertDatabaseHas('feeding_program_enrollments', [
            'id' => $enrollment->id,
            'is_active' => false,
            'completion_notes' => 'Recovered and graduated from the feeding cycle.',
        ]);

        $this->assertSame(1, FeedingProgramAttendance::query()->count());
        $this->assertSame(1, FeedingProgramProgressLog::query()->count());
    }

    public function test_bns_can_track_maternal_status_history_and_infant_feeding_follow_up(): void
    {
        [$bns, $barangay, $purok] = $this->bnsContext();
        $mother = $this->createResident($this->createHousehold($purok, '106'), [
            'first_name' => 'Angela',
            'last_name' => 'Cruz',
            'sex' => 'Female',
            'birth_date' => now()->subYears(27)->toDateString(),
            'relationship_to_head' => 'Head',
        ]);
        $infant = $this->createResident($this->createHousehold($purok, '107'), [
            'first_name' => 'Mia',
            'last_name' => 'Cruz',
            'sex' => 'Female',
            'birth_date' => now()->subMonths(8)->toDateString(),
        ]);

        $createProfileResponse = $this->actingAs($bns)->post(route('bns.maternal.profile.store'), [
            'resident_id' => $mother->id,
            'is_currently_pregnant' => '1',
            'expected_delivery_date' => now()->addMonths(4)->toDateString(),
            'current_risk_notes' => 'Needs regular prenatal weight checks.',
        ]);

        $createProfileResponse->assertRedirect(route('bns.maternal.show', $mother));
        $this->assertDatabaseHas('maternal_nutrition_profiles', [
            'resident_id' => $mother->id,
            'barangay_id' => $barangay->id,
            'is_currently_pregnant' => true,
            'is_currently_lactating' => false,
        ]);
        $this->assertDatabaseHas('maternal_nutrition_histories', [
            'resident_id' => $mother->id,
            'event_type' => MaternalNutritionHistory::EVENT_PREGNANCY_STATUS_CHANGE,
            'notes' => 'Status marked active in maternal tracking.',
        ]);

        $updateProfileResponse = $this->actingAs($bns)->put(route('bns.maternal.profile.update', $mother), [
            'is_currently_pregnant' => '1',
            'is_currently_lactating' => '1',
            'expected_delivery_date' => now()->addMonths(4)->toDateString(),
            'current_risk_notes' => 'Post-delivery planning also discussed.',
        ]);

        $updateProfileResponse->assertRedirect(route('bns.maternal.show', $mother));
        $this->assertDatabaseHas('maternal_nutrition_profiles', [
            'resident_id' => $mother->id,
            'is_currently_pregnant' => true,
            'is_currently_lactating' => true,
        ]);
        $this->assertDatabaseHas('maternal_nutrition_histories', [
            'resident_id' => $mother->id,
            'event_type' => MaternalNutritionHistory::EVENT_LACTATING_STATUS_CHANGE,
            'notes' => 'Status marked active in maternal tracking.',
        ]);

        $historyResponse = $this->actingAs($bns)->post(route('bns.maternal.histories.store', $mother), [
            'event_type' => MaternalNutritionHistory::EVENT_PRENATAL_WEIGHT_CHECK,
            'event_date' => now()->toDateString(),
            'gestational_age_weeks' => 24,
            'weight_kg' => 54.30,
            'notes' => 'Routine prenatal follow-up completed.',
        ]);

        $historyResponse->assertRedirect(route('bns.maternal.show', $mother));
        $this->assertDatabaseHas('maternal_nutrition_histories', [
            'resident_id' => $mother->id,
            'event_type' => MaternalNutritionHistory::EVENT_PRENATAL_WEIGHT_CHECK,
            'gestational_age_weeks' => 24,
            'weight_kg' => 54.30,
            'notes' => 'Routine prenatal follow-up completed.',
        ]);

        $feedingLogResponse = $this->actingAs($bns)->post(route('bns.maternal.infant-feeding.store', $mother), [
            'resident_id' => $infant->id,
            'observed_on' => now()->toDateString(),
            'feeding_method' => InfantFeedingLog::METHOD_MIXED_FEEDING,
            'notes' => 'Mixed feeding with complementary food started.',
        ]);

        $feedingLogResponse->assertRedirect(route('bns.maternal.show', $mother));
        $this->assertDatabaseHas('infant_feeding_logs', [
            'resident_id' => $infant->id,
            'mother_resident_id' => $mother->id,
            'feeding_method' => InfantFeedingLog::METHOD_MIXED_FEEDING,
            'notes' => 'Mixed feeding with complementary food started.',
        ]);
        $this->assertSame(3, MaternalNutritionHistory::query()->where('resident_id', $mother->id)->count());
    }

    public function test_bns_can_record_micronutrient_logs_for_toddlers_and_tracked_mothers(): void
    {
        [$bns, $barangay, $purok] = $this->bnsContext();
        $toddler = $this->createResident($this->createHousehold($purok, '108'), [
            'first_name' => 'Leo',
            'last_name' => 'Diaz',
            'sex' => 'Male',
            'birth_date' => now()->subMonths(20)->toDateString(),
        ]);
        $mother = $this->createResident($this->createHousehold($purok, '109'), [
            'first_name' => 'Rhea',
            'last_name' => 'Diaz',
            'sex' => 'Female',
            'birth_date' => now()->subYears(25)->toDateString(),
        ]);

        MaternalNutritionProfile::query()->create([
            'resident_id' => $mother->id,
            'barangay_id' => $barangay->id,
            'updated_by_user_id' => $bns->id,
            'is_currently_pregnant' => true,
            'is_currently_lactating' => true,
            'last_status_updated_at' => now(),
        ]);

        $toddlerResponse = $this->actingAs($bns)->post(route('bns.micronutrients.store'), [
            'resident_id' => $toddler->id,
            'administered_on' => now()->toDateString(),
            'supplement_type' => MicronutrientSupplementationLog::TYPE_VITAMIN_A,
            'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_TODDLER,
            'dose_description' => '1 capsule',
            'remarks' => 'Barangay supplementation day.',
        ]);

        $toddlerResponse->assertRedirect(route('bns.micronutrients.index'));
        $this->assertDatabaseHas('micronutrient_supplementation_logs', [
            'resident_id' => $toddler->id,
            'barangay_id' => $barangay->id,
            'supplement_type' => MicronutrientSupplementationLog::TYPE_VITAMIN_A,
            'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_TODDLER,
        ]);

        $motherResponse = $this->actingAs($bns)->post(route('bns.micronutrients.store'), [
            'resident_id' => $mother->id,
            'administered_on' => now()->toDateString(),
            'supplement_type' => MicronutrientSupplementationLog::TYPE_MNP,
            'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_PREGNANT_WOMAN,
            'dose_description' => '1 sachet',
            'remarks' => 'Issued during prenatal nutrition visit.',
        ]);

        $motherResponse->assertRedirect(route('bns.micronutrients.index'));
        $this->assertDatabaseHas('micronutrient_supplementation_logs', [
            'resident_id' => $mother->id,
            'barangay_id' => $barangay->id,
            'supplement_type' => MicronutrientSupplementationLog::TYPE_MNP,
            'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_PREGNANT_WOMAN,
        ]);
        $this->assertSame(2, MicronutrientSupplementationLog::query()->count());
    }

    public function test_bns_blocks_micronutrient_logs_when_category_does_not_match_resident_state(): void
    {
        [$bns, , $purok] = $this->bnsContext();
        $resident = $this->createResident($this->createHousehold($purok, '110'), [
            'first_name' => 'Lara',
            'last_name' => 'Torres',
            'sex' => 'Female',
            'birth_date' => now()->subYears(23)->toDateString(),
        ]);

        $response = $this->actingAs($bns)->post(route('bns.micronutrients.store'), [
            'resident_id' => $resident->id,
            'administered_on' => now()->toDateString(),
            'supplement_type' => MicronutrientSupplementationLog::TYPE_IRON_DROPS,
            'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_PREGNANT_WOMAN,
            'dose_description' => '5 ml',
        ]);

        $response->assertSessionHasErrors('resident_id');
        $this->assertSame(0, MicronutrientSupplementationLog::query()->count());
    }

    private function bnsContext(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 4,
        ]);
        $bns = User::factory()->create([
            'role' => 'bns',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);

        return [$bns, $barangay, $purok];
    }

    private function createHousehold(Purok $purok, string $householdNo, array $attributes = []): Household
    {
        return Household::query()->create(array_merge([
            'purok_id' => $purok->id,
            'household_no' => $householdNo,
            'household_address' => 'Default Address',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ], $attributes));
    }

    private function createResident(Household $household, array $attributes = []): Resident
    {
        return Resident::query()->create(array_merge([
            'household_id' => $household->id,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juana',
            'birth_date' => now()->subYears(2)->toDateString(),
            'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'relationship_to_head' => 'Daughter',
            'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => true,
        ], $attributes));
    }
}
