<?php

namespace Tests\Feature\Mobile;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
use App\Support\MobileBarangayScope;
use App\Support\MobileBootstrapPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MobileBarangayScopeTest extends TestCase
{
    use RefreshDatabase;

    public static function unusableAssignments(): array
    {
        return [
            'missing' => ['missing'],
            'inactive' => ['inactive'],
            'deleted' => ['deleted'],
            'invalid reference' => ['invalid'],
        ];
    }

    public static function persistedUnusableAssignments(): array
    {
        return array_diff_key(self::unusableAssignments(), ['invalid reference' => true]);
    }

    #[DataProvider('unusableAssignments')]
    public function test_unusable_assignment_blocks_bootstrap_seed_verify_and_sync(string $state): void
    {
        $bhw = $this->bhw();
        $this->makeAssignmentUnusable($bhw, $state);
        Sanctum::actingAs($bhw, ['mobile']);

        foreach (['bootstrap', 'seed', 'verify'] as $endpoint) {
            $this->getJson('/api/mobile/'.$endpoint)
                ->assertForbidden()
                ->assertJsonPath('message', MobileBarangayScope::DENIED_MESSAGE)
                ->assertJsonMissingPath('households')
                ->assertJsonMissingPath('residents')
                ->assertJsonMissingPath('field_visits')
                ->assertJsonMissingPath('risk_assessments');
        }

        $this->postJson('/api/mobile/sync', [
            'households' => [[
                'mobile_uuid' => (string) Str::uuid(),
                'household_no' => 'C1-001',
                'household_address' => 'Must not be written',
                'is_social_aid_beneficiary' => false,
                'is_active' => true,
            ]],
        ])->assertForbidden()->assertJsonPath('message', MobileBarangayScope::DENIED_MESSAGE);

        $this->assertDatabaseCount('households', 0);
        $this->assertDatabaseCount('sync_logs', 0);
    }

    #[DataProvider('persistedUnusableAssignments')]
    public function test_mobile_login_does_not_issue_a_token_for_an_unusable_assignment(string $state): void
    {
        $bhw = $this->bhw();
        $this->makeAssignmentUnusable($bhw, $state);

        $this->postJson('/api/mobile/auth/login', [
            'email' => $bhw->email,
            'password' => 'password',
            'device_name' => 'Field Tablet',
        ])->assertForbidden()->assertJsonPath('message', MobileBarangayScope::DENIED_MESSAGE);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('persistedUnusableAssignments')]
    public function test_admin_cannot_issue_a_mobile_token_for_an_unusable_assignment(string $state): void
    {
        $bhw = $this->bhw();
        $this->makeAssignmentUnusable($bhw, $state);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.devices.issue'), [
            'user_id' => $bhw->id,
            'device_name' => 'Field Tablet',
        ])->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('persistedUnusableAssignments')]
    public function test_existing_token_fails_after_assignment_becomes_unusable(string $state): void
    {
        $bhw = $this->bhw();
        $token = $bhw->createToken('Previously valid device', ['mobile'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/mobile/bootstrap', $headers)->assertOk();
        $this->getJson('/api/mobile/verify', $headers)->assertOk();

        $this->makeAssignmentUnusable($bhw, $state);

        foreach (['bootstrap', 'verify'] as $endpoint) {
            $this->app['auth']->forgetGuards();
            $this->getJson('/api/mobile/'.$endpoint, $headers)
                ->assertForbidden()
                ->assertJsonPath('message', MobileBarangayScope::DENIED_MESSAGE);
        }

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/mobile/sync', [], $headers)->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_admin_can_still_issue_a_usable_token_for_an_assigned_bhw(): void
    {
        $bhw = $this->bhw();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.devices.issue'), [
            'user_id' => $bhw->id,
            'device_name' => 'Assigned device',
        ])->assertRedirect(route('admin.devices.index'))->assertSessionHas('issued_token');

        $token = $response->getSession()->get('issued_token');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/mobile/bootstrap', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('assignment.barangay.id', $bhw->assigned_barangay_id);
    }

    #[DataProvider('unusableAssignments')]
    public function test_bootstrap_builder_itself_rejects_unusable_assignments(string $state): void
    {
        $bhw = $this->bhw();
        $this->makeAssignmentUnusable($bhw, $state);

        try {
            app(MobileBootstrapPayload::class)->build($bhw);
            $this->fail('Bootstrap must not build an unscoped payload.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(MobileBarangayScope::DENIED_MESSAGE, $exception->getMessage());
        }
    }

    private function bhw(): User
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);

        return User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
        ]);
    }

    private function makeAssignmentUnusable(User $bhw, string $state): void
    {
        // Preload the relation to ensure cached assignment data cannot bypass the check.
        $barangay = $bhw->assignedBarangay;

        match ($state) {
            'missing' => $bhw->update(['assigned_barangay_id' => null]),
            'inactive' => $barangay->update(['is_active' => false]),
            'deleted' => $barangay->delete(),
            // Simulate a dangling reference without disabling database FK protections.
            'invalid' => $bhw->forceFill(['assigned_barangay_id' => 999999999]),
        };
    }
}
