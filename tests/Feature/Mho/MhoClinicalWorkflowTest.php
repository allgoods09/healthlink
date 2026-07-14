<?php

namespace Tests\Feature\Mho;

use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\Household;
use App\Models\MhoClinicalReview;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MhoClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mho_can_record_a_final_review_for_an_escalated_case(): void
    {
        [$mho, $encounter] = $this->mhoEncounterContext('Mendoza');

        $response = $this->actingAs($mho)->post(route('mho.reviews.store', $encounter), [
            'reviewed_at' => now()->format('Y-m-d H:i:s'),
            'final_assessment' => 'Municipal review confirms acute respiratory infection with no admission required.',
            'diagnostic_override' => 'Adjusted working diagnosis after municipal review.',
            'prescription_notes' => 'Continue antibiotics for seven days and monitor fever.',
            'referral_destination' => 'Tubigon RHU Laboratory',
            'final_disposition' => 'Discharged with municipal follow-up',
            'return_instructions' => 'Return in three days or sooner if symptoms worsen.',
            'resolution_notes' => 'Case stabilized after municipal review.',
            'follow_up_status' => ClinicalEncounter::FOLLOW_UP_COMPLETED,
            'follow_up_notes' => 'Municipal review completed and closed.',
        ]);

        $response->assertRedirect(route('mho.escalations.show', $encounter));

        $encounter->refresh();
        $review = MhoClinicalReview::query()->where('clinical_encounter_id', $encounter->id)->firstOrFail();

        $this->assertSame($mho->id, $review->reviewed_by_user_id);
        $this->assertSame('Discharged with municipal follow-up', $review->final_disposition);
        $this->assertSame(ClinicalEncounter::FOLLOW_UP_COMPLETED, $encounter->follow_up_status);
        $this->assertNotNull($encounter->closed_at);
    }

    public function test_mho_queue_separates_pending_and_reviewed_cases(): void
    {
        [$mho, $pendingEncounter] = $this->mhoEncounterContext('Pending');
        [, $reviewedEncounter] = $this->mhoEncounterContext('Reviewed');

        MhoClinicalReview::query()->create([
            'clinical_encounter_id' => $reviewedEncounter->id,
            'reviewed_by_user_id' => $mho->id,
            'reviewed_at' => now(),
            'final_assessment' => 'Reviewed by the MHO.',
            'final_disposition' => 'Case closed',
        ]);

        $reviewedEncounter->update([
            'follow_up_status' => ClinicalEncounter::FOLLOW_UP_COMPLETED,
            'closed_at' => now(),
        ]);

        $pendingResponse = $this->actingAs($mho)->get(route('mho.escalations.index'));
        $pendingResponse->assertOk();
        $pendingResponse->assertSee('Pending, Case Dela');
        $pendingResponse->assertDontSee('Reviewed, Case Dela');

        $reviewedResponse = $this->actingAs($mho)->get(route('mho.escalations.index', ['status' => 'reviewed']));
        $reviewedResponse->assertOk();
        $reviewedResponse->assertSee('Reviewed, Case Dela');
        $reviewedResponse->assertDontSee('Pending, Case Dela');
    }

    private function mhoEncounterContext(string $residentLastName): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 3,
        ]);
        $household = Household::query()->create([
            'purok_id' => $purok->id,
            'household_no' => '210',
            'household_address' => 'Purok 3, '.$barangay->name.', Tubigon, Bohol',
            'drinking_water_source' => 'Level II',
            'has_sanitary_toilet' => true,
            'sanitary_toilet_type' => 'Water sealed',
            'garbage_disposal_method' => 'collection',
            'has_backyard_garden' => true,
            'housing_material_type' => 'mixed',
            'is_active' => true,
        ]);
        $resident = Resident::query()->create([
            'household_id' => $household->id,
            'last_name' => $residentLastName,
            'first_name' => 'Case',
            'middle_name' => 'Dela',
            'birth_date' => now()->subYears(34)->toDateString(),
            'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female',
            'civil_status' => 'Married',
            'citizenship' => 'Filipino',
            'religion' => 'Roman Catholic',
            'contact_number' => '09171234567',
            'relationship_to_head' => 'Self',
            'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $phn = User::factory()->create([
            'role' => 'phn',
        ]);
        $mho = User::factory()->create([
            'role' => 'mho',
        ]);

        $encounter = ClinicalEncounter::query()->create([
            'resident_id' => $resident->id,
            'household_id' => $household->id,
            'barangay_id' => $barangay->id,
            'purok_id' => $purok->id,
            'attended_by_user_id' => $phn->id,
            'encounter_source' => ClinicalEncounter::SOURCE_WALK_IN,
            'encountered_at' => now()->subHour(),
            'consultation_notes' => 'Resident presented with fever and persistent cough.',
            'working_impression' => 'Possible respiratory infection.',
            'action_taken' => 'Initial consultation completed by PHN.',
            'disposition' => 'For municipal review',
            'is_escalated_to_mho' => true,
            'escalation_notes' => 'Needs municipal confirmation and final prescription.',
            'escalated_at' => now()->subMinutes(30),
        ]);

        return [$mho, $encounter];
    }
}
