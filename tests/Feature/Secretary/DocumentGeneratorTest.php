<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\RbiTemplatePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_entire_barangay_review_and_export_for_both_documents(): void
    {
        $f = $this->fixture();
        $this->assertDataset(['document_type' => 'household_rbi'], [$f['households'][0]->id, $f['households'][1]->id]);
        $this->assertDataset(['document_type' => 'resident_rbi'], [$f['residents'][0]->id, $f['residents'][2]->id, $f['residents'][3]->id]);
    }

    public function test_single_and_multiple_puroks_use_or_within_scoped_coverage(): void
    {
        $f = $this->fixture();
        $puroks = array_column($f['puroks'], 'id');
        $this->assertDataset(['document_type' => 'household_rbi', 'coverage' => 'puroks', 'purok_ids' => [$puroks[0]]], [$f['households'][0]->id]);
        $this->assertDataset(['document_type' => 'household_rbi', 'coverage' => 'puroks', 'purok_ids' => array_slice($puroks, 0, 2)], [$f['households'][0]->id, $f['households'][1]->id]);
        $this->assertDataset(['document_type' => 'resident_rbi', 'coverage' => 'puroks', 'purok_ids' => array_slice($puroks, 0, 2)], [$f['residents'][0]->id, $f['residents'][2]->id, $f['residents'][3]->id]);
    }

    public function test_multiple_households_use_or_and_show_purok_context_for_repeating_numbers(): void
    {
        $f = $this->fixture();
        $ids = [$f['households'][0]->id, $f['households'][1]->id];
        $this->assertDataset(['document_type' => 'household_rbi', 'coverage' => 'households', 'household_ids' => $ids], $ids);
        $this->assertDataset(['document_type' => 'resident_rbi', 'coverage' => 'households', 'household_ids' => $ids], [$f['residents'][0]->id, $f['residents'][2]->id, $f['residents'][3]->id]);
        $this->get(route('secretary.documents.index', ['step' => 2, 'document_type' => 'household_rbi']))->assertOk()
            ->assertSee('Household #1 - Purok 1 - Centro')->assertSee('Household #1 - Purok 2 - Ilaya');
    }

    public function test_coverage_and_document_specific_filters_are_anded(): void
    {
        $f = $this->fixture();
        $ids = [$f['households'][0]->id, $f['households'][1]->id];
        $this->assertDataset(['document_type' => 'household_rbi', 'coverage' => 'households', 'household_ids' => $ids, 'social_aid' => 'yes'], [$ids[0]]);
        $this->assertDataset(['document_type' => 'resident_rbi', 'coverage' => 'puroks', 'purok_ids' => [$f['puroks'][0]->id, $f['puroks'][1]->id], 'sex' => 'Female', 'age_min' => 18, 'age_max' => 59, 'resident_status' => 'active'], [$f['residents'][3]->id]);
        $this->assertDataset(['document_type' => 'resident_rbi', 'coverage' => 'households', 'household_ids' => [$ids[0]], 'record_status' => 'inactive', 'resident_status' => 'deceased'], [$f['residents'][1]->id]);
        $this->assertDataset(['document_type' => 'household_rbi', 'record_status' => 'inactive'], [$f['households'][2]->id]);
    }

    public function test_foreign_deleted_and_nonexistent_scope_ids_return_coverage_errors(): void
    {
        $f = $this->fixture();
        foreach (['puroks' => 'purok_ids', 'households' => 'household_ids'] as $mode => $field) {
            $foreignId = $mode === 'puroks' ? $f['foreignHousehold']->purok_id : $f['foreignHousehold']->id;
            foreach ([$foreignId, 999999] as $id) {
                $this->assertError(['coverage' => $mode, $field => [$id]], $field, 2);
            }
        }
        $purok = $f['puroks'][2];
        $purok->delete();
        $this->assertError(['coverage' => 'puroks', 'purok_ids' => [$purok->id]], 'purok_ids', 2);
        $this->assertError(['coverage' => 'households', 'household_ids' => [$f['households'][2]->id]], 'household_ids', 2);
    }

    public function test_selected_coverage_requires_ids_and_age_range_validation_returns_to_filters(): void
    {
        $this->fixture();
        $this->assertError(['coverage' => 'puroks'], 'purok_ids', 2);
        $this->assertError(['coverage' => 'households'], 'household_ids', 2);
        $response = $this->assertError(['age_min' => 60, 'age_max' => 18], 'age_max', 3);
        $response->assertSee('Maximum age must be equal to or greater than minimum age.')
            ->assertSee('value="60"', false)->assertSee('value="18"', false);
        $this->assertError(['age_min' => -1], 'age_min', 3);
        $this->assertError(['age_max' => 151], 'age_max', 3);
    }

    public function test_irrelevant_filters_are_not_validated_used_or_rendered(): void
    {
        $f = $this->fixture();
        $review = $this->review(['document_type' => 'household_rbi', 'sex' => 'invalid', 'age_min' => 100, 'age_max' => 1, 'resident_status' => 'invalid']);
        $this->assertArrayNotHasKey('sex', $review->viewData('selection'));
        $this->assertArrayNotHasKey('age_min', $review->viewData('selection'));
        $this->assertSame(2, $review->viewData('previewCount'));
        $review = $this->review(['document_type' => 'resident_rbi', 'social_aid' => 'invalid']);
        $this->assertArrayNotHasKey('social_aid', $review->viewData('selection'));
        foreach (['household_rbi' => ['sex', 'resident_status', 'age_min', 'age_max'], 'resident_rbi' => ['social_aid']] as $type => $absent) {
            $response = $this->get(route('secretary.documents.index', ['step' => 3, 'document_type' => $type, 'coverage' => 'barangay']))->assertOk();
            foreach ($absent as $name) {
                $response->assertDontSee('name="'.$name.'"', false);
            }
        }
        $this->assertDataset(['document_type' => 'household_rbi', 'sex' => 'Female'], [$f['households'][0]->id, $f['households'][1]->id]);
    }

    public function test_review_signatories_are_nonblocking_and_readiness_does_not_write_registry(): void
    {
        $f = $this->fixture();
        $f['barangay']->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => 'Elena Secretary']);
        $f['barangay']->officials()->where('role_key', 'punong_barangay')->update(['official_name' => 'Pedro Captain']);
        $this->review(['document_type' => 'household_rbi'])->assertSee($f['user']->display_name)->assertSee('Pedro Captain')
            ->assertSee("Household forms include the household's recorded non-deleted members.", false);
        $this->review(['document_type' => 'resident_rbi'])->assertSee($f['user']->display_name)->assertDontSee('Pedro Captain');
        $f['barangay']->officials()->where('role_key', 'barangay_secretary')->delete();
        $count = $f['barangay']->officials()->count();
        $audits = AuditLog::count();
        $review = $this->review(['document_type' => 'resident_rbi'])->assertSee($f['user']->display_name)->assertSee('Generate Locked PDF');
        $this->assertSame($count, $f['barangay']->officials()->count());
        $this->assertSame($audits, AuditLog::count());
        $this->mock(RbiTemplatePdfGenerator::class)->shouldReceive('generateResidents')->once()
            ->withArgs(fn ($records, $context) => $context['barangay_secretary_name'] === $f['user']->display_name)->andReturn('%PDF-test');
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk();
    }

    public function test_zero_review_omits_generate_and_preserves_selection_for_back(): void
    {
        $this->fixture();
        $review = $this->review(['age_min' => 140, 'age_max' => 150])->assertSee('0 residents')->assertSee('No residents match this selection.')
            ->assertDontSee('data-rbi-reviewed', false)->assertDontSee('Generate Locked PDF')->assertSee('Back to Filters');
        $this->assertNull($review->viewData('reviewToken'));
        $this->assertSame('140', $review->viewData('state')['age_min']);
    }

    public function test_review_is_readonly_and_changed_selection_cannot_be_exported_without_new_review(): void
    {
        $this->fixture();
        $review = $this->review(['sex' => 'Female']);
        $review->assertDontSee('name="sex"', false)->assertDontSee('name="coverage"', false)->assertSee('name="review"', false);
        $token = $review->viewData('reviewToken');
        $this->get(route('secretary.documents.index', ['step' => 3, 'document_type' => 'resident_rbi', 'coverage' => 'barangay', 'sex' => 'Female']))
            ->assertOk()->assertDontSee('Generate Locked PDF')->assertDontSee('name="review"', false);
        $this->mock(RbiTemplatePdfGenerator::class)->shouldNotReceive('generateResidents');
        $this->get(route('secretary.documents.export', ['review' => $token, 'sex' => 'Male']))->assertRedirect();
        $this->get(route('secretary.documents.export', ['document_type' => 'resident_rbi']))->assertRedirect(route('secretary.documents.index'));
        $this->get(route('secretary.documents.export', ['review' => 'tampered']))->assertRedirect(route('secretary.documents.index'));
    }

    public function test_final_export_revalidates_scope_and_requeries_changed_records(): void
    {
        $f = $this->fixture();
        $review = $this->review(['coverage' => 'households', 'household_ids' => [$f['households'][0]->id]]);
        $this->assertSame(2, $review->viewData('previewCount'));
        $f['residents'][0]->update(['is_active' => false]);
        $this->mock(RbiTemplatePdfGenerator::class)->shouldReceive('generateResidents')->once()
            ->withArgs(fn ($records) => $records->pluck('id')->all() === [$f['residents'][2]->id])->andReturn('%PDF-test');
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk();
        $f['households'][0]->update(['purok_id' => $f['foreignHousehold']->purok_id, 'household_no' => '2']);
        $this->followingRedirects()->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk()
            ->assertSee('Every selected household must exist in your assigned barangay.');
    }

    public function test_review_is_bound_to_user_and_assignment_and_routes_remain_role_scoped(): void
    {
        $f = $this->fixture();
        $review = $this->review();
        $otherSecretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $this->actingAs($otherSecretary)->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertForbidden();
        $this->actingAs($f['user']);
        $f['user']->update(['assigned_barangay_id' => $f['foreignHousehold']->purok->barangay_id]);
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertForbidden();
        $f['user']->update(['assigned_barangay_id' => null]);
        $this->get(route('secretary.documents.index'))->assertNotFound();
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertNotFound();
        foreach (['bhw', 'bns', 'phn', 'mho'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('secretary.documents.index'))->assertForbidden();
            $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertForbidden();
        }
    }

    public function test_client_barangay_and_irrelevant_coverage_ids_cannot_override_normalized_scope(): void
    {
        $f = $this->fixture();
        $review = $this->review(['barangay_id' => $f['foreignHousehold']->purok->barangay_id,
            'purok_ids' => [$f['foreignHousehold']->purok_id], 'household_ids' => [$f['foreignHousehold']->id]]);
        $this->assertSame(3, $review->viewData('previewCount'));
        $this->assertSame($f['barangay']->id, $review->viewData('barangay')->id);
        $this->assertArrayNotHasKey('purok_ids', $review->viewData('selection'));
        $this->assertArrayNotHasKey('household_ids', $review->viewData('selection'));
        $this->assertError(['coverage' => 'puroks', 'purok_ids' => [$f['puroks'][0]->id, $f['puroks'][0]->id]], 'purok_ids.0', 2);
    }

    public function test_a_previously_nonempty_review_cannot_generate_after_all_matches_disappear(): void
    {
        $f = $this->fixture();
        $review = $this->review(['coverage' => 'households', 'household_ids' => [$f['households'][1]->id]]);
        $f['residents'][3]->delete();
        $this->mock(RbiTemplatePdfGenerator::class)->shouldNotReceive('generateResidents');
        $this->followingRedirects()->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))
            ->assertOk()->assertSee('0 residents')->assertDontSee('Generate Locked PDF');
    }

    public function test_document_and_back_state_are_server_rendered_without_javascript(): void
    {
        $f = $this->fixture();
        $this->get(route('secretary.documents.index'))->assertOk()->assertSee('Choose a document')
            ->assertDontSee('id="coverage"', false)->assertDontSee('id="record_status"', false);
        $this->get(route('secretary.documents.index', ['step' => 1, 'document_type' => 'household_rbi', 'social_aid' => 'yes']))->assertOk()
            ->assertSee('value="household_rbi" selected', false)->assertSee('name="social_aid" value="yes"', false);
        $this->get(route('secretary.documents.index', ['step' => 2, 'document_type' => 'resident_rbi', 'coverage' => 'households', 'household_ids' => [$f['households'][0]->id], 'sex' => 'Female']))->assertOk()
            ->assertSee('name="sex" value="Female"', false)->assertSee('name="step" value="1"', false);
    }

    public function test_form_a_preserves_all_nondeleted_members_and_original_order(): void
    {
        $f = $this->fixture();
        $deleted = $this->resident($f['households'][0], 'Deleted', 'Female', 5);
        $deleted->delete();
        $review = $this->review(['document_type' => 'household_rbi', 'coverage' => 'households', 'household_ids' => [$f['households'][0]->id]]);
        $this->mock(RbiTemplatePdfGenerator::class)->shouldReceive('generateHouseholds')->once()->withArgs(function ($records) use ($f, $deleted) {
            $members = $records->first()->residents->pluck('id')->all();
            $this->assertEqualsCanonicalizing(array_map(fn ($r) => $r->id, array_slice($f['residents'], 0, 3)), $members);
            $this->assertNotContains($deleted->id, $members);

            return true;
        })->andReturn('%PDF-test');
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk();
    }

    public function test_real_locked_generator_still_returns_pdf_for_both_types_and_admin_keeps_old_contract(): void
    {
        $f = $this->fixture();
        foreach (['household_rbi', 'resident_rbi'] as $type) {
            $review = $this->review(['document_type' => $type]);
            $response = $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.documents.index', ['barangay_id' => $f['barangay']->id]))->assertOk()
            ->assertSee('Attestation Settings')->assertDontSee('data-rbi-step', false);
        foreach (['household_rbi', 'resident_rbi'] as $type) {
            $this->get(route('admin.documents.export', ['barangay_id' => $f['barangay']->id, 'document_type' => $type]))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    private function review(array $selection = [])
    {
        return $this->get(route('secretary.documents.index', $selection + ['step' => 4, 'document_type' => 'resident_rbi', 'coverage' => 'barangay']))->assertOk();
    }

    private function assertDataset(array $selection, array $expected): void
    {
        $review = $this->review($selection);
        $isHousehold = $selection['document_type'] === 'household_rbi';
        $this->assertSame(count($expected), $review->viewData('previewCount'));
        $review->assertSee(count($expected).' '.($isHousehold ? 'households' : 'residents'));
        $this->mock(RbiTemplatePdfGenerator::class)->shouldReceive($isHousehold ? 'generateHouseholds' : 'generateResidents')->once()
            ->withArgs(function ($records) use ($expected) {
                $this->assertEqualsCanonicalizing($expected, $records->pluck('id')->all());

                return true;
            })->andReturn('%PDF-test');
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk();
    }

    private function assertError(array $selection, string $field, int $step)
    {
        $response = $this->followingRedirects()->get(route('secretary.documents.index', $selection + ['step' => 4, 'document_type' => 'resident_rbi', 'coverage' => 'barangay']))->assertOk();
        $this->assertSame($step, $response->viewData('step'));
        $this->assertTrue($response->viewData('errors')->has($field));

        return $response;
    }

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create();
        $user = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $this->actingAs($user);
        $puroks = $households = [];
        foreach (['Centro', 'Ilaya', 'Luyo'] as $index => $name) {
            $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => $index + 1, 'purok_name' => $name]);
            $puroks[] = $purok;
            $households[] = Household::create(['purok_id' => $purok->id, 'household_no' => '1', 'household_address' => 'Zone '.$name, 'is_active' => $index < 2, 'is_social_aid_beneficiary' => $index !== 1]);
        }
        $residents = [
            $this->resident($households[0], 'Adult', 'Male', 30),
            $this->resident($households[0], 'Inactive', 'Female', 20, false, 'deceased'),
            $this->resident($households[0], 'Child', 'Female', 10),
            $this->resident($households[1], 'Woman', 'Female', 40),
            $this->resident($households[2], 'Senior', 'Male', 70, false),
        ];
        $foreignBarangay = Barangay::factory()->create();
        $foreignPurok = Purok::factory()->create(['barangay_id' => $foreignBarangay->id, 'purok_number' => 1]);
        $foreignHousehold = Household::create(['purok_id' => $foreignPurok->id, 'household_no' => '1', 'household_address' => 'Foreign zone', 'is_active' => true]);
        $this->resident($foreignHousehold, 'Foreign', 'Female', 30);

        return compact('user', 'barangay', 'puroks', 'households', 'residents', 'foreignHousehold');
    }

    private function resident(Household $household, string $firstName, string $sex, int $age, bool $active = true, string $lifecycle = 'active'): Resident
    {
        return Resident::create([
            'household_id' => $household->id, 'first_name' => $firstName, 'last_name' => 'Santos',
            'birth_date' => now()->subYears($age)->format('Y-m-d'), 'birth_place' => 'Tubigon, Bohol', 'sex' => $sex,
            'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Child',
            'is_active' => $active, 'resident_status' => $lifecycle,
        ]);
    }
}
