<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontlineUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_can_create_a_bhw_account_in_their_barangay(): void
    {
        [$secretary, $purok] = $this->secretaryContext();

        $response = $this->actingAs($secretary)->post(route('secretary.team.store'), [
            'role' => 'bhw',
            'first_name' => 'New',
            'middle_name' => 'Barangay',
            'last_name' => 'Health Worker',
            'suffix' => null,
            'email' => 'new-bhw@example.com',
            'password' => 'securePass123',
            'password_confirmation' => 'securePass123',
            'assigned_purok_id' => $purok->id,
            'is_active' => '1',
        ]);

        $bhw = User::query()->where('email', 'new-bhw@example.com')->firstOrFail();

        $response->assertRedirect(route('secretary.team.show', $bhw));

        $this->assertSame('bhw', $bhw->role);
        $this->assertSame(User::APPROVAL_APPROVED, $bhw->approval_status);
        $this->assertSame('secretary', $bhw->registered_via);
        $this->assertSame($secretary->assigned_barangay_id, $bhw->assigned_barangay_id);
        $this->assertSame($purok->id, $bhw->assigned_purok_id);
        $this->assertSame($secretary->id, $bhw->approved_by);
        $this->assertTrue($bhw->is_active);
    }

    public function test_secretary_can_approve_a_pending_bns_self_registration(): void
    {
        [$secretary] = $this->secretaryContext();

        $pendingBns = User::factory()->create([
            'role' => 'bns',
            'requested_role' => 'bns',
            'approval_status' => User::APPROVAL_PENDING,
            'registered_via' => 'self',
            'assigned_barangay_id' => null,
            'assigned_purok_id' => null,
            'requested_barangay_id' => $secretary->assigned_barangay_id,
            'requested_purok_id' => null,
            'is_active' => false,
        ]);

        $response = $this->actingAs($secretary)->patch(route('secretary.team.approve', $pendingBns));

        $response->assertSessionHas('success');

        $pendingBns->refresh();

        $this->assertSame(User::APPROVAL_APPROVED, $pendingBns->approval_status);
        $this->assertSame($secretary->assigned_barangay_id, $pendingBns->assigned_barangay_id);
        $this->assertNull($pendingBns->assigned_purok_id);
        $this->assertSame($secretary->id, $pendingBns->approved_by);
        $this->assertTrue($pendingBns->is_active);
    }

    public function test_secretary_cannot_assign_frontline_user_to_foreign_purok(): void
    {
        [$secretary] = $this->secretaryContext();
        $foreignBarangay = Barangay::factory()->create();
        $foreignPurok = Purok::factory()->create([
            'barangay_id' => $foreignBarangay->id,
            'purok_number' => 8,
        ]);

        $response = $this->actingAs($secretary)
            ->from(route('secretary.team.create'))
            ->post(route('secretary.team.store'), [
                'role' => 'bhw',
                'first_name' => 'Wrong',
                'middle_name' => 'Scope',
                'last_name' => 'User',
                'suffix' => null,
                'email' => 'wrong-scope@example.com',
                'password' => 'securePass123',
                'password_confirmation' => 'securePass123',
                'assigned_purok_id' => $foreignPurok->id,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('secretary.team.create'));
        $response->assertSessionHasErrors('assigned_purok_id');

        $this->assertDatabaseMissing('users', [
            'email' => 'wrong-scope@example.com',
        ]);
    }

    public function test_secretary_can_reset_a_frontline_user_password(): void
    {
        [$secretary, $purok] = $this->secretaryContext();
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $secretary->assigned_barangay_id,
            'assigned_purok_id' => $purok->id,
        ]);

        $response = $this->actingAs($secretary)->put(route('secretary.team.password.reset', $bhw), [
            'password' => 'newSecurePass123',
            'password_confirmation' => 'newSecurePass123',
        ]);

        $response->assertRedirect(route('secretary.team.show', $bhw));

        $bhw->refresh();

        $this->assertTrue(Hash::check('newSecurePass123', $bhw->password));
    }

    public function test_account_actions_keep_their_forms_and_canonical_navigation(): void
    {
        [$secretary, $purok] = $this->secretaryContext();
        $user = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $secretary->assigned_barangay_id,
            'assigned_purok_id' => $purok->id, 'approval_status' => User::APPROVAL_PENDING,
            'email_verified_at' => null,
        ]);
        $this->actingAs($secretary);
        $pages = [
            'create' => [null, ['store' => [null, 'add']], ['index' => 'back']],
            'edit' => [$user, ['update' => ['PUT', 'edit'], 'approve' => ['PATCH', 'emerald'], 'reject' => ['PATCH', 'rose'],
                'verification.resend' => [null, 'edit'], 'verification.mark' => ['PATCH', 'amber']], ['show' => 'view', 'index' => 'back']],
            'show' => [$user, ['verification.resend' => [null, 'edit'], 'verification.mark' => ['PATCH', 'amber']], ['edit' => 'manage', 'password.edit' => 'security']],
            'password.edit' => [$user, ['password.reset' => ['PUT', 'edit'], 'password.generate' => [null, 'amber']], ['show' => 'back']],
        ];
        foreach ($pages as $page => [$parameter, $actions, $links]) {
            $html = $this->get(route('secretary.team.'.$page, $parameter))->assertOk()->getContent();
            $dom = new DOMDocument;
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            foreach ($actions as $action => [$method, $style]) {
                $forms = $xpath->query('//form[@action="'.route('secretary.team.'.$action, $action === 'store' ? null : $user).'"]');
                $this->assertCount(1, $forms);
                $form = $forms->item(0);
                $this->assertSame('POST', $form->getAttribute('method'));
                $this->assertCount(1, $xpath->query('.//input[@name="_token"]', $form));
                $spoofing = $xpath->query('.//input[@name="_method"]', $form);
                $this->assertCount($method === null ? 0 : 1, $spoofing);
                if ($method !== null) {
                    $this->assertSame($method, $spoofing->item(0)->getAttribute('value'));
                }
                $button = $xpath->query('.//button[@type="submit"]', $form)->item(0);
                $this->assertInstanceOf(DOMElement::class, $button);
                $this->assertFalse($button->hasAttribute('name'));
                $this->assertFalse($button->hasAttribute('value'));
                foreach (['min-h-10', 'rounded-md', 'focus:ring-2'] as $class) {
                    $this->assertStringContainsString($class, $button->getAttribute('class'));
                }
                if (in_array($style, ['emerald', 'rose', 'amber'], true)) {
                    $this->assertStringContainsString('bg-'.$style.'-', $button->getAttribute('class'));
                } else {
                    $this->assertSame($style, $button->getAttribute('data-record-action'));
                }
                if ($action === 'reject') {
                    $this->assertCount(1, $xpath->query('.//input[@type="hidden" and @name="approval_notes" and @value=""]', $form));
                }
            }
            foreach ($links as $destination => $variant) {
                $link = $xpath->query('//a[@href="'.route('secretary.team.'.$destination, $destination === 'index' ? null : $user).'" and @data-record-action="'.$variant.'"]');
                $this->assertCount(1, $link);
                $this->assertStringContainsString('flex-wrap', $link->item(0)->parentNode->getAttribute('class'));
            }
        }
    }

    public function test_account_actions_retain_approved_and_verified_visibility_rules(): void
    {
        [$secretary, $purok] = $this->secretaryContext();
        $user = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $secretary->assigned_barangay_id,
            'assigned_purok_id' => $purok->id, 'approval_status' => User::APPROVAL_APPROVED]);
        foreach (['edit', 'show'] as $page) {
            $response = $this->actingAs($secretary)->get(route('secretary.team.'.$page, $user))->assertOk();
            foreach (['Approve Registration', 'Reject Registration', 'Resend Verification Email', 'Mark as Verified'] as $label) {
                $response->assertDontSee($label);
            }
        }
    }

    private function secretaryContext(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 4,
        ]);
        $secretary = User::factory()->create([
            'role' => 'secretary',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);

        return [$secretary, $purok];
    }
}
