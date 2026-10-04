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

class ResidentActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_index_has_only_view_row_actions(): void
    {
        [$secretary, $resident] = $this->fixture();

        $this->actingAs($secretary)->get(route('secretary.residents.index'))
            ->assertOk()
            ->assertSee('href="'.route('secretary.residents.show', $resident).'"', false)
            ->assertDontSee('href="'.route('secretary.residents.edit', $resident).'"', false)
            ->assertDontSee('href="'.route('secretary.residents.relocate.edit', $resident).'"', false)
            ->assertDontSee('action="'.route('secretary.residents.toggle-status', $resident).'"', false);
    }

    public function test_details_retains_management_links_and_the_existing_status_form(): void
    {
        [$secretary, $resident] = $this->fixture();

        $response = $this->actingAs($secretary)->get(route('secretary.residents.show', $resident))
            ->assertOk()
            ->assertSee('href="'.route('secretary.residents.edit', $resident).'"', false)
            ->assertSee('href="'.route('secretary.residents.relocate.edit', $resident).'"', false)
            ->assertSee('href="'.route('secretary.households.show', $resident->household).'"', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $form = $xpath->query('//form[@action="'.route('secretary.residents.toggle-status', $resident).'"]')->item(0);
        $this->assertNotNull($form);
        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertSame('PATCH', $xpath->evaluate('string(.//input[@name="_method"]/@value)', $form));
        $this->assertNotEmpty($xpath->evaluate('string(.//input[@name="_token"]/@value)', $form));
        // The global confirmation handler uses this label to select the existing warning.
        $this->assertSame('Deactivate', trim($xpath->evaluate('string(.//button[@type="submit"])', $form)));
    }

    public function test_inactive_resident_retains_activate_instead_of_deactivate(): void
    {
        [$secretary, $resident] = $this->fixture(['is_active' => false]);

        $this->actingAs($secretary)->get(route('secretary.residents.show', $resident))
            ->assertOk()->assertSee('Activate')->assertDontSee('Deactivate');

        $this->actingAs($secretary)->get(route('secretary.residents.index'))
            ->assertOk()->assertDontSee('action="'.route('secretary.residents.toggle-status', $resident).'"', false);
    }

    public function test_deactivation_preserves_status_transition_audit_and_feedback(): void
    {
        [$secretary, $resident] = $this->fixture();
        $details = route('secretary.residents.show', $resident);

        $this->actingAs($secretary)->from($details)
            ->patch(route('secretary.residents.toggle-status', $resident))
            ->assertRedirect($details)
            ->assertSessionHas('success', "Resident {$resident->full_name} has been marked inactive.");

        $this->assertFalse($resident->fresh()->is_active);
        $this->assertSame(Resident::STATUS_ACTIVE, $resident->fresh()->resident_status);
        $audit = AuditLog::query()->where('event_type', 'status_toggled')
            ->where('model_type', Resident::class)->where('model_id', $resident->id)->sole();
        $this->assertSame($secretary->id, $audit->user_id);
        $this->assertSame(['is_active' => true], $audit->old_values);
        $this->assertSame(['is_active' => false], $audit->new_values);
    }

    public function test_secretary_cannot_toggle_another_barangays_resident(): void
    {
        [$secretary] = $this->fixture();
        [, $foreignResident] = $this->fixture();

        $this->actingAs($secretary)->patch(route('secretary.residents.toggle-status', $foreignResident))
            ->assertForbidden();
        $this->assertTrue($foreignResident->fresh()->is_active);
    }

    public function test_bhw_cannot_use_the_secretary_status_endpoint(): void
    {
        [$secretary, $resident] = $this->fixture();
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $secretary->assigned_barangay_id,
            'assigned_purok_id' => $resident->household->purok_id,
        ]);

        $this->actingAs($bhw)->patch(route('secretary.residents.toggle-status', $resident))->assertForbidden();
        $this->assertTrue($resident->fresh()->is_active);
    }

    public function test_deleted_resident_does_not_gain_details_or_status_action(): void
    {
        [$secretary, $resident] = $this->fixture();
        $resident->delete();

        $this->actingAs($secretary)->get(route('secretary.residents.show', $resident))->assertNotFound();
        $this->patch(route('secretary.residents.toggle-status', $resident))->assertNotFound();
    }

    public function test_admin_shared_views_keep_their_existing_actions(): void
    {
        [, $resident] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.residents.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.residents.edit', $resident).'"', false)
            ->assertSee('action="'.route('admin.residents.toggle-status', $resident).'"', false);
        $this->get(route('admin.residents.show', $resident))->assertOk()
            ->assertDontSee('action="'.route('admin.residents.toggle-status', $resident).'"', false);
    }

    private function fixture(array $attributes = []): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1]);
        $household = Household::query()->create([
            'purok_id' => $purok->id, 'household_no' => '001',
            'household_address' => 'Zone 1', 'is_active' => true,
            'is_social_aid_beneficiary' => false,
        ]);
        $resident = Resident::query()->create(array_merge([
            'household_id' => $household->id, 'first_name' => 'Juana', 'last_name' => 'Dela Cruz',
            'birth_date' => '1994-03-12', 'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Daughter', 'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => true,
        ], $attributes));
        $secretary = User::factory()->create([
            'role' => 'secretary', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => null,
        ]);

        return [$secretary, $resident];
    }
}
