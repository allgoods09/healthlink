<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SearchRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_component_preserves_explicit_metadata_and_legacy_options(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = Blade::render('<x-searchable-record-select name="household_id" :options="$options" />', [
            'errors' => new ViewErrorBag,
            'options' => [
                ['value' => '1', 'label' => 'Household #5', 'description' => 'Purok 1',
                    'ranking' => ['kind' => 'household', 'primaryIdentifier' => '5', 'purok' => 'Purok 1']],
                ['value' => '2', 'label' => 'Legacy option'],
            ],
        ]);

        $this->assertStringContainsString('primaryIdentifier', $html);
        $this->assertStringContainsString('Legacy option', $html);
        $this->assertStringContainsString('Purok 1', $html);
        $this->assertStringContainsString('@mousedown.prevent="selectOption(option)"', $html);
        $this->assertStringContainsString('@keydown.enter.prevent="selectHighlighted()"', $html);
    }

    public function test_shared_selector_exposes_accessible_structure_without_changing_form_binding(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = Blade::render('<x-searchable-record-select name="resident_id" selected="05" :options="$options" required />', [
            'options' => [['value' => '05', 'label' => 'Selected Resident']],
        ]);

        foreach (['role="combobox"', 'aria-autocomplete="list"', 'role="listbox"', 'role="option"',
            'x-id="[\'record-listbox\']"', ':aria-expanded=', ':aria-controls=', ':aria-activedescendant=',
            ':aria-selected=', 'tabindex="-1"', '@keydown.tab="isOpen = false"',
            '@keydown.escape.prevent="isOpen = false"', '@click.prevent="if (isOpen) selectOption(option)"',
            'type="hidden" name="resident_id" x-model="selectedValue"', 'Selected Resident'] as $markup) {
            $this->assertStringContainsString($markup, $html);
        }
        $this->assertStringNotContainsString('@keydown.tab.prevent', $html);
    }

    public function test_secretary_forms_and_directory_opt_in_without_changing_admin_forms(): void
    {
        [$secretary, $household, $resident] = $this->fixture();
        $this->actingAs($secretary);
        $this->get(route('secretary.residents.create'))->assertOk()->assertSee('rankingEnabled: true', false);
        $this->get(route('secretary.residents.edit', $resident))->assertOk()->assertSee('rankingEnabled: true', false);
        $this->get(route('secretary.residents.index'))->assertOk()->assertSee('primaryIdentifier');
        $this->get(route('secretary.households.edit', $household))->assertOk()->assertSee('nameAliases');
        $this->get(route('secretary.residents.relocate.edit', $resident))->assertOk()
            ->assertSee('window.rankHouseholdOptions(this.households, term).slice(0, 12)', false);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.residents.create'))->assertOk()->assertSee('rankingEnabled: false', false);
        $this->get(route('admin.residents.edit', $resident))->assertOk()->assertSee('rankingEnabled: false', false);
    }

    public function test_certificate_recipient_options_include_structured_household_and_resident_metadata(): void
    {
        [$secretary] = $this->fixture();
        $response = $this->actingAs($secretary)->get(route('secretary.certificates.create'))->assertOk();

        foreach (['primaryIdentifier', 'primaryName', 'secondaryFields', 'nameAliases', 'PS-TEST-5'] as $field) {
            $response->assertSee($field);
        }
        $response->assertSee('certificateWizard(')->assertSee('1. Certificate')->assertSee('4. Review &amp; Issue', false);
    }

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => '5', 'household_address' => 'Synthetic Purok 5', 'is_active' => true]);
        $resident = Resident::create(['household_id' => $household->id, 'first_name' => 'Juan', 'last_name' => 'Santos',
            'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Male', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Son', 'resident_status' => 'active',
            'is_active' => true, 'official_resident_code' => 'PS-TEST-5']);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);

        return [$secretary, $household, $resident];
    }
}
