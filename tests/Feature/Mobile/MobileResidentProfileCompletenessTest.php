<?php

namespace Tests\Feature\Mobile;

use App\Models\{Barangay, Household, HouseholdDraft, ProfileUpdateRequest, Purok, Resident, ResidentDraft, ResidentSocioEconomicProfile, User};
use App\Support\{MobileResidentRequestData, ResidentProfileData, RbiTemplatePdfGenerator, SecretaryPipelineProcessor};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileResidentProfileCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id, 'approval_status' => 'approved', 'is_active' => true]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $home = Household::create(['purok_id' => $purok->id, 'household_no' => 'COMPLETE-1', 'household_address' => 'Synthetic home', 'is_active' => false]);
        return [$bhw, $secretary, $home];
    }

    private function input(Household $home, array $extra = []): array
    {
        return array_replace(['household_id' => $home->id, 'mobile_uuid' => '00000000-0000-4000-8000-000000000099',
            'request_contract_version' => 2, 'local_revision' => 1, 'first_name' => 'Celine', 'last_name' => 'Pilot',
            'middle_name' => 'Synthetic', 'birth_date' => now()->toDateString(), 'birth_place' => 'Tubigon', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino', 'religion' => 'Recorded religion', 'contact_number' => '09123456789',
            'email_address' => 'synthetic@example.test', 'relationship_to_head' => 'Daughter', 'is_active' => true,
            'philsys_card_no' => 'SYNTHETIC-PHILSYS-99', 'occupation' => 'Recorded profession', 'employment_status' => 'Employed',
            'highest_education_level' => 'College', 'education_status' => 'Undergraduate', 'is_pwd' => true, 'disability_type' => 'Recorded disability',
            'is_ofw' => true, 'is_solo_parent' => true, 'is_osy' => true, 'is_osc' => true, 'is_ip' => true, 'ethnicity' => 'Recorded ethnicity'], $extra);
    }

    private function sync(User $user, array $record)
    {
        Sanctum::actingAs($user, ['mobile']);
        return $this->postJson('/api/mobile/sync', ['residents' => [$record]])->assertOk();
    }

    private function approve(User $secretary, ResidentDraft $draft, array $extra = [])
    {
        $parent = $draft->householdDraft;
        return $this->actingAs($secretary)->patch(route('secretary.drafts.approve', $parent), [
            'purok_id' => $parent->purok_id, 'residents' => [array_replace($draft->only([
                'philsys_card_no', 'first_name', 'last_name', 'middle_name', 'suffix', 'birth_place', 'sex', 'civil_status', 'citizenship',
                'religion', 'contact_number', 'email_address', 'relationship_to_head', ...ResidentProfileData::FIELDS]),
                ['draft_id' => $draft->id, 'birth_date' => $draft->birth_date->toDateString()], $extra)],
        ]);
    }

    public function test_complete_proposal_survives_review_approval_bootstrap_and_existing_rbi_mapping(): void
    {
        [$bhw, $secretary, $home] = $this->fixture();
        $input = $this->input($home);
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $draft = ResidentDraft::firstOrFail();
        foreach (ResidentProfileData::FIELDS as $field) $this->assertSame($input[$field], $draft->{$field});
        $this->assertSame(0, Resident::count());
        $this->assertSame(0, ResidentSocioEconomicProfile::count());
        $this->actingAs($secretary)->get(route('secretary.drafts.show', $draft->householdDraft))->assertOk()
            ->assertSee('Recorded profession')->assertSee('SYNTHETIC-PHILSYS-99')->assertSee('Recorded ethnicity');
        $this->get(route('secretary.drafts.edit', $draft->householdDraft))->assertOk()
            ->assertSee('residents[0][occupation]', false)->assertSee('Recorded disability');
        $this->approve($secretary, $draft, ['occupation' => 'Secretary confirmed profession'])->assertSessionHasNoErrors();
        $resident = $draft->fresh()->approvedResident->load('socioEconomicProfile', 'household.purok');
        $this->assertSame($home->id, $resident->household_id);
        $this->assertSame('Secretary confirmed profession', $resident->socioEconomicProfile->occupation);
        foreach (array_diff(ResidentProfileData::FIELDS, ['occupation']) as $field) $this->assertSame($input[$field], $resident->socioEconomicProfile->{$field});
        $this->sync($bhw, $input)->assertJsonPath('resolved_records.residents.0.verification_status', 'approved');
        $this->assertSame(1, Resident::count());
        $response = $this->getJson('/api/mobile/bootstrap')->assertOk()->assertJsonPath('resident_contract_version', 2)
            ->assertJsonPath('residents.0.occupation', 'Secretary confirmed profession')
            ->assertJsonPath('residents.0.official_snapshot.disability_type', 'Recorded disability')
            ->assertJsonPath('resident_profile_choices', ResidentProfileData::CHOICES);
        foreach (array_diff(ResidentProfileData::FIELDS, ['occupation']) as $field) $response->assertJsonPath('residents.0.'.$field, $input[$field]);
        $this->assertSame($input['philsys_card_no'], $resident->philsys_card_no);
        $this->assertSame('College', $resident->socioEconomicProfile->highest_education_level);
        $this->assertSame('Undergraduate', $resident->socioEconomicProfile->education_status);
        $this->assertSame('Synthetic home', $resident->household->household_address);
        $this->assertStringStartsWith('%PDF', app(RbiTemplatePdfGenerator::class)->generateResidents([$resident]));
    }

    public function test_nullable_text_clears_and_all_profile_flags_use_one_atomic_correction(): void
    {
        [$bhw, $secretary, $home] = $this->fixture();
        $this->sync($bhw, $this->input($home));
        $this->approve($secretary, ResidentDraft::firstOrFail());
        $resident = Resident::firstOrFail();
        $changes = [...array_fill_keys(ResidentProfileData::FLAGS, false), 'disability_type' => null, 'occupation' => null,
            'ethnicity' => null, 'employment_status' => 'Unemployed', 'highest_education_level' => 'Vocational',
            'education_status' => 'Graduate', 'philsys_card_no' => null];
        $input = ['id' => $resident->id, 'household_id' => $home->id, 'mobile_uuid' => $resident->mobile_uuid,
            'local_revision' => 2, 'request_contract_version' => 2, 'base_snapshot' => MobileResidentRequestData::snapshot($resident), 'proposed_changes' => $changes];
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $correction = ProfileUpdateRequest::firstOrFail();
        $this->assertSame($changes, $correction->proposed_changes);
        $this->actingAs($secretary)->get(route('secretary.update-requests.show', $correction))->assertOk()->assertSee('Recorded profession');
        $this->get(route('secretary.update-requests.edit', $correction))->assertOk()->assertSee('name="occupation"', false)->assertSee('name="is_pwd"', false);
        $final = array_replace(MobileResidentRequestData::snapshot($resident), $changes);
        $this->patch(route('secretary.update-requests.approve', $correction), $final)->assertSessionHasNoErrors();
        $resident = $resident->fresh('socioEconomicProfile');
        foreach (ResidentProfileData::FLAGS as $flag) $this->assertFalse($resident->socioEconomicProfile->{$flag});
        foreach (['occupation', 'ethnicity', 'disability_type'] as $field) $this->assertNull($resident->socioEconomicProfile->{$field});
        $this->assertNull($resident->philsys_card_no);
        $this->assertSame('Graduate', $resident->socioEconomicProfile->education_status);
        $this->sync($bhw, $input)->assertJsonPath('resolved_records.residents.0.verification_status', 'approved');
        $this->assertSame(1, ProfileUpdateRequest::count());
    }

    public function test_profile_and_philsys_stale_state_block_submission_and_later_approval(): void
    {
        [$bhw, $secretary, $home] = $this->fixture();
        $this->sync($bhw, $this->input($home)); $this->approve($secretary, ResidentDraft::firstOrFail());
        $resident = Resident::firstOrFail();
        $base = MobileResidentRequestData::snapshot($resident);
        $resident->socioEconomicProfile()->update(['occupation' => 'Driver']);
        $input = ['id' => $resident->id, 'household_id' => $home->id, 'mobile_uuid' => $resident->mobile_uuid,
            'local_revision' => 2, 'request_contract_version' => 2, 'base_snapshot' => $base, 'proposed_changes' => ['education_status' => 'Graduate']];
        $this->sync($bhw, $input)->assertJsonPath('status', 'failed');
        $this->assertSame('Driver', $resident->fresh()->socioEconomicProfile->occupation);
        $input['base_snapshot'] = MobileResidentRequestData::snapshot($resident->fresh());
        $this->sync($bhw, $input)->assertJsonPath('status', 'success');
        $request = ProfileUpdateRequest::firstOrFail();
        $resident->update(['philsys_card_no' => 'CHANGED-AUTHORITATIVELY']);
        $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $request),
            array_replace($request->current_snapshot, $request->proposed_changes))->assertSessionHasErrors('resident');
        $this->assertSame('pending', $request->fresh()->request_status);
        $this->assertSame('CHANGED-AUTHORITATIVELY', $resident->fresh()->philsys_card_no);
        $resident->update(['philsys_card_no' => $base['philsys_card_no']]);
        $resident->socioEconomicProfile()->update(['occupation' => 'Vendor']);
        $this->patch(route('secretary.update-requests.approve', $request),
            array_replace($request->current_snapshot, $request->proposed_changes))->assertSessionHasErrors('resident');
        $this->assertSame('Undergraduate', $resident->fresh()->socioEconomicProfile->education_status);
    }

    public function test_no_profile_is_bootstrapped_as_absent_and_philsys_is_optional_but_unique_on_approval(): void
    {
        [$bhw, $secretary, $home] = $this->fixture();
        $this->sync($bhw, $this->input($home, ['philsys_card_no' => null]));
        $this->approve($secretary, ResidentDraft::firstOrFail())->assertSessionHasNoErrors();
        $resident = Resident::firstOrFail(); $resident->socioEconomicProfile()->delete();
        Sanctum::actingAs($bhw, ['mobile']);
        $response = $this->getJson('/api/mobile/bootstrap')->assertOk();
        foreach (ResidentProfileData::FIELDS as $field) $response->assertJsonPath('residents.0.'.$field, null);
        $resident->update(['philsys_card_no' => 'ALREADY-OFFICIAL']);
        $this->sync($bhw, $this->input($home, ['philsys_card_no' => 'ALREADY-OFFICIAL', 'first_name' => 'Different',
            'mobile_uuid' => '00000000-0000-4000-8000-000000000088']));
        $draft = ResidentDraft::whereNull('approved_resident_id')->firstOrFail();
        $this->approve($secretary, $draft)->assertSessionHasErrors('residents.0.philsys_card_no');
        $this->assertSame(1, Resident::count());
        $this->assertSame(0, ResidentSocioEconomicProfile::count());
    }

    public function test_rejection_and_profile_write_failure_never_leave_partial_official_records(): void
    {
        [$bhw, $secretary, $home] = $this->fixture();
        $this->sync($bhw, $this->input($home)); $draft = ResidentDraft::firstOrFail();
        Event::listen('eloquent.creating: '.ResidentSocioEconomicProfile::class, fn () => throw new \RuntimeException('Test profile failure'));
        try {
            app(SecretaryPipelineProcessor::class)->approveHouseholdDraft($draft->householdDraft, ['purok_id' => $home->purok_id,
                'residents' => [array_replace($this->input($home), ['draft_id' => $draft->id])]], $secretary);
            $this->fail('Expected atomic profile failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test profile failure', $exception->getMessage());
        } finally { Event::forget('eloquent.creating: '.ResidentSocioEconomicProfile::class); }
        $this->assertSame(0, Resident::count()); $this->assertSame(0, ResidentSocioEconomicProfile::count());
        $this->assertSame(HouseholdDraft::STATUS_PENDING, $draft->householdDraft->fresh()->draft_status);
        $this->actingAs($secretary)->patch(route('secretary.drafts.reject', $draft->householdDraft), ['review_notes' => 'Not confirmed'])->assertSessionHasNoErrors();
        $this->assertSame(0, Resident::count()); $this->assertSame(0, ResidentSocioEconomicProfile::count());
    }

    public function test_invalid_profile_choices_and_oversized_text_are_rejected_without_partial_drafts(): void
    {
        [$bhw, , $home] = $this->fixture();
        foreach (['employment_status' => 'Self-employed', 'highest_education_level' => 'Doctorate', 'education_status' => 'Student',
            'occupation' => str_repeat('x', 151), 'ethnicity' => str_repeat('x', 101)] as $field => $value) {
            $this->sync($bhw, $this->input($home, [$field => $value]))->assertJsonPath('status', 'failed');
        }
        $this->assertSame(0, ResidentDraft::count());
    }

    public function test_philsys_can_be_added_changed_and_cannot_duplicate_another_official_resident(): void
    {
        [$bhw, $secretary, $home] = $this->fixture();
        $this->sync($bhw, $this->input($home, ['philsys_card_no' => null]));
        $this->approve($secretary, ResidentDraft::firstOrFail());
        $resident = Resident::firstOrFail();
        foreach (['ADDED-PHILSYS', 'CHANGED-PHILSYS', 'OTHER-OFFICIAL-PHILSYS'] as $index => $value) {
            if ($index === 2) Resident::create(['household_id' => $home->id, 'philsys_card_no' => $value,
                'first_name' => 'Other', 'last_name' => 'Synthetic', 'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon',
                'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Other',
                'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true]);
            $base = MobileResidentRequestData::snapshot($resident->fresh());
            $this->sync($bhw, ['id' => $resident->id, 'household_id' => $home->id, 'mobile_uuid' => $resident->mobile_uuid,
                'request_contract_version' => 2, 'local_revision' => $index + 2, 'base_snapshot' => $base,
                'proposed_changes' => ['philsys_card_no' => $value]])->assertJsonPath('status', 'success');
            $correction = ProfileUpdateRequest::latest('id')->firstOrFail();
            $response = $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $correction),
                array_replace($base, ['philsys_card_no' => $value]));
            if ($index === 2) {
                $response->assertSessionHasErrors('philsys_card_no');
                $this->assertSame('CHANGED-PHILSYS', $resident->fresh()->philsys_card_no);
                $this->assertSame('pending', $correction->fresh()->request_status);
            } else {
                $response->assertSessionHasNoErrors();
                $this->assertSame($value, $resident->fresh()->philsys_card_no);
                $this->assertSame('approved', $correction->fresh()->request_status);
            }
        }
    }

    public function test_failed_profile_correction_rolls_back_resident_changes_and_keeps_request_pending(): void
    {
        [$bhw, $secretary, $home] = $this->fixture();
        $this->sync($bhw, $this->input($home));
        $this->approve($secretary, ResidentDraft::firstOrFail());
        $resident = Resident::firstOrFail();
        $base = MobileResidentRequestData::snapshot($resident);
        $changes = ['philsys_card_no' => 'CORRECTION-ROLLBACK', 'occupation' => 'Vendor'];
        $this->sync($bhw, ['id' => $resident->id, 'household_id' => $home->id, 'mobile_uuid' => $resident->mobile_uuid,
            'request_contract_version' => 2, 'local_revision' => 2, 'base_snapshot' => $base, 'proposed_changes' => $changes]);
        $correction = ProfileUpdateRequest::firstOrFail();
        Event::listen('eloquent.updating: '.ResidentSocioEconomicProfile::class, fn () => throw new \RuntimeException('Test correction profile failure'));
        try {
            app(SecretaryPipelineProcessor::class)->applyProfileUpdateRequest($correction, array_replace($base, $changes), $secretary);
            $this->fail('Expected atomic correction failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test correction profile failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.updating: '.ResidentSocioEconomicProfile::class);
        }
        $this->assertSame($base['philsys_card_no'], $resident->fresh()->philsys_card_no);
        $this->assertSame($base['occupation'], $resident->fresh()->socioEconomicProfile->occupation);
        $this->assertSame('pending', $correction->fresh()->request_status);
        $this->assertNull($correction->fresh()->applied_at);
    }
}
