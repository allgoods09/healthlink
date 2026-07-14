<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
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
            'name' => 'New Barangay Health Worker',
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
                'name' => 'Wrong Scope User',
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
