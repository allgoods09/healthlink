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
use App\Support\MobileResidentRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileResidentRequestIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id, 'approval_status' => 'approved', 'is_active' => true]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => 'EX-100',
            'household_address' => 'Existing home', 'is_active' => true]);
        return [$bhw, $secretary, $household];
    }

    private function input(array $extra = []): array
    {
        return array_replace(['mobile_uuid' => '10000000-0000-4000-8000-000000000001', 'local_revision' => 1,
            'request_contract_version' => 2, 'first_name' => 'Lina', 'last_name' => 'Santos',
            'birth_date' => '1998-04-14', 'birth_place' => 'Tubigon', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Daughter',
            'is_active' => true, 'middle_name' => null, 'suffix' => null, 'religion' => null,
            'email_address' => null, 'contact_number' => null, 'propose_household_head' => false], $extra);
    }

    private function sync(User $bhw, array $input)
    {
        Sanctum::actingAs($bhw, ['mobile']);
        return $this->postJson('/api/mobile/sync', ['residents' => [$input]])->assertOk();
    }

    private function correction(Resident $resident, array $changes, int $revision = 1): array
    {
        return ['id' => $resident->id, 'household_id' => $resident->household_id,
            'mobile_uuid' => '10000000-0000-4000-8000-000000000002', 'local_revision' => $revision,
            'request_contract_version' => 2, 'base_snapshot' => MobileResidentRequestData::snapshot($resident),
            'proposed_changes' => $changes];
    }

    private function resident(Household $household, array $extra = []): Resident
    {
        return Resident::create(array_replace(['household_id' => $household->id,
            'first_name' => 'Ana', 'last_name' => 'Pilot', 'birth_date' => '1990-01-01',
            'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Daughter', 'resident_status' => 'active', 'is_active' => false,
            'philsys_card_no' => 'UNSEEN-123', 'middle_name' => 'Old Middle', 'suffix' => 'II',
            'religion' => 'Old Religion', 'contact_number' => '09123456789', 'email_address' => 'old@example.test'], $extra));
    }

    private function approveDraft(User $secretary, ResidentDraft $child, array $extra = [])
    {
        $package = $child->householdDraft;
        return $this->actingAs($secretary)->patch(route('secretary.drafts.approve', $package), array_replace([
            'purok_id' => $package->purok_id,
            'household_no' => $package->targetHousehold?->household_no ?? 'NEW-100', 'household_address' => 'Pilot address',
            'residents' => [array_replace($child->only(['id', 'first_name', 'last_name', 'middle_name', 'suffix',
                'birth_place', 'sex', 'civil_status', 'citizenship', 'religion', 'contact_number', 'email_address',
                'relationship_to_head']), ['draft_id' => $child->id, 'birth_date' => $child->birth_date->toDateString()])],
        ], $extra));
    }

    public function test_explicit_clears_are_reviewed_and_approved_without_touching_unseen_fields(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        $resident = $this->resident($household);
        $clears = array_fill_keys(['middle_name', 'suffix', 'religion', 'contact_number', 'email_address'], null);
        $input = $this->correction($resident, $clears);
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $request = ProfileUpdateRequest::firstOrFail();
        $this->assertSame($clears, $request->proposed_changes);
        $this->assertSame('Old Middle', $resident->fresh()->middle_name);
        $this->actingAs($secretary)->get(route('secretary.update-requests.show', $request))->assertOk()->assertSee('Old Middle');
        $html = $this->actingAs($secretary)->get(route('secretary.update-requests.edit', $request))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="correction_middle_name"[^>]*value=""/', $html);
        $final = array_replace(MobileResidentRequestData::snapshot($resident), $clears);
        unset($final['philsys_card_no'], $final['is_active']);
        $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $request), $final)->assertSessionHasNoErrors();
        foreach ($clears as $field => $value) $this->assertNull($resident->fresh()->{$field});
        $this->assertSame('UNSEEN-123', $resident->fresh()->philsys_card_no);
        $this->assertFalse($resident->fresh()->is_active);
        $this->assertTrue($resident->fresh()->isCurrentPopulation());
        $this->sync($bhw, $input)->assertJsonPath('resolved_records.residents.0.verification_status', 'approved');
        $this->assertSame(1, ProfileUpdateRequest::count());
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('residents.0.middle_name', null)
            ->assertJsonPath('residents.0.philsys_card_no', 'UNSEEN-123');
    }

    public function test_pending_correction_blocks_another_revision_but_exact_retry_is_idempotent(): void
    {
        [$bhw, , $household] = $this->team();
        $resident = $this->resident($household);
        $input = $this->correction($resident, ['first_name' => 'Corrected']);
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $this->sync($bhw, $this->correction($resident, ['first_name' => 'Another'], 2))->assertJsonPath('status', 'failed');
        $this->assertSame(1, ProfileUpdateRequest::count());
        $this->assertSame(['first_name' => 'Corrected'], ProfileUpdateRequest::first()->proposed_changes);
        $this->assertNotSame('Corrected', $resident->fresh()->first_name);
    }

    public function test_stale_submission_cannot_overwrite_a_changed_official_record(): void
    {
        [$bhw, , $household] = $this->team();
        $resident = $this->resident($household);
        $input = $this->correction($resident, ['first_name' => 'Proposed']);
        $resident->update(['middle_name' => 'Secretary changed']);
        $this->sync($bhw, $input)->assertJsonPath('status', 'failed');
        $this->assertSame(0, ProfileUpdateRequest::count());
        $this->assertSame('Secretary changed', $resident->fresh()->middle_name);
    }

    public function test_stale_approval_is_rejected_and_evidence_remains_pending(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        $resident = $this->resident($household);
        $this->sync($bhw, $this->correction($resident, ['first_name' => 'Proposed']))->assertJsonPath('status', 'success');
        $request = ProfileUpdateRequest::firstOrFail();
        $resident->update(['first_name' => 'Secretary changed']);
        $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $request),
            array_replace(MobileResidentRequestData::snapshot($resident), ['first_name' => 'Proposed']))->assertSessionHasErrors('resident');
        $this->assertSame('Secretary changed', $resident->fresh()->first_name);
        $this->assertSame('pending', $request->fresh()->request_status);
    }

    public function test_lifecycle_ineligibility_before_submission_or_approval_never_reactivates(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        $resident = $this->resident($household);
        $input = $this->correction($resident, ['first_name' => 'Proposed']);
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $request = ProfileUpdateRequest::firstOrFail();
        $resident->update(['resident_status' => 'deceased', 'date_of_death' => now()->toDateString()]);
        $this->sync($bhw, $input)->assertJsonPath('status', 'failed');
        $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $request),
            array_replace($input['base_snapshot'], ['first_name' => 'Proposed']))->assertSessionHasErrors('resident');
        $this->assertSame('deceased', $resident->fresh()->resident_status);
        $this->assertSame('pending', $request->fresh()->request_status);
    }

    public function test_rejected_correction_retains_reason_and_retry_cannot_reopen_it(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        $resident = $this->resident($household);
        $name = $resident->first_name;
        $input = $this->correction($resident, ['first_name' => 'Proposed']);
        $this->sync($bhw, $input);
        $request = ProfileUpdateRequest::firstOrFail();
        $this->actingAs($secretary)->patch(route('secretary.update-requests.reject', $request), ['review_notes' => 'Please confirm spelling.'])->assertSessionHas('success');
        $this->sync($bhw, $input)->assertJsonPath('resolved_records.residents.0.verification_status', 'rejected');
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('residents.0.verification_notes', 'Please confirm spelling.');
        $this->assertSame($name, $resident->fresh()->first_name);
        $this->assertSame(1, ProfileUpdateRequest::count());
    }

    public function test_noneditable_and_lifecycle_fields_are_not_accepted_as_correction_changes(): void
    {
        [$bhw, , $household] = $this->team();
        $resident = $this->resident($household);
        foreach (['is_active', 'resident_status', 'head_resident_id'] as $field) {
            $this->sync($bhw, $this->correction($resident, [$field => 'forbidden']))->assertJsonPath('status', 'failed');
        }
        $this->assertSame(0, ProfileUpdateRequest::count());
        $this->assertSame('UNSEEN-123', $resident->fresh()->philsys_card_no);
    }

    public function test_base_required_and_household_change_cannot_target_a_foreign_purok(): void
    {
        [$bhw, , $household] = $this->team();
        $resident = $this->resident($household);
        $input = $this->correction($resident, ['first_name' => 'Proposed']);
        $input['base_snapshot'] = [];
        $this->sync($bhw, $input)->assertJsonPath('status', 'failed');
        $foreign = Household::create(['purok_id' => Purok::factory()->create()->id,
            'household_no' => 'FOREIGN', 'household_address' => 'Elsewhere']);
        $input = $this->correction($resident, ['household_id' => $foreign->id]);
        $input['household_id'] = $foreign->id;
        $this->sync($bhw, $input)->assertJsonPath('status', 'failed');
        $this->assertSame(0, ProfileUpdateRequest::count());
    }

    public function test_existing_household_new_request_does_not_infer_head_and_approved_retry_does_not_create_correction(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        $head = $this->resident($household, ['is_active' => true]);
        $household->update(['head_resident_id' => $head->id]);
        $input = $this->input(['household_id' => $household->id]);
        $this->sync($bhw, $input)->assertJsonPath('resolved_records.residents.0.id', null);
        $child = ResidentDraft::firstOrFail();
        $this->assertFalse($child->is_household_head_candidate);
        $this->assertSame(1, Resident::count());
        $final = array_replace($child->only(['last_name', 'first_name', 'birth_place', 'sex', 'civil_status', 'citizenship', 'relationship_to_head']),
            ['draft_id' => $child->id, 'birth_date' => $child->birth_date->toDateString(), 'first_name' => 'Secretary final']);
        $this->approveDraft($secretary, $child, ['residents' => [$final]])->assertSessionHasNoErrors();
        $this->assertSame($head->id, $household->fresh()->head_resident_id);
        $this->sync($bhw, $input)->assertJsonPath('resolved_records.residents.0.verification_status', 'approved');
        $this->sync($bhw, array_replace($input, ['local_revision' => 2, 'first_name' => 'Newer local work']))
            ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.residents');
        $this->assertSame(0, ProfileUpdateRequest::count());
        $this->assertSame(2, Resident::count());
        $this->assertSame('Secretary final', Resident::findOrFail($child->fresh()->approved_resident_id)->first_name);
    }

    public function test_vacant_existing_household_does_not_autodesignate_head_and_rejects_mobile_head_proposal(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        $this->sync($bhw, $this->input(['household_id' => $household->id, 'propose_household_head' => true]))->assertJsonPath('status', 'failed');
        $this->sync($bhw, $this->input(['household_id' => $household->id]))->assertJsonPath('status', 'success');
        $this->approveDraft($secretary, ResidentDraft::firstOrFail())->assertSessionHasNoErrors();
        $this->assertNull($household->fresh()->head_resident_id);
    }

    public function test_new_package_explicit_proposal_revisions_and_secretary_final_head_choice(): void
    {
        [$bhw, $secretary] = $this->team();
        Sanctum::actingAs($bhw, ['mobile']);
        $uuid = '10000000-0000-4000-8000-000000000003';
        $this->postJson('/api/mobile/sync', ['households' => [['mobile_uuid' => $uuid, 'local_revision' => 1,
            'household_no' => 'NEW-100', 'household_address' => 'Pilot address', 'is_active' => true,
            'is_social_aid_beneficiary' => false]]])->assertJsonPath('status', 'success');
        $input = $this->input(['household_mobile_uuid' => $uuid, 'propose_household_head' => true]);
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $child = ResidentDraft::firstOrFail();
        $this->assertTrue($child->is_household_head_candidate);
        $this->sync($bhw, array_replace($input, ['local_revision' => 2, 'propose_household_head' => false]))->assertJsonPath('status', 'success');
        $this->assertFalse($child->fresh()->is_household_head_candidate);
        $this->sync($bhw, array_replace($input, ['local_revision' => 3]))->assertJsonPath('status', 'success');
        $this->assertSame($child->id, ResidentDraft::first()->id);
        $this->actingAs($secretary)->get(route('secretary.drafts.edit', $child->householdDraft))->assertOk()
            ->assertSee('Head of Household');
        $this->approveDraft($secretary, $child->fresh(), ['head_draft_id' => $child->id])->assertSessionHasNoErrors();
        $this->assertSame($child->fresh()->approved_resident_id, $child->householdDraft->fresh()->approvedHousehold->head_resident_id);
    }

    public function test_legacy_head_text_remains_reviewable_but_is_not_a_head_proposal(): void
    {
        [$bhw, , $household] = $this->team();
        $legacy = $this->input(['household_id' => $household->id, 'relationship_to_head' => 'Head']);
        unset($legacy['request_contract_version']);
        $this->sync($bhw, $legacy)->assertJsonPath('status', 'success');
        $this->assertFalse(ResidentDraft::first()->is_household_head_candidate);
        $this->sync($bhw, array_replace($legacy, ['request_contract_version' => 2, 'local_revision' => 2]))->assertJsonPath('status', 'success');
        $this->assertSame('Head', ResidentDraft::first()->relationship_to_head);
    }

    public function test_rejection_is_terminal_and_rejected_parent_cannot_receive_a_new_resident(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        $input = $this->input(['household_id' => $household->id]);
        $this->sync($bhw, $input);
        $child = ResidentDraft::firstOrFail();
        $this->actingAs($secretary)->patch(route('secretary.drafts.reject', $child->householdDraft), ['review_notes' => 'Not confirmed.'])->assertSessionHas('success');
        $this->sync($bhw, $input)->assertJsonPath('resolved_records.residents.0.verification_status', 'rejected');
        $this->sync($bhw, array_replace($input, ['first_name' => 'Revised', 'local_revision' => 2]))
            ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.residents');
        $this->assertSame('Lina', $child->fresh()->first_name);
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('residents.0.verification_notes', 'Not confirmed.');
        $this->assertSame(0, Resident::count());
        $this->assertSame(1, ResidentDraft::count());
        $parent = $child->householdDraft;
        $parent->update(['mobile_uuid' => '10000000-0000-4000-8000-000000000003']);
        $this->sync($bhw, $this->input(['mobile_uuid' => '10000000-0000-4000-8000-000000000004', 'household_mobile_uuid' => $parent->mobile_uuid]))->assertJsonPath('status', 'failed');
    }

    public function test_shared_web_edit_preserves_native_child_identity_and_rejects_lineage_removal(): void
    {
        [$bhw, , $household] = $this->team();
        $this->sync($bhw, $this->input(['household_id' => $household->id]));
        $child = ResidentDraft::firstOrFail();
        $parent = $child->householdDraft;
        $values = $this->input(['draft_id' => $child->id, 'first_name' => 'Updated']);
        $this->actingAs($bhw)->get(route('bhw.drafts.edit', $parent))->assertOk()->assertSee('draft_id');
        $payload = ['purok_id' => $parent->purok_id, 'household_address' => 'Pilot address', 'residents' => [$values]];
        $this->actingAs($bhw)->put(route('bhw.drafts.update', $parent), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, ResidentDraft::count());
        $this->assertSame($values['mobile_uuid'], $child->fresh()->mobile_uuid);
        $this->assertSame('Updated', $child->fresh()->first_name);
        $this->assertSame(2, $child->fresh()->mobile_revision);
        unset($payload['residents'][0]['draft_id']);
        $this->actingAs($bhw)->put(route('bhw.drafts.update', $parent), $payload)->assertSessionHasErrors('residents');
        $this->assertSame(1, ResidentDraft::count());
    }

    public function test_birth_date_boundaries_match_mobile_and_secretary_newborn_approval(): void
    {
        [$bhw, $secretary, $household] = $this->team();
        foreach ([now()->addDay()->toDateString(), '2025-02-29', '2024-02-30'] as $date) {
            $this->sync($bhw, $this->input(['household_id' => $household->id, 'birth_date' => $date]))->assertJsonPath('status', 'failed');
        }
        $this->sync($bhw, $this->input(['household_id' => $household->id, 'birth_date' => now()->toDateString()]))->assertJsonPath('status', 'success');
        $this->approveDraft($secretary, ResidentDraft::first())->assertSessionHasNoErrors();
        $this->assertSame(now()->toDateString(), Resident::first()->birth_date->toDateString());
    }

    public function test_required_fields_email_and_new_request_availability_are_validated_without_official_writes(): void
    {
        [$bhw, , $household] = $this->team();
        foreach ([['first_name' => '  '], ['last_name' => ''], ['birth_place' => ''], ['sex' => ''],
            ['relationship_to_head' => ''], ['email_address' => 'not email'], ['is_active' => false]] as $invalid) {
            $this->sync($bhw, $this->input(['household_id' => $household->id, ...$invalid]))->assertJsonPath('status', 'failed');
        }
        $this->assertSame(0, ResidentDraft::count());
        $this->assertSame(0, Resident::count());
    }

    public function test_bootstrap_head_context_is_validated_and_deleted_households_are_not_selectable(): void
    {
        [$bhw, , $household] = $this->team();
        $head = $this->resident($household);
        $household->update(['head_resident_id' => $head->id]);
        Sanctum::actingAs($bhw, ['mobile']);
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('households.0.current_head_name', $head->formal_name)
            ->assertJsonPath('households.0.is_vacant', false);
        $head->update(['resident_status' => 'deceased', 'date_of_death' => now()->toDateString()]);
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('households.0.current_head_name', null)
            ->assertJsonPath('households.0.is_vacant', true);
        $household->delete();
        $this->getJson('/api/mobile/bootstrap')->assertJsonCount(0, 'households');
        $this->sync($bhw, $this->input(['household_id' => $household->id]))->assertJsonPath('status', 'failed');
        $this->assertSame(0, ResidentDraft::count());
    }

    public function test_shared_web_edit_cannot_claim_another_package_child_identity(): void
    {
        [$bhw, , $household] = $this->team();
        $this->sync($bhw, $this->input(['household_id' => $household->id]));
        $child = ResidentDraft::firstOrFail();
        $secondInput = $this->input(['household_id' => $household->id, 'mobile_uuid' => '10000000-0000-4000-8000-000000000004', 'first_name' => 'Different']);
        $this->sync($bhw, $secondInput)->assertJsonPath('status', 'success');
        $other = ResidentDraft::where('id', '!=', $child->id)->firstOrFail();
        $payload = ['purok_id' => $child->householdDraft->purok_id, 'household_address' => 'Pilot address',
            'residents' => [$this->input(['draft_id' => $other->id])]];
        $this->actingAs($bhw)->put(route('bhw.drafts.update', $child->householdDraft), $payload)->assertForbidden();
        $this->assertSame($secondInput['mobile_uuid'], $other->fresh()->mobile_uuid);
        $this->assertSame('Lina', $child->fresh()->first_name);
    }
}
