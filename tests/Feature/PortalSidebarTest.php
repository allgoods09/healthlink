<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalSidebarTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('portalRoles')]
    public function test_portal_roles_have_mobile_sidebar_and_desktop_header_profile_access(string $role, string $page): void
    {
        $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $response = $this->actingAs($user)->get(route($page))->assertOk()
            ->assertSee("sidebarLayout('portal-{$role}')", false)
            ->assertSee('data-sidebar-shell', false)
            ->assertSee('aria-label="Open notifications"', false)
            ->assertSee('x-ref="sidebarScroll"', false)
            ->assertSee('@click.capture="handleNavClick($event)"', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $mobile = $xpath->query('//aside[@data-sidebar]//a[@data-mobile-profile-link]');
        $this->assertSame(1, $mobile->length);
        $link = $mobile->item(0);
        $this->assertSame(route('profile.edit'), $link->getAttribute('href'));
        $this->assertSame('Profile', trim($link->textContent));
        $this->assertContains('sm:hidden', explode(' ', $link->getAttribute('class')));
        $this->assertContains('focus:underline', explode(' ', $link->getAttribute('class')));
        $this->assertFalse($link->hasAttribute('tabindex'));
        $this->assertFalse($link->hasAttribute('onclick'));

        $desktop = $xpath->query('//*[@data-sidebar-content]/nav//a[@href="'.route('profile.edit').'"]');
        $this->assertSame(1, $desktop->length);
        $this->assertSame('Profile', trim($desktop->item(0)->textContent));
        $classes = explode(' ', $desktop->item(0)->getAttribute('class'));
        $this->assertContains('hidden', $classes);
        $this->assertContains('sm:inline-flex', $classes);

        $logout = $xpath->query('//*[@data-sidebar-content]/nav//form[@action="'.route('logout').'"]');
        $this->assertSame(1, $logout->length);
        $this->assertSame('POST', $logout->item(0)->getAttribute('method'));
        $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $logout->item(0))->length);
        $this->assertSame('Logout', trim($xpath->query('.//button[@type="submit"]', $logout->item(0))->item(0)->textContent));
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        if ($role === 'secretary' && ($path = getenv('PORTAL_PROFILE_BROWSER_HTML'))) {
            file_put_contents($path, $response->getContent());
        }
    }

    public static function portalRoles(): array
    {
        return [
            'Secretary' => ['secretary', 'secretary.certificates.create'],
            'BHW' => ['bhw', 'bhw.dashboard'],
            'BNS' => ['bns', 'bns.dashboard'],
            'PHN' => ['phn', 'phn.dashboard'],
            'MHO' => ['mho', 'mho.dashboard'],
        ];
    }

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
            ->assertDontSee("sidebarLayout('portal-secretary')", false)
            ->assertDontSee('data-mobile-profile-link', false)
            ->assertSee(route('profile.edit'), false);
    }
}
