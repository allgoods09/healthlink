<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationSkeletonPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_secretary_residents_sidebar_navigation_opts_in(): void
    {
        $barangay = Barangay::factory()->create();
        $secretary = User::factory()->create([
            'role' => 'secretary',
            'assigned_barangay_id' => $barangay->id,
        ]);

        $response = $this->actingAs($secretary)->get(route('secretary.residents.index'));
        $response->assertOk()
            ->assertSee('data-navigation-loading-region', false)
            ->assertSee('data-navigation-skeleton-layout="generic"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-navigation-skeleton="generic"'));
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('secretary.residents.index'), '/').'"[^>]+data-navigation-skeleton="generic"/',
            $response->getContent(),
        );
    }

    public function test_other_role_navigation_does_not_opt_in(): void
    {
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => Barangay::factory()->create()->id,
        ]);

        $this->actingAs($bhw)->get(route('bhw.households.index'))
            ->assertOk()->assertDontSee('data-navigation-skeleton="generic"', false);
    }
}
