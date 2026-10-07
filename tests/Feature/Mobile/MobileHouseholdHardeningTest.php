<?php

namespace Tests\Feature\Mobile;

use App\Models\{Barangay, FieldVisit, Household, HouseholdDraft, ProfileUpdateRequest, Purok, Resident, User};
use App\Support\{MobileHouseholdRequestData, SecretaryPipelineProcessor};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileHouseholdHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1]);
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id, 'approval_status' => 'approved', 'is_active' => true]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $household = Household::create(['purok_id' => $purok->id, 'mobile_uuid' => (string) Str::uuid(),
            'household_no' => '001-A', 'household_address' => 'Original address', 'is_active' => true,
            'is_social_aid_beneficiary' => false]);
        Sanctum::actingAs($bhw, ['mobile']);
        return [$bhw, $secretary, $household];
    }

    private function correction(Household $household, array $changes, int $revision = 1): array
    {
        return ['id' => $household->id, 'mobile_uuid' => $household->mobile_uuid, 'local_revision' => $revision,
            'request_contract_version' => 1, 'base_snapshot' => MobileHouseholdRequestData::snapshot($household),
            'proposed_changes' => $changes];
    }

    public function test_only_changed_fields_apply_and_secretary_final_edits_preserve_unrelated_values(): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $input = $this->correction($household, ['is_social_aid_beneficiary' => true]);
        $household->update(['household_no' => 'Secretary-number']);
        $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'success');
        $request = ProfileUpdateRequest::firstOrFail();
        $this->assertSame(['is_social_aid_beneficiary' => true], $request->proposed_changes);
        $this->assertFalse($household->fresh()->is_social_aid_beneficiary);
        $household->update(['household_address' => 'Secretary-address']);
        $this->actingAs($secretary)->patch(route('secretary.update-requests.approve', $request), [
            'purok_id' => $household->purok_id, 'household_no' => '001-A', 'household_address' => 'Original address',
            'is_social_aid_beneficiary' => '0', 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $household->refresh();
        $this->assertSame('Secretary-number', $household->household_no);
        $this->assertSame('Secretary-address', $household->household_address);
        $this->assertTrue($household->is_active);
        $this->assertFalse($household->is_social_aid_beneficiary);
        $this->assertSame(['is_social_aid_beneficiary' => '0'], $request->fresh()->proposed_changes);
    }

    public function test_changed_field_staleness_is_checked_at_upload_and_approval(): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $input = $this->correction($household, ['household_address' => 'Proposal']);
        $household->update(['household_address' => 'Different']);
        $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'failed');
        $this->assertDatabaseCount('profile_update_requests', 0);
        $input = $this->correction($household, ['household_address' => 'Proposal']);
        $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'success');
        $request = ProfileUpdateRequest::firstOrFail();
        $household->update(['household_address' => 'Changed after submission']);
        try {
            app(SecretaryPipelineProcessor::class)->applyProfileUpdateRequest($request, ['household_address' => 'Final'], $secretary);
            $this->fail('A changed intended field must conflict.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('official household changed', $exception->getMessage());
        }
        $this->assertSame('Changed after submission', $household->fresh()->household_address);
        $this->assertSame('pending', $request->fresh()->request_status);
    }

    public function test_false_social_aid_delta_idempotency_and_subject_lock_exclusion(): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $household->update(['is_social_aid_beneficiary' => true]);
        $input = $this->correction($household, ['is_social_aid_beneficiary' => false]);
        foreach ([1, 2] as $retry) $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('profile_update_requests', 1);
        $this->assertSame(['is_social_aid_beneficiary' => false], ProfileUpdateRequest::first()->proposed_changes);
        $this->postJson('/api/mobile/sync', ['households' => [$this->correction($household, ['household_address' => 'Competing'], 2)]])
            ->assertJsonPath('status', 'failed');
        app(SecretaryPipelineProcessor::class)->applyProfileUpdateRequest(ProfileUpdateRequest::first(), ['is_social_aid_beneficiary' => false], $secretary);
        $this->assertFalse($household->fresh()->is_social_aid_beneficiary);
    }

    #[DataProvider('invalidCorrections')]
    public function test_invalid_and_legacy_corrections_are_not_acknowledged(array $changes, array $extra = []): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $input = array_replace($this->correction($household, $changes), $extra);
        $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'failed')
            ->assertJsonCount(0, 'resolved_records.households');
        $this->assertDatabaseCount('profile_update_requests', 0);
        $this->assertSame('Original address', $household->fresh()->household_address);
    }

    public static function invalidCorrections(): array
    {
        return [
            'null address' => [['household_address' => null]],
            'empty address' => [['household_address' => '']],
            'unknown field' => [['head_resident_id' => 1]],
            'availability delta' => [['is_active' => false]],
            'unknown top field' => [['household_address' => 'Valid'], ['secret' => 'untrusted']],
            'missing base' => [['household_address' => 'Valid'], ['base_snapshot' => null]],
            'legacy contract' => [['household_address' => 'Valid'], ['request_contract_version' => null]],
        ];
    }

    public function test_legacy_full_field_proposal_is_preserved_by_failure_and_foreign_or_deleted_subjects_fail(): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $this->postJson('/api/mobile/sync', ['households' => [['id' => $household->id, 'mobile_uuid' => $household->mobile_uuid,
            'household_no' => 'Old', 'household_address' => 'Legacy proposal', 'is_active' => false]]])
            ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.households');
        $input = $this->correction($household, ['household_address' => 'Proposal']);
        $other = Purok::factory()->create(['barangay_id' => $bhw->assigned_barangay_id, 'purok_number' => 2]);
        $household->update(['purok_id' => $other->id]);
        $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'failed');
        $household->delete();
        $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'failed');
    }

    public function test_persisted_legacy_correction_is_preserved_without_acknowledgment_or_approval(): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $legacy = ProfileUpdateRequest::create([
            'mobile_submission_key' => "household:{$household->mobile_uuid}:1",
            'submitted_by_user_id' => $bhw->id, 'barangay_id' => $bhw->assigned_barangay_id,
            'subject_type' => 'household', 'subject_id' => $household->id,
            'current_snapshot' => $household->toArray(),
            'proposed_changes' => ['household_address' => 'Retained legacy address', 'is_active' => false],
            'request_reason' => 'Older mobile proposal', 'request_status' => 'pending',
        ]);
        $before = $legacy->fresh()->toArray();
        $this->postJson('/api/mobile/sync', ['households' => [[
            'id' => $household->id, 'mobile_uuid' => $household->mobile_uuid, 'local_revision' => 1,
            'household_address' => 'Retained legacy address', 'is_active' => false,
        ]]])->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.households');
        try {
            app(SecretaryPipelineProcessor::class)->applyProfileUpdateRequest($legacy, ['household_address' => 'Retained legacy address'], $secretary);
            $this->fail('Legacy correction without a genuine baseline must not apply.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('legacy mobile correction', $exception->getMessage());
        }
        $this->assertSame($before, $legacy->fresh()->toArray());
        $this->assertSame('Original address', $household->fresh()->household_address);
    }

    #[DataProvider('reviewOutcomes')]
    public function test_reviewed_new_request_replays_exact_outcome_but_not_a_newer_revision(string $status): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $input = ['mobile_uuid' => (string) Str::uuid(), 'local_revision' => 1, 'household_no' => 'NEW-2',
            'household_address' => 'New address', 'is_social_aid_beneficiary' => false, 'is_active' => true];
        $this->postJson('/api/mobile/sync', ['households' => [$input]])->assertJsonPath('status', 'success');
        $draft = HouseholdDraft::firstOrFail();
        $this->postJson('/api/mobile/sync', ['households' => [array_replace($input, ['local_revision' => 2, 'household_address' => 'Revision two'])]])
            ->assertJsonPath('status', 'success');
        $draft->refresh();
        $this->assertSame(2, $draft->mobile_revision);
        if ($status === 'approved') {
            app(SecretaryPipelineProcessor::class)->approveHouseholdDraft($draft, ['purok_id' => $draft->purok_id,
                'household_no' => 'NEW-2', 'household_address' => 'Secretary final'], $secretary);
        } else {
            $this->actingAs($secretary)->patch(route('secretary.drafts.reject', $draft), ['review_notes' => 'Wrong number'])->assertSessionHasNoErrors();
            Sanctum::actingAs($bhw, ['mobile']);
        }
        $retry = array_replace($input, ['local_revision' => 2]);
        $this->postJson('/api/mobile/sync', ['households' => [$retry]])->assertJsonPath('resolved_records.households.0.verification_status', $status);
        $this->postJson('/api/mobile/sync', ['households' => [array_replace($retry, ['local_revision' => 3])]])
            ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.households');
        $this->assertDatabaseCount('household_drafts', 1);
        $this->assertDatabaseCount('profile_update_requests', 0);
        $this->assertDatabaseCount('households', $status === 'approved' ? 2 : 1);
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('household_request_outcomes.0.local_revision', 2)
            ->assertJsonPath('household_request_outcomes.0.verification_status', $status);
    }

    public static function reviewOutcomes(): array { return [['approved'], ['rejected']]; }

    #[DataProvider('visitReferences')]
    public function test_visit_identity_references_and_original_recorder(string $reference, bool $valid): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $household->update(['is_active' => false]); // Availability and vacancy are not Visit exclusions.
        $other = Household::create(['purok_id' => $household->purok_id, 'mobile_uuid' => (string) Str::uuid(),
            'household_no' => '2', 'household_address' => 'Other']);
        $refs = match ($reference) {
            'id' => ['household_id' => $household->id],
            'uuid' => ['household_mobile_uuid' => $household->mobile_uuid],
            'both' => ['household_id' => $household->id, 'household_mobile_uuid' => $household->mobile_uuid],
            'conflict' => ['household_id' => $household->id, 'household_mobile_uuid' => $other->mobile_uuid],
            'missing id' => ['household_id' => 999999, 'household_mobile_uuid' => $other->mobile_uuid],
            'zero id' => ['household_id' => 0, 'household_mobile_uuid' => $household->mobile_uuid],
            'negative id' => ['household_id' => -1, 'household_mobile_uuid' => $household->mobile_uuid],
            'null' => ['household_mobile_uuid' => null],
            'empty' => ['household_mobile_uuid' => ''],
        };
        $input = [...$refs, 'mobile_uuid' => (string) Str::uuid(), 'visited_at' => '2026-10-01', 'notes' => 'Observation'];
        $this->postJson('/api/mobile/sync', ['field_visits' => [$input]])->assertJsonPath('status', $valid ? 'success' : 'failed');
        $this->assertDatabaseCount('field_visits', $valid ? 1 : 0);
        if (! $valid) return;
        $visit = FieldVisit::firstOrFail();
        foreach ([0, -1, 999999] as $invalidVisitId) {
            $this->postJson('/api/mobile/sync', ['field_visits' => [array_replace($input, ['id' => $invalidVisitId, 'notes' => 'Must not apply'])]])
                ->assertJsonPath('status', 'failed');
            $this->assertSame('Observation', $visit->fresh()->notes);
        }
        $editor = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $bhw->assigned_barangay_id,
            'assigned_purok_id' => $bhw->assigned_purok_id, 'approval_status' => 'approved', 'is_active' => true]);
        Sanctum::actingAs($editor, ['mobile']);
        $this->postJson('/api/mobile/sync', ['field_visits' => [array_replace($input, ['id' => $visit->id, 'notes' => 'Edited'])]])
            ->assertJsonPath('status', 'success');
        $this->assertSame($bhw->id, $visit->fresh()->recorded_by_user_id);
        $this->getJson('/api/mobile/bootstrap')->assertJsonPath('field_visits.0.recorded_by_user_id', $bhw->id);
    }

    public static function visitReferences(): array
    {
        return [['id', true], ['uuid', true], ['both', true], ['conflict', false], ['missing id', false], ['zero id', false], ['negative id', false], ['null', false], ['empty', false]];
    }

    public function test_bootstrap_suppresses_other_purok_details_and_preserves_canonical_membership(): void
    {
        [$bhw, $secretary, $household] = $this->context();
        $otherPurok = Purok::factory()->create(['barangay_id' => $bhw->assigned_barangay_id, 'purok_number' => 2]);
        $other = Household::create(['purok_id' => $otherPurok->id, 'household_no' => 'Foreign scope',
            'household_address' => 'Lookup address', 'is_social_aid_beneficiary' => true]);
        foreach (['active', 'deceased', 'moved_out', 'relocated', 'deleted'] as $i => $status) {
            $resident = Resident::create(['household_id' => $household->id, 'first_name' => 'Synthetic', 'last_name' => "Person$i",
                'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Male', 'civil_status' => 'Single',
                'citizenship' => 'Filipino', 'relationship_to_head' => 'Son', 'is_active' => false,
                'resident_status' => $status === 'deleted' ? 'active' : $status]);
            if ($status === 'deleted') $resident->delete();
        }
        foreach ([$household, $other] as $h) FieldVisit::create(['mobile_uuid' => (string) Str::uuid(), 'household_id' => $h->id, 'recorded_by_user_id' => $bhw->id,
            'visited_at' => now(), 'notes' => 'Private', 'photos' => [['path' => 'private-photo']]]);
        $payload = $this->getJson('/api/mobile/bootstrap')->assertOk()->assertJsonPath('household_contract_version', 1)->json();
        $home = collect($payload['households'])->firstWhere('id', $household->id);
        $lookup = collect($payload['households'])->firstWhere('id', $other->id);
        $this->assertSame(1, $home['current_member_count']);
        $this->assertFalse($home['is_vacant']);
        $this->assertNull($home['current_head_name']);
        $this->assertSame(5, $household->residents()->withTrashed()->count());
        foreach (['is_social_aid_beneficiary', 'current_head_name', 'current_member_count', 'resident_count', 'base_snapshot'] as $field) {
            $this->assertArrayNotHasKey($field, $lookup);
        }
        $this->assertSame('undisclosed', $lookup['member_coverage']);
        $this->assertCount(1, $payload['field_visits']);
    }
}
