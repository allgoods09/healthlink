<?php

namespace Tests\Feature\Bns;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BnsRoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_bns_dashboard_reflects_nutrition_only_scope(): void
    {
        $barangay = Barangay::factory()->create();
        $bns = User::factory()->create([
            'role' => 'bns',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);

        $response = $this->actingAs($bns)->get(route('bns.dashboard'));

        $response->assertOk();
        $response->assertSee('Nutrition-only monitoring');
        $response->assertDontSee('BHW Team');
    }

    public function test_legacy_bns_management_routes_are_not_available(): void
    {
        $barangay = Barangay::factory()->create();
        $bns = User::factory()->create([
            'role' => 'bns',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);

        foreach ([
            '/bns/households',
            '/bns/residents',
            '/bns/team',
            '/bns/devices',
            '/bns/sync-logs',
            '/bns/visits',
            '/bns/reports/demographics',
        ] as $uri) {
            $this->actingAs($bns)->get($uri)->assertNotFound();
        }
    }
}
