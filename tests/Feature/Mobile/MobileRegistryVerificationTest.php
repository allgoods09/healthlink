<?php

namespace Tests\Feature\Mobile;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileRegistryVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $bhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id, 'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $secretary = User::factory()->create([
            'role' => 'secretary', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);

        return [$bhw, $secretary, $purok];
    }

    private function householdInput(array $overrides = []): array
    {
        return array_replace([
            'mobile_uuid' => '00000000-0000-4000-8000-000000000101',
            'local_revision' => 1,
            'household_no' => 'M-101',
            'household_address' => 'Purok Test',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ], $overrides);
    }

    private function residentInput(array $overrides = []): array
    {
        return array_replace([
            'mobile_uuid' => '00000000-0000-4000-8000-000000000102',
            'local_revision' => 1,
            'household_mobile_uuid' => '00000000-0000-4000-8000-000000000101',
            'last_name' => 'Santos', 'first_name' => 'Lina', 'middle_name' => null,
            'suffix' => null, 'birth_date' => '1998-04-14', 'birth_place' => 'Tubigon',
            'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'religion' => null, 'contact_number' => null, 'email_address' => null,
            'relationship_to_head' => 'Head', 'is_active' => true,
        ], $overrides);
    }

    private function sync(User $bhw, array $households = [], array $residents = []): TestResponse
    {
        Sanctum::actingAs($bhw, ['mobile']);

        return $this->postJson('/api/mobile/sync', compact('households', 'residents'));
    }

    private function approvalResident(ResidentDraft $draft): array
    {
        return [
            'draft_id' => $draft->id, 'philsys_card_no' => $draft->philsys_card_no,
            'last_name' => $draft->last_name, 'first_name' => $draft->first_name,
            'middle_name' => $draft->middle_name, 'suffix' => $draft->suffix,
            'birth_date' => $draft->birth_date->toDateString(), 'birth_place' => $draft->birth_place,
            'sex' => $draft->sex, 'civil_status' => $draft->civil_status,
            'citizenship' => $draft->citizenship, 'religion' => $draft->religion,
            'contact_number' => $draft->contact_number, 'email_address' => $draft->email_address,
            'relationship_to_head' => $draft->relationship_to_head,
        ];
    }

    public function test_new_household_and_resident_stay_linked_through_review_and_approval_then_reconcile_by_uuid(): void
    {
        [$bhw, $secretary, $purok] = $this->team();
        $householdInput = $this->householdInput();
        $residentInput = $this->residentInput();
        $this->sync($bhw, [$householdInput], [$residentInput])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('resolved_records.households.0.id', null)
            ->assertJsonPath('resolved_records.households.0.verification_status', 'submitted')
            ->assertJsonPath('resolved_records.residents.0.id', null)
            ->assertJsonPath('resolved_records.residents.0.verification_status', 'submitted');

        $draft = HouseholdDraft::where('mobile_uuid', $householdInput['mobile_uuid'])->firstOrFail();
        $residentDraft = ResidentDraft::where('mobile_uuid', $residentInput['mobile_uuid'])->firstOrFail();
        $this->assertSame($draft->id, $residentDraft->household_draft_id);
        $this->assertSame($bhw->id, $draft->submitted_by_user_id);
        $this->assertSame($purok->id, $draft->purok_id);
        $this->assertSame(0, Household::count());
        $this->assertSame(0, Resident::count());
        $this->actingAs($secretary)->get(route('secretary.drafts.index'))->assertOk()->assertSee($draft->draft_reference_code);
        $this->actingAs($secretary)->get(route('secretary.drafts.show', $draft))->assertOk()->assertSee('Lina');

        $this->actingAs($secretary)->patch(route('secretary.drafts.approve', $draft), [
            'purok_id' => $purok->id, 'household_no' => $householdInput['household_no'],
            'household_address' => $householdInput['household_address'],
            'is_social_aid_beneficiary' => '0', 'head_draft_id' => $residentDraft->id,
            'residents' => [$this->approvalResident($residentDraft)],
        ])->assertSessionHasNoErrors();

        $official = Household::where('mobile_uuid', $householdInput['mobile_uuid'])->firstOrFail();
        $resident = Resident::where('mobile_uuid', $residentInput['mobile_uuid'])->firstOrFail();
        $this->assertSame($official->id, $resident->household_id);
        $this->assertSame($official->id, $draft->fresh()->approved_household_id);
        $this->assertSame($resident->id, $residentDraft->fresh()->approved_resident_id);
        Sanctum::actingAs($bhw, ['mobile']);
        $bootstrap = $this->getJson('/api/mobile/bootstrap')->assertOk();
        $this->assertCount(1, collect($bootstrap->json('households'))->where('mobile_uuid', $householdInput['mobile_uuid']));
        $this->assertCount(1, collect($bootstrap->json('residents'))->where('mobile_uuid', $residentInput['mobile_uuid']));
        $bootstrap->assertJsonPath('households.0.id', $official->id)
            ->assertJsonPath('residents.0.id', $resident->id);
    }

    public function test_new_resident_under_verified_household_is_reviewed_without_replacing_household(): void
    {
        [$bhw, $secretary, $purok] = $this->team();
        $household = Household::create([
            'purok_id' => $purok->id, 'household_no' => 'M-201',
            'household_address' => 'Existing home', 'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $this->sync($bhw, [], [$this->residentInput([
            'household_id' => $household->id, 'household_mobile_uuid' => null,
        ])])->assertJsonPath('status', 'success');
        $draft = ResidentDraft::firstOrFail();
        $package = $draft->householdDraft;
        $this->assertSame($household->id, $package->target_household_id);
        $this->assertSame(0, Resident::count());
        $this->actingAs($secretary)->get(route('secretary.drafts.show', $package))->assertOk()->assertSee('Lina');
        $this->actingAs($secretary)->patch(route('secretary.drafts.approve', $package), [
            'purok_id' => $purok->id, 'residents' => [array_replace($this->approvalResident($draft), ['relationship_to_head' => 'Daughter'])],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Household::count());
        $this->assertSame($household->id, Resident::firstOrFail()->household_id);
        $this->assertSame('Existing home', $household->fresh()->household_address);
    }

    public function test_household_only_submission_can_be_approved_without_fabricating_a_resident(): void
    {
        [$bhw, $secretary, $purok] = $this->team();
        $this->sync($bhw, [$this->householdInput()])->assertJsonPath('status', 'success');
        $draft = HouseholdDraft::firstOrFail();
        $this->actingAs($secretary)->get(route('secretary.drafts.edit', $draft))->assertOk();
        $this->actingAs($secretary)->patch(route('secretary.drafts.approve', $draft), [
            'purok_id' => $purok->id, 'household_no' => 'M-101',
            'household_address' => 'Purok Test',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Household::count());
        $this->assertSame(0, Resident::count());
        $this->assertSame('M-101', Household::firstOrFail()->household_no);
    }

    public function test_mobile_retry_reuses_the_same_pending_draft_and_newer_revision_updates_it(): void
    {
        [$bhw] = $this->team();
        $input = $this->householdInput();
        $this->sync($bhw, [$input])->assertJsonPath('status', 'success');
        $this->sync($bhw, [$input])->assertJsonPath('status', 'success');
        $this->assertSame(1, HouseholdDraft::count());
        $this->sync($bhw, [$this->householdInput([
            'local_revision' => 2, 'household_address' => 'Corrected address',
        ])])->assertJsonPath('status', 'success');
        $draft = HouseholdDraft::firstOrFail();
        $this->assertSame('Corrected address', $draft->household_address);
        $this->assertSame(2, $draft->mobile_revision);
        $this->assertSame(0, Household::count());
    }

    public function test_rejected_household_submission_remains_nonofficial_and_reason_returns_to_mobile(): void
    {
        [$bhw, $secretary] = $this->team();
        $this->sync($bhw, [$this->householdInput()], [$this->residentInput()])->assertJsonPath('status', 'success');
        $draft = HouseholdDraft::firstOrFail();
        $this->actingAs($secretary)->patch(route('secretary.drafts.reject', $draft), [
            'review_notes' => 'Check the household number first.',
        ])->assertSessionHas('success');
        $this->assertSame(0, Household::count());
        $this->assertSame(0, Resident::count());
        Sanctum::actingAs($bhw, ['mobile']);
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('households.0.verification_status', 'rejected')
            ->assertJsonPath('households.0.verification_notes', 'Check the household number first.')
            ->assertJsonPath('residents.0.verification_status', 'rejected');
    }

    public function test_verified_household_and_resident_edits_submit_corrections_without_direct_write(): void
    {
        [$bhw, $secretary, $purok] = $this->team();
        $household = Household::create([
            'purok_id' => $purok->id, 'household_no' => 'M-301',
            'household_address' => 'Old address', 'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $resident = Resident::create([
            'household_id' => $household->id, 'last_name' => 'Santos', 'first_name' => 'Lina',
            'birth_date' => '1998-04-14', 'birth_place' => 'Tubigon', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Daughter', 'is_active' => true,
        ]);
        $this->sync($bhw, [$this->householdInput([
            'id' => $household->id, 'household_no' => 'M-301', 'household_address' => 'New address',
        ])], [$this->residentInput([
            'id' => $resident->id, 'household_id' => $household->id,
            'household_mobile_uuid' => null, 'first_name' => 'Lina Maria',
            'relationship_to_head' => 'Daughter',
        ])])->assertJsonPath('status', 'success')
            ->assertJsonPath('resolved_records.households.0.verification_status', 'submitted')
            ->assertJsonPath('resolved_records.residents.0.verification_status', 'submitted');
        $this->assertSame('Old address', $household->fresh()->household_address);
        $this->assertSame('Lina', $resident->fresh()->first_name);
        $requests = ProfileUpdateRequest::all();
        $this->assertCount(2, $requests);
        $this->actingAs($secretary)->get(route('secretary.update-requests.index'))->assertOk();
        foreach ($requests as $request) {
            $this->actingAs($secretary)->get(route('secretary.update-requests.show', $request))->assertOk();
        }
        $householdRequest = $requests->firstWhere('subject_type', 'household');
        $this->actingAs($secretary)->patch(route('secretary.update-requests.reject', $householdRequest), [
            'review_notes' => 'Address not confirmed.',
        ])->assertSessionHas('success');
        $this->assertSame('Old address', $household->fresh()->household_address);
        $residentRequest = $requests->firstWhere('subject_type', 'resident');
        $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $residentRequest), [
            'household_id' => $household->id, 'last_name' => 'Santos', 'first_name' => 'Lina Maria',
            'birth_date' => '1998-04-14', 'birth_place' => 'Tubigon', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Daughter', 'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Lina Maria', $resident->fresh()->first_name);
        Sanctum::actingAs($bhw, ['mobile']);
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('households.0.verification_status', 'rejected')
            ->assertJsonPath('residents.0.verification_status', 'approved');
    }

    public function test_cross_purok_and_cross_barangay_submission_cannot_create_drafts(): void
    {
        [$bhw, , $purok] = $this->team();
        $otherBarangay = Barangay::factory()->create();
        $otherPurok = Purok::factory()->create(['barangay_id' => $otherBarangay->id]);
        $otherHousehold = Household::create([
            'purok_id' => $otherPurok->id, 'household_no' => 'Other',
            'household_address' => 'Other barangay', 'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $this->sync($bhw, [$this->householdInput(['purok_id' => $otherPurok->id])])
            ->assertJsonPath('status', 'failed');
        $this->sync($bhw, [], [$this->residentInput([
            'household_id' => $otherHousehold->id, 'household_mobile_uuid' => null,
        ])])->assertJsonPath('status', 'failed');
        $this->assertSame(0, HouseholdDraft::count());
        $this->assertSame(0, ResidentDraft::count());
        $this->assertSame($purok->id, $bhw->assigned_purok_id);
    }
}
