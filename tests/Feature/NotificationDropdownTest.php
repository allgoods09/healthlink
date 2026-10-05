<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationDropdownTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('themes')]
    public function test_disclosure_keeps_notification_form_contracts_and_counts(string $theme): void
    {
        $notifications = $this->notifications();
        $html = Blade::render('<x-notification-dropdown :notifications="$notifications" :unread-count="7" :theme="$theme" />',
            compact('notifications', 'theme'));
        $xpath = $this->xpath($html);
        $trigger = $xpath->query('//button[@aria-label="Open notifications"]')->item(0);
        $this->assertSame('button', $trigger->getAttribute('type'));
        $this->assertSame('false', $trigger->getAttribute('aria-expanded'));
        $this->assertStringContainsString(':aria-expanded="open.toString()"', $html);
        $id = $trigger->getAttribute('aria-controls');
        $this->assertNotEmpty($id);
        $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"][@role="region"]')->length);
        $panel = $xpath->query('//*[@id="'.$id.'"]')->item(0);
        $this->assertSame($id.'-title', $panel->getAttribute('aria-labelledby'));
        $this->assertSame('Notifications', $xpath->query('//*[@id="'.$id.'-title"]')->item(0)->textContent);
        $this->assertStringContainsString('@keydown.escape.window=', $html);
        $this->assertStringContainsString('$refs.trigger.focus()', $html);
        $this->assertStringContainsString('@click.away="open = false"', $html);
        $this->assertStringNotContainsString('role="menu"', $html);
        $this->assertStringNotContainsString('role="dialog"', $html);
        $this->assertStringNotContainsString('@keydown.tab', $html);
        $this->assertStringContainsString('focus:ring-2', $trigger->getAttribute('class'));
        $this->assertStringContainsString($theme === 'admin' ? 'text-gray-500' : 'text-slate-500', $trigger->getAttribute('class'));
        $this->assertSame('7', trim($xpath->query('.//span', $trigger)->item(0)->textContent));
        $this->assertStringContainsString('7 unread', $html);
        $forms = $xpath->query('//form');
        $this->assertSame(7, $forms->length);
        $this->assertSame(route('notifications.read-all'), $forms->item(0)->getAttribute('action'));
        foreach ($forms as $form) {
            $this->assertSame('POST', $form->getAttribute('method'));
            $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);
            $this->assertSame('submit', $xpath->query('.//button', $form)->item(0)->getAttribute('type'));
        }
        foreach ($notifications as $index => $notification) {
            $this->assertSame(route('notifications.open', $notification->id), $forms->item($index + 1)->getAttribute('action'));
            $this->assertStringContainsString($notification->data['title'], $forms->item($index + 1)->textContent);
        }
        $this->assertSame(1, $xpath->query('//a[@href="'.route('notifications.index').'"]')->length);
    }

    #[DataProvider('themes')]
    public function test_empty_and_large_unread_counts_keep_existing_behavior(string $theme): void
    {
        $html = Blade::render('<x-notification-dropdown :theme="$theme" />', compact('theme'));
        $this->assertStringContainsString('No notifications yet.', $html);
        $this->assertStringContainsString('0 unread', $html);
        $this->assertSame(0, $this->xpath($html)->query('//form')->length);
        $this->assertSame(0, $this->xpath($html)->query('//button[@aria-label="Open notifications"]//span')->length);
        $html = Blade::render('<x-notification-dropdown :unread-count="120" :theme="$theme" />', compact('theme'));
        $this->assertStringContainsString('99+', $html);
        $this->assertStringContainsString('120 unread', $html);
    }

    public function test_multiple_disclosures_have_unique_panel_ids(): void
    {
        $xpath = $this->xpath(Blade::render('<x-notification-dropdown /><x-notification-dropdown theme="admin" />'));
        $triggers = $xpath->query('//button[@aria-controls]');
        $this->assertSame(2, $triggers->length);
        $first = $triggers->item(0)->getAttribute('aria-controls');
        $second = $triggers->item(1)->getAttribute('aria-controls');
        $this->assertNotSame($first, $second);
        foreach ([$first, $second] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
        }
    }

    #[DataProvider('roles')]
    public function test_shared_dropdown_renders_in_each_active_layout(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $notifications = $this->notifications();
        // Synthetic presentation fixtures only: no notification mutations or notification endpoint requests.
        View::composer(['layouts.portal', 'layouts.admin'], function ($view) use ($notifications): void {
            $view->with('layoutRecentNotifications', $notifications)->with('layoutUnreadNotificationCount', 7);
        });
        $page = $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('aria-label="Open notifications"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Mark all read')
            ->assertSee(route('notifications.index'), false);
        if (($directory = getenv('NOTIFICATION_BROWSER_DIR')) && in_array($role, ['secretary', 'admin'], true)) {
            file_put_contents($directory.'/'.$role.'.html', $page->getContent());
        }
    }

    public static function themes(): array
    {
        return [['portal'], ['admin']];
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], ['secretary', 'bhw', 'bns', 'phn', 'mho', 'admin']);
    }

    private function notifications(): Collection
    {
        return collect(range(1, 6))->map(fn ($index) => new DatabaseNotification([
            'id' => sprintf('00000000-0000-4000-8000-%012d', $index),
            'data' => ['title' => 'Synthetic notice '.$index,
                'body' => str_repeat('Synthetic notification content for a household follow-up. ', 4), 'level' => 'info'],
            'created_at' => now()->subMinutes($index),
            'read_at' => $index === 6 ? now() : null,
        ]));
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }
}
