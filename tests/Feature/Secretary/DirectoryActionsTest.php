<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_directories_have_only_identically_styled_compact_view_actions(): void
    {
        [$secretary, $records] = $this->fixture();
        $this->actingAs($secretary);
        $classes = [];

        foreach ($records as $dataset => $record) {
            $response = $this->get(route("secretary.$dataset.index"))->assertOk();
            $xpath = $this->xpath($response->getContent());
            $cells = $xpath->query('//td[contains(@class,"table-actions-cell")]');
            $this->assertCount(1, $cells);
            $cell = $cells->item(0);
            $this->assertSame(1, $xpath->query('.//a | .//button | .//form', $cell)->length);
            $view = $xpath->query('.//a', $cell)->item(0);
            $this->assertSame('View', trim($view->textContent));
            $this->assertSame(route("secretary.$dataset.show", $record), $view->getAttribute('href'));
            $this->assertSame('view', $view->getAttribute('data-record-action'));
            $this->assertSame('compact', $view->getAttribute('data-record-action-size'));
            $classes[] = $view->getAttribute('class');
        }

        $this->assertCount(1, array_unique($classes));
    }

    public function test_details_retain_record_actions_and_identical_edit_geometry(): void
    {
        [$secretary, $records] = $this->fixture();
        $this->actingAs($secretary);
        $classes = [];

        foreach (['residents', 'households', 'puroks'] as $dataset) {
            $response = $this->get(route("secretary.$dataset.show", $records[$dataset]))->assertOk();
            $xpath = $this->xpath($response->getContent());
            $edit = $xpath->query('//a[@data-record-action="edit"]')->item(0);
            $this->assertSame(route("secretary.$dataset.edit", $records[$dataset]), $edit->getAttribute('href'));
            $classes[] = $edit->getAttribute('class');
            $back = $xpath->query('//a[@data-record-action="back"]')->item(0);
            $this->assertSame(route("secretary.$dataset.index"), $back->getAttribute('href'));
            $this->assertStringNotContainsString('rounded-full', $edit->getAttribute('class'));
        }
        $this->assertCount(1, array_unique($classes));

        $this->get(route('secretary.residents.show', $records['residents']))
            ->assertSee('Relocate')->assertSee('View Household')->assertSee('Download RBI PDF')->assertSee('Open Print View');
        $this->get(route('secretary.households.show', $records['households']))
            ->assertSee('Add Resident')->assertSee('Download RBI PDF')->assertSee('Open Print View');

        $userDetails = $this->get(route('secretary.team.show', $records['team']))->assertOk();
        $xpath = $this->xpath($userDetails->getContent());
        $manage = $xpath->query('//a[@data-record-action="manage"]')->item(0);
        $this->assertSame($classes[0], $manage->getAttribute('class'));
        $this->assertSame(route('secretary.team.edit', $records['team']), $manage->getAttribute('href'));
        $this->assertSame('Manage Assignment', trim($manage->textContent));
        $security = $xpath->query('//a[@data-record-action="security"]')->item(0);
        $this->assertSame(route('secretary.team.password.edit', $records['team']), $security->getAttribute('href'));
        $this->assertSame('Reset Password', trim($security->textContent));
    }

    public function test_moved_status_forms_keep_routes_csrf_patch_and_active_inactive_labels(): void
    {
        [$secretary, $records] = $this->fixture();
        $this->actingAs($secretary);

        foreach (['residents', 'households', 'puroks'] as $dataset) {
            $record = $records[$dataset];
            foreach ([true, false] as $active) {
                $record->update(['is_active' => $active]);
                $response = $this->get(route("secretary.$dataset.show", $record))->assertOk();
                $xpath = $this->xpath($response->getContent());
                $form = $xpath->query('//form[@action="'.route("secretary.$dataset.toggle-status", $record).'"]')->item(0);
                $this->assertNotNull($form);
                $this->assertSame('POST', $form->getAttribute('method'));
                $this->assertSame('PATCH', $xpath->evaluate('string(.//input[@name="_method"]/@value)', $form));
                $this->assertNotEmpty($xpath->evaluate('string(.//input[@name="_token"]/@value)', $form));
                $button = $xpath->query('.//button', $form)->item(0);
                $this->assertSame($active ? 'Deactivate' : 'Activate', trim($button->textContent));
                $this->assertSame($active ? 'deactivate' : 'activate', $button->getAttribute('data-record-action'));
                $this->assertSame('submit', $button->getAttribute('type'));
            }
        }
    }

    public function test_household_and_purok_status_changes_keep_audit_redirect_and_feedback(): void
    {
        [$secretary, $records] = $this->fixture();
        $this->actingAs($secretary);

        foreach (['households', 'puroks'] as $dataset) {
            $record = $records[$dataset];
            $details = route("secretary.$dataset.show", $record);
            $label = $dataset === 'households' ? "Household #{$record->household_no}" : "Purok {$record->display_name}";
            foreach ([false, true] as $newStatus) {
                $this->from($details)->patch(route("secretary.$dataset.toggle-status", $record))
                    ->assertRedirect($details)
                    ->assertSessionHas('success', $label.' has been '.($newStatus ? 'activated' : 'marked inactive').'.');
                $this->assertSame($newStatus, $record->fresh()->is_active);
                $audit = AuditLog::query()->where('event_type', 'status_toggled')
                    ->where('model_type', $record::class)->where('model_id', $record->id)->latest('id')->firstOrFail();
                $this->assertSame($secretary->id, $audit->user_id);
                $this->assertSame(['is_active' => !$newStatus], $audit->old_values);
                $this->assertSame(['is_active' => $newStatus], $audit->new_values);
            }
        }
    }

    public function test_foreign_scope_and_bhw_still_cannot_toggle_households_or_puroks(): void
    {
        [$secretary, $records] = $this->fixture();
        [, $foreign] = $this->fixture();
        foreach (['households', 'puroks'] as $dataset) {
            $this->actingAs($secretary)->patch(route("secretary.$dataset.toggle-status", $foreign[$dataset]))->assertForbidden();
            $this->assertTrue($foreign[$dataset]->fresh()->is_active);
            $this->actingAs($records['team'])->patch(route("secretary.$dataset.toggle-status", $records[$dataset]))->assertForbidden();
            $this->assertTrue($records[$dataset]->fresh()->is_active);
        }
    }

    public function test_deleted_records_do_not_gain_details_or_status_actions(): void
    {
        [$secretary, $records] = $this->fixture();
        $this->actingAs($secretary);
        foreach (['residents', 'households', 'puroks'] as $dataset) {
            $record = $records[$dataset];
            $record->delete();
            $this->get(route("secretary.$dataset.show", $record))->assertNotFound();
            $this->patch(route("secretary.$dataset.toggle-status", $record))->assertNotFound();
        }
    }

    public function test_pending_user_directory_is_view_only_and_review_remains_on_manage_page(): void
    {
        [$secretary, $records] = $this->fixture();
        $pending = $records['team'];
        $pending->update(['approval_status' => User::APPROVAL_PENDING]);
        $response = $this->actingAs($secretary)->get(route('secretary.team.index'))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $cell = $xpath->query('//td[contains(@class,"table-actions-cell")]')->item(0);
        $this->assertSame(1, $xpath->query('.//a | .//button | .//form', $cell)->length);
        $this->get(route('secretary.team.show', $pending))->assertOk()
            ->assertSee('href="'.route('secretary.team.edit', $pending).'"', false);
        $this->get(route('secretary.team.edit', $pending))->assertOk()
            ->assertSee('action="'.route('secretary.team.approve', $pending).'"', false)
            ->assertSee('action="'.route('secretary.team.reject', $pending).'"', false);
    }

    public function test_admin_shared_pages_keep_their_existing_actions_and_styles(): void
    {
        [, $records] = $this->fixture();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['households', 'puroks', 'residents'] as $dataset) {
            $record = $records[$dataset];
            $this->get(route("admin.$dataset.index"))->assertOk()
                ->assertSee('href="'.route("admin.$dataset.edit", $record).'"', false)
                ->assertSee('action="'.route("admin.$dataset.toggle-status", $record).'"', false)
                ->assertDontSee('data-record-action=', false);
            $this->get(route("admin.$dataset.show", $record))->assertOk()->assertDontSee('data-record-action=', false);
        }
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1, 'is_active' => true]);
        $household = Household::query()->create([
            'purok_id' => $purok->id, 'household_no' => '001', 'household_address' => 'Zone 1',
            'is_active' => true, 'is_social_aid_beneficiary' => false,
        ]);
        $resident = Resident::query()->create([
            'household_id' => $household->id, 'first_name' => 'Juana', 'last_name' => 'Dela Cruz',
            'birth_date' => '1994-03-12', 'birth_place' => 'Tubigon, Bohol', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Daughter',
            'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true,
        ]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => null]);
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => $purok->id]);

        return [$secretary, ['residents' => $resident, 'households' => $household, 'puroks' => $purok, 'team' => $bhw]];
    }
}
