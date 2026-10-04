<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_pages_use_the_same_prepaint_shell_and_correct_role_context(): void
    {
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $this->actingAs($secretary);
        foreach (['secretary.dashboard', 'secretary.residents.index', 'secretary.households.index',
            'secretary.documents.index', 'secretary.officials.index', 'secretary.certificates.index', 'secretary.certificates.create'] as $route) {
            $page = $this->get(route($route))->assertOk()
                ->assertSee("sidebarLayout('portal-secretary')", false)->assertDontSee('portal-default')
                ->assertSee('data-sidebar-shell', false)->assertSee('data-sidebar-content', false)->assertSee('data-sidebar', false)
                ->assertSee('healthlinkSidebarDesktopPreference', false);
            $html = $page->getContent();
            $this->assertLessThan(strpos($html, '<body'), strpos($html, 'dataset.sidebarDesktopOpen'));
        }
    }

    public function test_other_portal_role_is_isolated_and_admin_keeps_its_existing_context(): void
    {
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $this->actingAs($bhw)->get(route('bhw.households.index'))->assertOk()
            ->assertSee("sidebarLayout('portal-bhw')", false)->assertDontSee('portal-secretary')->assertDontSee('portal-default');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee("sidebarLayout('admin')", false)
            ->assertDontSee("sidebarLayout('portal-secretary')", false);
    }
}
