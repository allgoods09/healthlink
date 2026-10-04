<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\HouseholdHeadManager;
use App\Support\HouseholdRelationships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HouseholdHeadManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private Household $household;

    private Resident $head;

    private Resident $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        $purok = Purok::factory()->create(['barangay_id' => Barangay::factory()->create()->id]);
        $this->secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $purok->barangay_id]);
        $this->household = $this->home($purok);
        $this->head = $this->person($this->household, 'Pedro', HouseholdRelationships::HEAD);
        $this->candidate = $this->person($this->household, 'Maria', 'Spouse');
        $this->household->update(['head_resident_id' => $this->head->id]);
        $this->actingAs($this->secretary);
    }

    private function home(Purok $purok, string $number = '001'): Household
    {
        return Household::create(['purok_id' => $purok->id, 'household_no' => $number, 'household_address' => 'Pilot street', 'is_active' => true]);
    }

    private function person(Household $household, string $name, string $relationship = 'Child'): Resident
    {
        return Resident::create(['household_id' => $household->id, 'first_name' => $name, 'last_name' => 'Santos',
            'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => $relationship, 'is_active' => true, 'resident_status' => 'active']);
    }

    private function payload(?Resident $resident = null, array $changes = []): array
    {
        return array_replace(($resident ?? $this->candidate)->only(['household_id', 'first_name', 'last_name', 'birth_place',
            'sex', 'civil_status', 'citizenship', 'relationship_to_head', 'resident_status', 'is_active']),
            ['birth_date' => ($resident ?? $this->candidate)->birth_date->toDateString()], $changes);
    }

    private function review(array $payload = [], ?Resident $candidate = null): array
    {
        $candidate ??= $this->candidate;
        $data = $this->payload($candidate, array_replace(['set_as_household_head' => '1'], $payload));
        $response = $this->put(route('secretary.residents.update', $candidate), $data)
            ->assertOk()->assertViewIs('households.head-review');

        return [$data, $response->viewData('token'), $response];
    }

    private function confirm(array $data, string $token, array $relationships, ?Resident $candidate = null)
    {
        return $this->put(route('secretary.residents.update', $candidate ?? $this->candidate), array_replace($data,
            ['head_review_token' => $token, 'head_reviews' => [$this->household->id => ['relationships' => $relationships]]]));
    }

    public function test_secretary_selector_groups_and_head_control_are_explicit(): void
    {
        $response = $this->get(route('secretary.residents.create', ['household_id' => $this->household->id]))->assertOk();
        foreach (HouseholdRelationships::choices() as $choice) {
            $response->assertSee($choice);
        }
        $response->assertSee('Set this resident as the household head')->assertSee('optgroup', false)
            ->assertDontSee('<option value="Head of Household"', false)->assertDontSee('<option value="Household Head"', false);
    }

    public function test_unchanged_legacy_relationship_is_readable_and_editable(): void
    {
        $this->get(route('secretary.residents.edit', $this->candidate))->assertOk()->assertSee('Current value: Spouse');
        $this->put(route('secretary.residents.update', $this->candidate), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Spouse', $this->candidate->fresh()->relationship_to_head);
    }

    public function test_new_ordinary_resident_uses_canonical_relationship_and_is_not_auto_designated(): void
    {
        $empty = $this->home($this->household->purok, '002');
        $this->post(route('secretary.residents.store'), $this->payload(null, ['household_id' => $empty->id,
            'first_name' => 'Lina', 'relationship_to_head' => 'Daughter']))->assertSessionHasNoErrors()->assertRedirect();
        $resident = Resident::where('first_name', 'Lina')->firstOrFail();
        $this->assertSame('Daughter', $resident->relationship_to_head);
        $this->assertNull($empty->fresh()->head_resident_id);
    }

    public function test_changed_legacy_or_arbitrary_value_is_not_a_new_secretary_choice(): void
    {
        foreach (['Parent', 'Invented relationship', 'Head', 'Head of Household'] as $value) {
            $this->put(route('secretary.residents.update', $this->candidate), $this->payload(null, ['relationship_to_head' => $value]))
                ->assertSessionHasErrors('relationship_to_head');
            $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        }
    }

    public function test_current_head_cannot_be_unchecked_or_changed_by_ordinary_relationship_edit(): void
    {
        $this->get(route('secretary.residents.edit', $this->head))->assertOk()->assertSee('This resident remains the household head')
            ->assertDontSee('<option value="Head of Household"', false);
        $this->put(route('secretary.residents.update', $this->head), $this->payload($this->head,
            ['set_as_household_head' => '0', 'relationship_to_head' => 'Daughter']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame(HouseholdRelationships::HEAD, $this->head->fresh()->relationship_to_head);
    }

    public function test_explicit_new_head_in_empty_household_is_atomic_and_forces_system_value(): void
    {
        $empty = $this->home($this->household->purok, '002');
        $this->post(route('secretary.residents.store'), $this->payload(null, ['household_id' => $empty->id,
            'first_name' => 'Lina', 'set_as_household_head' => '1', 'relationship_to_head' => 'tampered']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $resident = Resident::where('first_name', 'Lina')->firstOrFail();
        $this->assertSame($resident->id, $empty->fresh()->head_resident_id);
        $this->assertSame(HouseholdRelationships::HEAD, $resident->relationship_to_head);
    }

    public function test_headless_household_with_members_requires_review_without_inference(): void
    {
        $this->household->update(['head_resident_id' => null]);
        [, , $response] = $this->review();
        $response->assertSee('No designated head')->assertSee($this->head->full_name);
        $this->assertNull($this->household->fresh()->head_resident_id);
    }

    public function test_review_includes_former_head_and_all_non_deleted_other_members(): void
    {
        $child = $this->person($this->household, 'Juan');
        $deleted = $this->person($this->household, 'Deleted');
        $deleted->delete();
        [, , $response] = $this->review();
        $response->assertSee($this->head->full_name)->assertSee($child->full_name)->assertDontSee($deleted->full_name)
            ->assertSee('Current value: Child')->assertDontSee('<option value="Head of Household"', false);
    }

    public function test_cancel_review_creates_no_registry_or_audit_changes(): void
    {
        $before = AuditLog::count();
        $this->review();
        $this->get(route('secretary.residents.edit', $this->candidate))->assertOk();
        $this->assertSame($before, AuditLog::count());
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame('Spouse', $this->candidate->fresh()->relationship_to_head);
    }

    public function test_replacement_updates_all_members_head_fk_and_audit_context(): void
    {
        $child = $this->person($this->household, 'Juan');
        [$data, $token] = $this->review(['relationship_to_head' => 'tampered']);
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner', $child->id => 'Child'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->candidate->id, $this->household->fresh()->head_resident_id);
        $this->assertSame(HouseholdRelationships::HEAD, $this->candidate->fresh()->relationship_to_head);
        $this->assertSame('Spouse / Partner', $this->head->fresh()->relationship_to_head);
        $this->assertSame('Child', $child->fresh()->relationship_to_head);
        $audit = AuditLog::where('model_type', Household::class)->where('model_id', $this->household->id)->latest('id')->firstOrFail();
        $this->assertSame($this->head->id, $audit->metadata['old_head_resident_id']);
        $this->assertSame($this->candidate->id, $audit->metadata['new_head_resident_id']);
        $this->assertSame($this->secretary->id, $audit->user_id);
        $this->assertNotNull($audit->created_at);
        $this->assertCount(3, AuditLog::where('model_type', Resident::class)->where('event_type', 'updated')->pluck('model_id')->unique());
    }

    public function test_incomplete_review_rolls_back_candidate_edits(): void
    {
        $this->person($this->household, 'Juan');
        [$data, $token] = $this->review(['first_name' => 'Changed']);
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])
            ->assertSessionHasErrors('head_reviews')->assertRedirect(route('secretary.residents.edit', $this->candidate));
        $this->assertSame('Maria', $this->candidate->fresh()->first_name);
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_foreign_review_member_and_head_like_review_value_are_rejected(): void
    {
        [$data, $token] = $this->review();
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner', 99999 => 'Child'])->assertSessionHasErrors('head_reviews');
        $this->confirm($data, $token, [$this->head->id => 'Head'])->assertSessionHasErrors('head_reviews');
        $this->confirm($data, $token, ['0'.$this->head->id => 'Spouse / Partner'])->assertSessionHasErrors('head_reviews');
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_member_relationship_change_makes_review_stale(): void
    {
        [$data, $token] = $this->review();
        $this->head->update(['relationship_to_head' => 'Father']);
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])->assertSessionHasErrors('head_reviews');
        $this->assertSame('Father', $this->head->fresh()->relationship_to_head);
    }

    public function test_head_change_makes_review_stale(): void
    {
        [$data, $token] = $this->review();
        $this->household->update(['head_resident_id' => null]);
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])->assertSessionHasErrors('head_reviews');
        $this->assertNull($this->household->fresh()->head_resident_id);
    }

    public function test_new_member_makes_review_stale(): void
    {
        [$data, $token] = $this->review();
        $this->person($this->household, 'New member');
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])->assertSessionHasErrors('head_reviews');
    }

    public function test_removed_member_makes_review_stale(): void
    {
        [$data, $token] = $this->review();
        $this->head->delete();
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])->assertSessionHasErrors('head_reviews');
    }

    public function test_candidate_move_makes_review_stale(): void
    {
        [$data, $token] = $this->review();
        $other = $this->home($this->household->purok, '002');
        $this->candidate->update(['household_id' => $other->id]);
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])->assertSessionHasErrors('head_reviews');
        $this->assertSame($other->id, $this->candidate->fresh()->household_id);
    }

    public function test_review_token_cannot_change_original_fields_or_be_replayed(): void
    {
        [$data, $token] = $this->review();
        $this->confirm(array_replace($data, ['first_name' => 'Injected']), $token, [$this->head->id => 'Spouse / Partner'])
            ->assertSessionHasErrors('head_reviews');
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])->assertSessionHasNoErrors();
        $this->confirm($data, $token, [$this->head->id => 'Spouse / Partner'])->assertSessionHasErrors('head_reviews');
    }

    public function test_new_resident_review_does_not_create_temporary_rows_and_failure_rolls_back(): void
    {
        $data = $this->payload(null, ['first_name' => 'Lina', 'set_as_household_head' => '1']);
        $response = $this->post(route('secretary.residents.store'), $data)->assertOk()->assertViewIs('households.head-review');
        $this->assertSame(2, Resident::count());
        $this->post(route('secretary.residents.store'), array_replace($data, ['head_review_token' => $response->viewData('token'),
            'head_reviews' => [$this->household->id => ['relationships' => [$this->head->id => 'Spouse / Partner']]]]))
            ->assertSessionHasErrors('head_reviews');
        $this->assertSame(2, Resident::count());
        $this->assertDatabaseMissing('residents', ['first_name' => 'Lina']);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_new_resident_confirmed_review_assigns_head_without_duplicate_creation(): void
    {
        $data = $this->payload(null, ['first_name' => 'Lina', 'set_as_household_head' => '1']);
        $response = $this->post(route('secretary.residents.store'), $data)->assertOk();
        $this->post(route('secretary.residents.store'), array_replace($data, ['head_review_token' => $response->viewData('token'),
            'head_reviews' => [$this->household->id => ['relationships' => [$this->head->id => 'Father', $this->candidate->id => 'Spouse']]]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(3, Resident::count());
        $this->assertSame(Resident::where('first_name', 'Lina')->value('id'), $this->household->fresh()->head_resident_id);
    }

    public function test_non_designated_legacy_head_is_not_promoted_and_must_be_resolved_during_review(): void
    {
        $this->candidate->update(['relationship_to_head' => 'Head']);
        $this->get(route('secretary.residents.edit', $this->candidate))->assertOk()
            ->assertSee('Current recorded relationship: Head')->assertSee('not the designated household head');
        $this->put(route('secretary.residents.update', $this->candidate), $this->payload())->assertSessionHasNoErrors();
        $this->assertFalse($this->candidate->fresh()->is_household_head);
        $third = $this->person($this->household, 'Third');
        [$data, $token, $response] = $this->review([], $third);
        $response->assertDontSee('Current value: Head');
        $this->confirm($data, $token, [$this->head->id => 'Father', $this->candidate->id => 'Head'], $third)->assertSessionHasErrors('head_reviews');
    }

    public function test_cross_barangay_head_change_is_forbidden(): void
    {
        $foreign = $this->home(Purok::factory()->create());
        $resident = $this->person($foreign, 'Foreign');
        $this->put(route('secretary.residents.update', $resident), $this->payload($resident, [
            'barangay_id' => $this->household->purok->barangay_id,
            'purok_id' => $this->household->purok_id,
            'household_id' => $this->household->id,
            'set_as_household_head' => '1',
        ]))->assertForbidden();
        $this->assertNull($foreign->fresh()->head_resident_id);
    }

    public function test_candidate_must_belong_to_household_even_for_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $foreign = $this->person($this->home(Purok::factory()->create()), 'Foreign');
        $this->put(route('admin.households.update', $this->household), ['purok_id' => $this->household->purok_id,
            'household_no' => '001', 'household_address' => 'Updated', 'head_resident_id' => $foreign->id])
            ->assertSessionHasErrors('head_resident_id');
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_admin_head_selection_uses_same_review_and_transaction(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['purok_id' => $this->household->purok_id, 'household_no' => '001', 'household_address' => 'Updated', 'head_resident_id' => $this->candidate->id];
        $response = $this->put(route('admin.households.update', $this->household), $data)->assertOk()->assertViewIs('households.head-review');
        $this->put(route('admin.households.update', $this->household), array_replace($data,
            ['head_review_token' => $response->viewData('token'), 'head_reviews' => [$this->household->id => ['relationships' => [$this->head->id => 'Spouse / Partner']]]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->candidate->id, $this->household->fresh()->head_resident_id);
        $this->assertSame('Spouse / Partner', $this->head->fresh()->relationship_to_head);
    }

    public function test_household_editor_cannot_remove_current_head(): void
    {
        $this->put(route('secretary.households.update', $this->household), ['purok_id' => $this->household->purok_id,
            'household_no' => '001', 'household_address' => 'Same', 'head_resident_id' => null])->assertSessionHasErrors('head_resident_id');
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_admin_relationship_text_does_not_designate_a_head(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['Head', 'Head of Household'] as $value) {
            $this->put(route('admin.residents.update', $this->candidate), $this->payload(null, ['relationship_to_head' => $value]))
                ->assertSessionHasErrors('relationship_to_head');
        }
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_resident_correction_head_text_requires_explicit_secretary_resolution(): void
    {
        $request = ProfileUpdateRequest::create(['submitted_by_user_id' => $this->secretary->id, 'barangay_id' => $this->secretary->assigned_barangay_id,
            'subject_type' => 'resident', 'subject_id' => $this->candidate->id, 'current_snapshot' => $this->candidate->toArray(),
            'proposed_changes' => ['relationship_to_head' => 'Head'], 'request_reason' => 'Mobile correction', 'request_status' => 'pending']);
        foreach (['Head', 'Head of Household'] as $value) {
            $this->patch(route('secretary.update-requests.approve', $request), $this->payload(null, ['relationship_to_head' => $value]))
                ->assertSessionHasErrors('relationship_to_head');
        }
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame('pending', $request->fresh()->request_status);
    }

    public function test_transaction_exception_rolls_back_head_and_relationship_audits(): void
    {
        try {
            app(HouseholdHeadManager::class)->locked([$this->household->id], function ($households) {
                app(HouseholdHeadManager::class)->designate($households[$this->household->id], $this->candidate, [$this->head->id => 'Spouse / Partner']);
                throw new \RuntimeException('Injected failure');
            });
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected failure', $exception->getMessage());
        }
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame('Spouse', $this->candidate->fresh()->relationship_to_head);
        $this->assertSame(0, AuditLog::count());
    }

    private function relocation(Resident $resident, Household $target, bool $head = false): array
    {
        return ['target_purok_id' => $target->purok_id, 'destination' => 'existing_household', 'target_household_id' => $target->id,
            'set_as_household_head' => $head ? '1' : '0', 'relationship_to_head' => 'Other Relative'];
    }

    public function test_non_head_relocation_preserves_source_head(): void
    {
        $target = $this->home($this->household->purok, '002');
        $this->patch(route('secretary.residents.relocate.update', $this->candidate), $this->relocation($this->candidate, $target))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertNull($target->fresh()->head_resident_id);
    }

    public function test_departing_head_requires_source_replacement_and_full_review(): void
    {
        $child = $this->person($this->household, 'Juan');
        $target = $this->home($this->household->purok, '002');
        $data = $this->relocation($this->head, $target);
        $response = $this->patch(route('secretary.residents.relocate.update', $this->head), $data)->assertOk()->assertViewIs('households.head-review');
        $this->assertSame($this->household->id, $this->head->fresh()->household_id);
        $this->patch(route('secretary.residents.relocate.update', $this->head), array_replace($data, ['head_review_token' => $response->viewData('token'),
            'head_reviews' => [$this->household->id => ['candidate_id' => $this->candidate->id, 'relationships' => [$child->id => 'Child']]]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->candidate->id, $this->household->fresh()->head_resident_id);
        $this->assertSame($target->id, $this->head->fresh()->household_id);
        $this->assertSame('Other Relative', $this->head->fresh()->relationship_to_head);
    }

    public function test_last_member_relocation_clears_source_without_inventing_successor(): void
    {
        $this->candidate->delete();
        $target = $this->home($this->household->purok, '002');
        $this->patch(route('secretary.residents.relocate.update', $this->head), $this->relocation($this->head, $target, true))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($this->household->fresh()->head_resident_id);
        $this->assertSame($this->head->id, $target->fresh()->head_resident_id);
    }

    public function test_occupied_target_deliberate_replacement_uses_review(): void
    {
        $target = $this->home($this->household->purok, '002');
        $targetHead = $this->person($target, 'Target head', HouseholdRelationships::HEAD);
        $target->update(['head_resident_id' => $targetHead->id]);
        $data = $this->relocation($this->candidate, $target, true);
        $response = $this->patch(route('secretary.residents.relocate.update', $this->candidate), $data)->assertOk();
        $this->patch(route('secretary.residents.relocate.update', $this->candidate), array_replace($data,
            ['head_review_token' => $response->viewData('token'), 'head_reviews' => [$target->id => ['relationships' => [$targetHead->id => 'Father']]]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->candidate->id, $target->fresh()->head_resident_id);
        $this->assertSame('Father', $targetHead->fresh()->relationship_to_head);
    }

    public function test_inactive_head_remains_designated_without_automatic_succession(): void
    {
        $this->put(route('secretary.residents.update', $this->head), $this->payload($this->head, ['is_active' => '0']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($this->head->fresh()->is_active);
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_review_cancel_returns_to_original_get_form_without_trusting_referer(): void
    {
        $data = $this->payload(null, ['first_name' => 'Lina', 'set_as_household_head' => '1']);
        $response = $this->withHeader('referer', 'https://unrelated.example/')->post(route('secretary.residents.store'), $data)->assertOk();
        $this->assertSame(route('secretary.residents.create'), $response->viewData('cancelUrl'));
        $response->assertSee('name="first_name" value="Lina"', false);
        $this->assertSame(2, Resident::count());
    }

    public function test_explicit_resident_correction_head_change_requires_review_before_approval(): void
    {
        $correction = ProfileUpdateRequest::create(['submitted_by_user_id' => $this->secretary->id, 'barangay_id' => $this->secretary->assigned_barangay_id,
            'subject_type' => 'resident', 'subject_id' => $this->candidate->id, 'current_snapshot' => $this->candidate->toArray(),
            'proposed_changes' => ['relationship_to_head' => 'Head'], 'request_reason' => 'Field correction', 'request_status' => 'pending']);
        $data = $this->payload(null, ['set_as_household_head' => '1', 'relationship_to_head' => 'tampered']);
        $response = $this->patch(route('secretary.update-requests.approve', $correction), $data)->assertOk()->assertViewIs('households.head-review');
        $this->assertSame('pending', $correction->fresh()->request_status);
        $this->patch(route('secretary.update-requests.approve', $correction), array_replace($data,
            ['head_review_token' => $response->viewData('token'), 'head_reviews' => [$this->household->id => ['relationships' => [$this->head->id => 'Spouse / Partner']]]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $correction->fresh()->request_status);
        $this->assertSame($this->candidate->id, $this->household->fresh()->head_resident_id);
        $this->assertSame(HouseholdRelationships::HEAD, $this->candidate->fresh()->relationship_to_head);
    }

    public function test_household_correction_uses_same_complete_relationship_review(): void
    {
        $correction = ProfileUpdateRequest::create(['submitted_by_user_id' => $this->secretary->id, 'barangay_id' => $this->secretary->assigned_barangay_id,
            'subject_type' => 'household', 'subject_id' => $this->household->id, 'current_snapshot' => $this->household->toArray(),
            'proposed_changes' => ['head_resident_id' => $this->candidate->id], 'request_reason' => 'Review head', 'request_status' => 'pending']);
        $data = ['purok_id' => $this->household->purok_id, 'household_no' => '001', 'household_address' => 'Pilot street', 'head_resident_id' => $this->candidate->id];
        $response = $this->patch(route('secretary.update-requests.approve', $correction), $data)->assertOk();
        $this->patch(route('secretary.update-requests.approve', $correction), array_replace($data,
            ['head_review_token' => $response->viewData('token'), 'head_reviews' => [$this->household->id => ['relationships' => [$this->head->id => 'Spouse / Partner']]]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $correction->fresh()->request_status);
        $this->assertSame($this->candidate->id, $this->household->fresh()->head_resident_id);
    }

    public function test_resident_editor_moving_head_requires_source_replacement(): void
    {
        $target = $this->home($this->household->purok, '002');
        $data = $this->payload($this->head, ['household_id' => $target->id, 'relationship_to_head' => 'Other Relative']);
        $response = $this->put(route('secretary.residents.update', $this->head), $data)->assertOk();
        $this->put(route('secretary.residents.update', $this->head), array_replace($data,
            ['head_review_token' => $response->viewData('token'), 'head_reviews' => [$this->household->id => ['candidate_id' => $this->candidate->id, 'relationships' => []]]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->candidate->id, $this->household->fresh()->head_resident_id);
        $this->assertSame($target->id, $this->head->fresh()->household_id);
        $this->assertNull($target->fresh()->head_resident_id);
    }

    public function test_authoritative_service_rejects_direct_bhw_head_designation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $this->secretary->assigned_barangay_id]));
        try {
            app(HouseholdHeadManager::class)->designate($this->household, $this->candidate, [$this->head->id => 'Spouse / Partner']);
            $this->fail('BHW must not designate official heads');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_admin_cannot_move_head_wording_as_an_ordinary_destination_relationship(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $target = $this->home($this->household->purok, '002');
        $data = $this->payload($this->head, ['household_id' => $target->id]);
        $response = $this->put(route('admin.residents.update', $this->head), $data)->assertOk();
        $this->put(route('admin.residents.update', $this->head), array_replace($data,
            ['head_review_token' => $response->viewData('token'), 'head_reviews' => [$this->household->id => ['candidate_id' => $this->candidate->id, 'relationships' => []]]]))
            ->assertSessionHasErrors('relationship_to_head');
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame($this->household->id, $this->head->fresh()->household_id);
        $this->assertNull($target->fresh()->head_resident_id);
    }

    public function test_unavailable_active_candidate_can_be_designated_with_current_member_review(): void
    {
        $this->candidate->update(['is_active' => false]);
        $data = ['purok_id' => $this->household->purok_id, 'household_no' => '001',
            'household_address' => 'Pilot street', 'head_resident_id' => $this->candidate->id, 'is_active' => 1];
        $review = $this->put(route('secretary.households.update', $this->household), $data)->assertOk();
        $this->put(route('secretary.households.update', $this->household), $data + [
            'head_review_token' => $review->viewData('token'),
            'head_reviews' => [$this->household->id => ['relationships' => [$this->head->id => 'Father']]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->candidate->id, $this->household->fresh()->currentHeadResident()->id);
        $this->assertFalse($this->candidate->fresh()->is_active);
    }

    public static function noncurrentStates(): array
    {
        return [['deceased', false], ['moved_out', false], ['relocated', false], ['active', true]];
    }

    #[DataProvider('noncurrentStates')]
    public function test_noncurrent_candidate_is_rejected_by_request_and_authoritative_service(string $status, bool $deleted): void
    {
        $this->candidate->update(['resident_status' => $status]);
        if ($deleted) {
            $this->candidate->delete();
        }
        $this->put(route('secretary.households.update', $this->household), ['purok_id' => $this->household->purok_id,
            'household_no' => '001', 'household_address' => 'Unchanged', 'head_resident_id' => $this->candidate->id])
            ->assertSessionHasErrors('head_resident_id');
        try {
            app(HouseholdHeadManager::class)->designate($this->household, $this->candidate, [$this->head->id => 'Father']);
            $this->fail('A non-current candidate cannot become head.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('head_reviews', $exception->errors());
        }
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_review_and_relationship_rewrites_exclude_all_historical_members(): void
    {
        $history = [];
        foreach (['deceased', 'moved_out', 'relocated', 'deleted'] as $status) {
            $member = $this->person($this->household, 'Historical'.$status, 'Other Relative');
            if ($status === 'deleted') {
                $member->delete();
            } else {
                $member->update(['resident_status' => $status]);
            }
            $history[$member->id] = Resident::withTrashed()->findOrFail($member->id)->getAttributes();
        }
        [$data, $token, $response] = $this->review();
        foreach ($history as $id => $attributes) {
            $response->assertDontSee('name="head_reviews['.$this->household->id.'][relationships]['.$id.']"', false);
        }
        $this->confirm($data, $token, [$this->head->id => 'Father'])->assertSessionHasNoErrors()->assertRedirect();
        foreach ($history as $id => $attributes) {
            $this->assertSame($attributes, Resident::withTrashed()->findOrFail($id)->getAttributes());
        }
        $this->assertSame(0, $this->candidate->fresh()->lifecycle_version);
        $this->assertDatabaseCount('resident_lifecycle_events', 0);
    }

    #[DataProvider('noncurrentStates')]
    public function test_historical_member_changes_invalidate_frozen_review_even_when_not_a_candidate(string $status, bool $deleted): void
    {
        $historical = $this->person($this->household, 'Historical', 'Other Relative');
        $historical->update(['resident_status' => $status]);
        if ($deleted) {
            $historical->delete();
        }
        [$data, $token] = $this->review();
        $historical->update(['middle_name' => 'Changed after review']);
        $this->confirm($data, $token, [$this->head->id => 'Father'])->assertSessionHasErrors('head_reviews');
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_ordinary_household_edit_preserves_invalid_historical_head_without_rewriting_relationship(): void
    {
        $this->head->update(['resident_status' => 'deceased', 'relationship_to_head' => 'Father']);
        $this->put(route('secretary.households.update', $this->household), ['purok_id' => $this->household->purok_id,
            'household_no' => '001', 'household_address' => 'Corrected address', 'head_resident_id' => null, 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertNull($this->household->fresh()->currentHeadResident());
        $this->assertSame('Father', $this->head->fresh()->relationship_to_head);
        $this->assertTrue($this->household->fresh()->is_active);
    }

    public function test_departing_last_current_head_leaves_historical_relationships_untouched(): void
    {
        $this->candidate->update(['resident_status' => 'relocated']);
        $target = $this->home($this->household->purok, '002');
        $this->patch(route('secretary.residents.relocate.update', $this->head), $this->relocation($this->head, $target))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($this->household->fresh()->isVacant());
        $this->assertNull($this->household->fresh()->head_resident_id);
        $this->assertSame('Spouse', $this->candidate->fresh()->relationship_to_head);
        $this->assertTrue($this->household->fresh()->is_active);
    }

    public function test_historical_relationship_injection_is_rejected_without_partial_mutations(): void
    {
        $historical = $this->person($this->household, 'Historical', 'Other Relative');
        $historical->update(['resident_status' => 'moved_out']);
        [$data, $token] = $this->review();
        $this->confirm($data, $token, [$this->head->id => 'Father', $historical->id => 'Child'])
            ->assertSessionHasErrors('head_reviews');
        $this->assertSame('Other Relative', $historical->fresh()->relationship_to_head);
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_candidate_lifecycle_change_invalidates_frozen_head_review(): void
    {
        [$data, $token] = $this->review();
        $this->candidate->update(['resident_status' => 'deceased']);
        $this->confirm($data, $token, [$this->head->id => 'Father'])->assertSessionHasErrors('head_reviews');
        $this->assertSame('deceased', $this->candidate->fresh()->resident_status);
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_secretary_correction_approval_cannot_select_a_historical_head(): void
    {
        $this->candidate->update(['resident_status' => 'relocated']);
        $correction = ProfileUpdateRequest::create(['submitted_by_user_id' => $this->secretary->id,
            'barangay_id' => $this->secretary->assigned_barangay_id, 'subject_type' => 'household',
            'subject_id' => $this->household->id, 'current_snapshot' => $this->household->toArray(),
            'proposed_changes' => ['head_resident_id' => $this->candidate->id], 'request_reason' => 'Review head', 'request_status' => 'pending']);
        $this->patch(route('secretary.update-requests.approve', $correction), ['purok_id' => $this->household->purok_id,
            'household_no' => '001', 'household_address' => 'Pilot street', 'head_resident_id' => $this->candidate->id])
            ->assertSessionHasErrors('head_resident_id');
        $this->assertSame('pending', $correction->fresh()->request_status);
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_direct_service_cannot_designate_a_member_of_another_household(): void
    {
        $foreign = $this->person($this->home($this->household->purok, '002'), 'Foreign');
        try {
            app(HouseholdHeadManager::class)->designate($this->household, $foreign, [$this->head->id => 'Father', $this->candidate->id => 'Child']);
            $this->fail('A foreign household member cannot become head.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('head_reviews', $exception->errors());
        }
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
    }

    public function test_ordinary_update_omitting_head_selection_keeps_historical_context(): void
    {
        $this->head->update(['resident_status' => 'moved_out', 'relationship_to_head' => 'Father']);
        $this->put(route('secretary.households.update', $this->household), ['purok_id' => $this->household->purok_id,
            'household_no' => '001', 'household_address' => 'Corrected address', 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->head->id, $this->household->fresh()->head_resident_id);
        $this->assertSame('Father', $this->head->fresh()->relationship_to_head);
    }
}
