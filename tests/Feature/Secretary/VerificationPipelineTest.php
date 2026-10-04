<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDraft;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VerificationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_can_approve_a_household_draft_package_into_verified_records(): void
    {
        [$secretary, $barangay, $purok] = $this->secretaryContext();
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
        ]);

        $householdDraft = HouseholdDraft::query()->create([
            'submitted_by_user_id' => $bhw->id,
            'barangay_id' => $barangay->id,
            'purok_id' => $purok->id,
            'draft_reference_code' => 'DR-001',
            'household_address' => 'Sitio Riverside',
            'drinking_water_source' => 'Deep Well',
            'has_sanitary_toilet' => true,
            'sanitary_toilet_type' => 'Water Sealed',
            'draft_status' => HouseholdDraft::STATUS_PENDING,
        ]);

        $headDraft = ResidentDraft::query()->create([
            'household_draft_id' => $householdDraft->id,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Mario',
            'birth_date' => now()->subYears(36)->toDateString(),
            'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Male',
            'civil_status' => 'Married',
            'citizenship' => 'Filipino',
            'relationship_to_head' => 'Father',
            'is_household_head_candidate' => true,
        ]);

        $childDraft = ResidentDraft::query()->create([
            'household_draft_id' => $householdDraft->id,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Mia',
            'birth_date' => now()->subYears(8)->toDateString(),
            'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'relationship_to_head' => 'Daughter',
        ]);

        $response = $this->actingAs($secretary)->patch(route('secretary.drafts.approve', $householdDraft), [
            'purok_id' => $purok->id,
            'household_no' => '101-A',
            'household_address' => 'Sitio Riverside',
            'drinking_water_source' => 'Deep Well',
            'has_sanitary_toilet' => '1',
            'sanitary_toilet_type' => 'Water Sealed',
            'is_social_aid_beneficiary' => '0',
            'head_draft_id' => $headDraft->id,
            'verification_notes' => 'Names verified from barangay IDs.',
            'residents' => [
                [
                    'draft_id' => $headDraft->id,
                    'philsys_card_no' => null,
                    'last_name' => 'Dela Cruz',
                    'first_name' => 'Mario',
                    'middle_name' => null,
                    'suffix' => null,
                    'birth_date' => $headDraft->birth_date->format('Y-m-d'),
                    'birth_place' => 'Tubigon, Bohol',
                    'sex' => 'Male',
                    'civil_status' => 'Married',
                    'citizenship' => 'Filipino',
                    'religion' => null,
                    'contact_number' => null,
                    'email_address' => null,
                    'relationship_to_head' => 'Father',
                ],
                [
                    'draft_id' => $childDraft->id,
                    'philsys_card_no' => null,
                    'last_name' => 'Dela Cruz',
                    'first_name' => 'Mia',
                    'middle_name' => null,
                    'suffix' => null,
                    'birth_date' => $childDraft->birth_date->format('Y-m-d'),
                    'birth_place' => 'Tubigon, Bohol',
                    'sex' => 'Female',
                    'civil_status' => 'Single',
                    'citizenship' => 'Filipino',
                    'religion' => null,
                    'contact_number' => null,
                    'email_address' => null,
                    'relationship_to_head' => 'Daughter',
                ],
            ],
        ]);

        $approvedHousehold = Household::query()->where('household_no', '101-A')->firstOrFail();

        $response->assertRedirect(route('secretary.households.show', $approvedHousehold));

        $householdDraft->refresh();
        $approvedHousehold->refresh();

        $this->assertSame(HouseholdDraft::STATUS_APPROVED, $householdDraft->draft_status);
        $this->assertSame($approvedHousehold->id, $householdDraft->approved_household_id);
        $this->assertNotNull($approvedHousehold->official_household_code);
        $this->assertSame(2, $approvedHousehold->residents()->count());
        $this->assertNotNull($approvedHousehold->head_resident_id);
        $this->assertSame('Head of Household', $approvedHousehold->headResident?->relationship_to_head);
        $this->assertDatabaseMissing('resident_drafts', [
            'id' => $headDraft->id,
            'approved_resident_id' => null,
        ]);
        $this->assertDatabaseMissing('resident_drafts', [
            'id' => $childDraft->id,
            'approved_resident_id' => null,
        ]);
    }

    public function test_secretary_can_reject_a_household_draft_package(): void
    {
        [$secretary, $barangay] = $this->secretaryContext();

        $householdDraft = HouseholdDraft::query()->create([
            'barangay_id' => $barangay->id,
            'draft_reference_code' => 'DR-REJECT',
            'household_address' => 'Unknown Address',
            'draft_status' => HouseholdDraft::STATUS_PENDING,
        ]);

        $response = $this->actingAs($secretary)->patch(route('secretary.drafts.reject', $householdDraft), [
            'review_notes' => 'Package lacked enough identifying information.',
        ]);

        $response->assertSessionHas('success');

        $householdDraft->refresh();

        $this->assertSame(HouseholdDraft::STATUS_REJECTED, $householdDraft->draft_status);
        $this->assertSame('Package lacked enough identifying information.', $householdDraft->verification_notes);
        $this->assertSame($secretary->id, $householdDraft->reviewed_by_user_id);
    }

    public function test_secretary_can_apply_a_resident_correction_request_with_final_edits(): void
    {
        [$secretary, $barangay, $purok] = $this->secretaryContext();
        $oldHousehold = $this->createHousehold($purok, '001');
        $newHousehold = $this->createHousehold($purok, '002');
        $resident = $this->createResident($oldHousehold, [
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'contact_number' => '09000000000',
        ]);

        $updateRequest = ProfileUpdateRequest::query()->create([
            'submitted_by_user_id' => $secretary->id,
            'barangay_id' => $barangay->id,
            'subject_type' => ProfileUpdateRequest::SUBJECT_RESIDENT,
            'subject_id' => $resident->id,
            'current_snapshot' => [
                'first_name' => 'Ana',
                'contact_number' => '09000000000',
                'household_id' => $oldHousehold->id,
            ],
            'proposed_changes' => [
                'first_name' => 'Anna',
                'contact_number' => '09111111111',
                'household_id' => $newHousehold->id,
                'relationship_to_head' => 'Head of Household',
            ],
            'request_reason' => 'Resident moved households and corrected first name spelling.',
        ]);

        $response = $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $updateRequest), [
            'household_id' => $newHousehold->id,
            'philsys_card_no' => null,
            'last_name' => 'Lopez',
            'first_name' => 'Anna Maria',
            'middle_name' => null,
            'suffix' => null,
            'birth_date' => $resident->birth_date->format('Y-m-d'),
            'birth_place' => $resident->birth_place,
            'sex' => $resident->sex,
            'civil_status' => $resident->civil_status,
            'citizenship' => $resident->citizenship,
            'religion' => $resident->religion,
            'contact_number' => '09112223333',
            'email_address' => $resident->email_address,
            'set_as_household_head' => '1',
            'relationship_to_head' => 'Head of Household',
            'resident_status' => Resident::STATUS_ACTIVE,
            'moved_in_at' => now()->toDateString(),
            'moved_out_at' => null,
            'date_of_death' => null,
            'status_notes' => 'Verified move after secretary interview.',
            'is_active' => '1',
            'review_notes' => 'Applied with final secretary-verified spelling.',
        ]);

        $response->assertRedirect(route('secretary.residents.show', $resident));

        $resident->refresh();
        $updateRequest->refresh();
        $newHousehold->refresh();

        $this->assertSame('Anna Maria', $resident->first_name);
        $this->assertSame('09112223333', $resident->contact_number);
        $this->assertSame($newHousehold->id, $resident->household_id);
        $this->assertSame('Head of Household', $resident->relationship_to_head);
        $this->assertSame($resident->id, $newHousehold->head_resident_id);
        $this->assertSame(ProfileUpdateRequest::STATUS_APPROVED, $updateRequest->request_status);
        $this->assertSame('Applied with final secretary-verified spelling.', $updateRequest->review_notes);
        $this->assertSame('Anna Maria', data_get($updateRequest->proposed_changes, 'first_name'));
    }

    public function test_secretary_can_apply_a_household_correction_request(): void
    {
        [$secretary, $barangay, $purok] = $this->secretaryContext();
        $household = $this->createHousehold($purok, '010', [
            'household_address' => 'Old Address',
            'is_social_aid_beneficiary' => false,
        ]);
        $resident = $this->createResident($household, [
            'first_name' => 'Carlos',
            'last_name' => 'Santos',
            'relationship_to_head' => 'Head of Household',
        ]);
        $household->update(['head_resident_id' => $resident->id]);

        $updateRequest = ProfileUpdateRequest::query()->create([
            'submitted_by_user_id' => $secretary->id,
            'barangay_id' => $barangay->id,
            'subject_type' => ProfileUpdateRequest::SUBJECT_HOUSEHOLD,
            'subject_id' => $household->id,
            'current_snapshot' => [
                'household_address' => 'Old Address',
                'is_social_aid_beneficiary' => false,
            ],
            'proposed_changes' => [
                'household_address' => 'New Address',
                'is_social_aid_beneficiary' => true,
            ],
            'request_reason' => 'Secretary needs to update assistance status and corrected address.',
        ]);

        $response = $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $updateRequest), [
            'purok_id' => $purok->id,
            'household_no' => '010',
            'household_address' => 'Verified New Address',
            'drinking_water_source' => null,
            'has_sanitary_toilet' => '1',
            'sanitary_toilet_type' => 'Water Sealed',
            'head_resident_id' => $resident->id,
            'is_social_aid_beneficiary' => '1',
            'is_active' => '1',
            'review_notes' => 'Applied after barangay hall verification.',
        ]);

        $response->assertRedirect(route('secretary.households.show', $household));

        $household->refresh();
        $updateRequest->refresh();

        $this->assertSame('Verified New Address', $household->household_address);
        $this->assertTrue($household->is_social_aid_beneficiary);
        $this->assertSame(ProfileUpdateRequest::STATUS_APPROVED, $updateRequest->request_status);
        $this->assertSame('Applied after barangay hall verification.', $updateRequest->review_notes);
    }

    public function test_former_secretary_triage_queue_is_unavailable_without_deleting_records(): void
    {
        [$secretary, $barangay, $purok] = $this->secretaryContext();
        $household = $this->createHousehold($purok, '020');
        $resident = $this->createResident($household, [
            'first_name' => 'Scoped',
            'last_name' => 'Resident',
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
        ]);

        TriageRecord::query()->create([
            'resident_id' => $resident->id,
            'household_id' => $household->id,
            'barangay_id' => $barangay->id,
            'purok_id' => $purok->id,
            'recorded_by_user_id' => $bhw->id,
            'triage_status' => TriageRecord::STATUS_PENDING,
            'measured_at' => now(),
            'triage_notes' => 'Scoped note',
        ]);

        $foreignBarangay = Barangay::factory()->create();
        $foreignPurok = Purok::factory()->create([
            'barangay_id' => $foreignBarangay->id,
            'purok_number' => 8,
        ]);
        $foreignHousehold = $this->createHousehold($foreignPurok, '099');
        $foreignResident = $this->createResident($foreignHousehold, [
            'first_name' => 'Foreign',
            'last_name' => 'Resident',
        ]);
        $foreignBhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $foreignBarangay->id,
            'assigned_purok_id' => $foreignPurok->id,
        ]);

        TriageRecord::query()->create([
            'resident_id' => $foreignResident->id,
            'household_id' => $foreignHousehold->id,
            'barangay_id' => $foreignBarangay->id,
            'purok_id' => $foreignPurok->id,
            'recorded_by_user_id' => $foreignBhw->id,
            'triage_status' => TriageRecord::STATUS_PENDING,
            'measured_at' => now(),
            'triage_notes' => 'Foreign note',
        ]);

        $this->actingAs($secretary)->get('/secretary/triage-queue')->assertNotFound();
        $this->assertDatabaseCount('triage_records', 2);
    }

    public static function childOutcomeCases(): array
    {
        return [
            'pending unresolved' => ['pending', false, 1, false, 'Still pending review'],
            'rejected unresolved' => ['rejected', false, 1, false, 'Rejected'],
            'multiple rejected children' => ['rejected', false, 3, false, 'Rejected'],
            'approved linked' => ['approved', true, 1, false, 'Approved to verified resident'],
            'approved missing link' => ['approved', false, 1, false, 'Approved'],
            'historical rejected' => ['rejected', false, 2, true, 'Rejected'],
            'rejected with explicit approval link' => ['rejected', true, 1, false, 'Approved to verified resident'],
            'pending with explicit approval link' => ['pending', true, 1, false, 'Approved to verified resident'],
        ];
    }

    #[DataProvider('childOutcomeCases')]
    public function test_draft_detail_child_wording_follows_parent_without_mutating_records(
        string $status, bool $linked, int $childCount, bool $historical, string $expected
    ): void {
        [$secretary, $barangay, $purok] = $this->secretaryContext();
        $draft = HouseholdDraft::create([
            'barangay_id' => $barangay->id, 'purok_id' => $purok->id,
            'household_address' => 'Synthetic draft fixture', 'draft_status' => $status,
            'reviewed_by_user_id' => $status === 'pending' ? null : $secretary->id,
            'reviewed_at' => $status === 'pending' ? null : ($historical ? '2025-01-15 10:00:00' : now()),
            'verification_notes' => 'Existing package review notes remain visible.',
        ]);
        if ($historical) {
            $draft->forceFill(['created_at' => '2025-01-14 10:00:00'])->save();
        }
        $approvedResident = $linked ? $this->createResident($this->createHousehold($purok, 'OUTCOME-1')) : null;
        $children = [];
        for ($index = 0; $index < $childCount; $index++) {
            $children[] = ResidentDraft::create([
                'household_draft_id' => $draft->id, 'last_name' => 'Fixture', 'first_name' => 'Child '.$index,
                'birth_date' => '2018-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female',
                'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Daughter',
                'approved_resident_id' => $approvedResident?->id,
            ]);
        }
        $before = $draft->fresh()->getAttributes();
        $childAttributes = array_map(fn (ResidentDraft $child) => $child->fresh()->getAttributes(), $children);

        $page = $this->actingAs($secretary)->get(route('secretary.drafts.show', $draft))->assertOk()
            ->assertSee('Review Notes')->assertSee('Existing package review notes remain visible.');
        if ($status !== 'pending') {
            $page->assertDontSee('Still pending review');
        }
        $document = new \DOMDocument();
        @$document->loadHTML($page->getContent());
        $nodes = (new \DOMXPath($document))->query(
            '//section[.//h3[normalize-space(.)="Resident Drafts"]]//div[@class="text-sm text-slate-500"]'
        );
        $this->assertCount($childCount, $nodes);
        foreach ($nodes as $node) {
            $this->assertSame($expected, trim($node->textContent));
        }
        $this->assertSame($before, $draft->fresh()->getAttributes());
        foreach ($children as $index => $child) {
            $this->assertSame($childAttributes[$index], $child->fresh()->getAttributes());
        }
    }

    private function secretaryContext(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 4,
        ]);
        $secretary = User::factory()->create([
            'role' => 'secretary',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);

        return [$secretary, $barangay, $purok];
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
            'birth_date' => now()->subYears(32)->toDateString(),
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
