<?php

namespace Tests\Feature\Auth;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_bhw_users_can_register_pending_secretary_approval(): void
    {
        Notification::fake();

        $barangay = Barangay::factory()->create();

        $response = $this->post('/register', [
            'name' => 'Test BHW',
            'email' => 'bhw@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'requested_role' => 'bhw',
            'requested_barangay_id' => $barangay->id,
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'bhw@example.com',
            'role' => 'bhw',
            'requested_role' => 'bhw',
            'requested_barangay_id' => $barangay->id,
            'requested_purok_id' => null,
            'approval_status' => User::APPROVAL_PENDING,
            'registered_via' => 'self',
            'is_active' => false,
        ]);

        $user = User::query()->where('email', 'bhw@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_new_bns_users_can_register_pending_secretary_approval(): void
    {
        Notification::fake();

        $barangay = Barangay::factory()->create();

        $response = $this->post('/register', [
            'name' => 'Test BNS',
            'email' => 'bns@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'requested_role' => 'bns',
            'requested_barangay_id' => $barangay->id,
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'bns@example.com',
            'role' => 'bns',
            'requested_role' => 'bns',
            'requested_barangay_id' => $barangay->id,
            'requested_purok_id' => null,
            'approval_status' => User::APPROVAL_PENDING,
            'registered_via' => 'self',
            'is_active' => false,
        ]);

        $user = User::query()->where('email', 'bns@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
