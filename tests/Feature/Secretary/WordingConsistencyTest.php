<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WordingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public static function emptyListings(): array
    {
        $cases = [];
        foreach ([
            'certificates' => ['No certificates have been issued yet.', 'No certificates match the current search or filters.',
                ['search' => 'missing', 'certificate_type' => 'barangay_clearance', 'recipient_type' => 'resident', 'purok_id' => '99999', 'date_from' => '2026-01-01', 'date_to' => '2026-07-01']],
            'drafts' => ['No field drafts are available yet.', 'No field drafts match the current search or filters.',
                ['search' => 'missing', 'purok_id' => '99999', 'status' => 'pending']],
            'update-requests' => ['No correction requests are available yet.', 'No correction requests match the current search or filters.',
                ['search' => 'missing', 'subject_type' => 'resident', 'status' => 'pending']],
            'team' => ['No frontline users are available yet.', 'No frontline users match the current search or filters.',
                ['search' => 'missing', 'role' => 'bhw', 'purok_id' => '99999', 'approval_status' => 'pending', 'status' => 'active']],
        ] as $module => [$firstUse, $filtered, $parameters]) {
            $cases[$module.' unfiltered'] = [$module, [], $firstUse, $filtered];
            $cases[$module.' blank filters and pagination'] = [$module, array_fill_keys(array_keys($parameters), '') + ['page' => '1'], $firstUse, $filtered];
            foreach ($parameters as $key => $value) {
                $cases[$module.' '.$key] = [$module, [$key => $value], $filtered, $firstUse];
            }
        }

        return $cases;
    }

    #[DataProvider('emptyListings')]
    public function test_empty_wording_uses_only_existing_search_and_filter_state(string $module, array $parameters, string $expected, string $unexpected): void
    {
        $this->signInSecretary();
        $this->get(route('secretary.'.$module.'.index', $parameters))
            ->assertOk()->assertSee($expected)->assertDontSee($unexpected);
    }

    public function test_demographics_title_and_existing_filter_contract_remain_consistent(): void
    {
        $this->signInSecretary();
        $response = $this->get(route('secretary.reports.demographics'))->assertOk()
            ->assertSee('Demographics - HealthLink Secretary')
            ->assertDontSee('Local Demographic Export')
            ->assertSee('Registry status reflects resident lifecycle; Active/Inactive record reflects the separate legacy availability flag.');

        foreach (['purok_id', 'sex', 'age_group'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
        // L3 removed historical-state filters from this current-population report.
        $response->assertDontSee('name="resident_status"', false)->assertDontSee('name="is_active"', false);
    }

    public function test_dashboard_uses_resident_status_not_marital_status_wording(): void
    {
        $this->signInSecretary();
        $this->get(route('secretary.dashboard'))->assertOk()
            ->assertSee('resident status mix')->assertDontSee('civil status mix');
    }

    private function signInSecretary(): void
    {
        $barangay = Barangay::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]));
    }
}
