<?php

namespace Tests\Feature\Admin;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminHighPriorityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->flush();
    }

    public function test_non_admin_users_cannot_access_admin_helper_api_routes(): void
    {
        $barangay = Barangay::factory()->create();
        Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 1,
        ]);

        $user = User::factory()->create([
            'role' => 'bhw',
        ]);

        $response = $this->actingAs($user)->getJson("/api/admin/puroks-by-barangay?barangay_id={$barangay->id}");

        $response->assertForbidden();
    }

    public function test_admin_users_can_access_admin_helper_api_routes(): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 4,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/puroks-by-barangay?barangay_id={$barangay->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $purok->id,
                'purok_number' => $purok->purok_number,
            ]);
    }

    public function test_admin_helper_api_uses_the_runtime_rate_limit_setting(): void
    {
        Setting::setValue('rate_limit_attempts', 1);

        $barangay = Barangay::factory()->create();
        Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 8,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/puroks-by-barangay?barangay_id={$barangay->id}")
            ->assertOk();

        $this->actingAs($admin)
            ->getJson("/api/admin/puroks-by-barangay?barangay_id={$barangay->id}")
            ->assertStatus(429)
            ->assertJsonPath('profile', 'admin-helper');
    }

    public function test_mobile_sync_rejects_payloads_that_exceed_the_configured_batch_size(): void
    {
        Setting::setValue('sync_batch_size', 2);

        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 2,
        ]);

        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
        ]);

        Sanctum::actingAs($bhw, ['mobile']);

        $response = $this->postJson('/api/mobile/sync', [
            'households' => [
                ['id' => 1],
                ['id' => 2],
            ],
            'residents' => [
                ['id' => 1],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('max_batch_size', 2)
            ->assertJsonPath('submitted_records', 3);
    }

    public function test_admin_can_generate_a_temporary_password_for_secure_manual_handoff(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $targetUser = User::factory()->create([
            'role' => 'bhw',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.password.generate', $targetUser));

        $response->assertRedirect()
            ->assertSessionHas('temporary_password')
            ->assertSessionHas('success');

        $temporaryPassword = session('temporary_password');

        $this->assertIsString($temporaryPassword);
        $this->assertSame(12, strlen($temporaryPassword));
        $this->assertTrue(Hash::check($temporaryPassword, $targetUser->fresh()->password));
    }
}
