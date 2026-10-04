<?php

namespace Tests\Feature\Admin;

use App\Models\Barangay;
use App\Models\User;
use App\Support\BarangayOfficialsRegistry;
use App\Support\OperationalSecretaryGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalSecretaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creation_rejects_second_operational_secretary_but_allows_another_barangay(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barangay = Barangay::factory()->create();
        $payload = $this->payload($barangay);
        $this->actingAs($admin)->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, User::operationalSecretaries()->where('assigned_barangay_id', $barangay->id)->count());
        $payload['email'] = 'second-secretary@healthlink.test';
        $this->post(route('admin.users.store'), $payload)->assertSessionHasErrors('assigned_barangay_id');
        $payload['assigned_barangay_id'] = Barangay::factory()->create()->id;
        $this->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, User::operationalSecretaries()->count());
    }

    public function test_ineligible_states_allow_manual_fallback_and_do_not_conflict(): void
    {
        foreach ([['approval_status' => 'pending'], ['approval_status' => 'rejected'], ['is_active' => false],
            ['email_verified_at' => null], ['deleted_at' => now()]] as $state) {
            $barangay = Barangay::factory()->create();
            $barangay->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => 'Manual Fallback']);
            $this->secretary($barangay, $state);
            $registry = app(BarangayOfficialsRegistry::class);
            $this->assertNull($registry->operationalSecretary($barangay));
            $this->assertSame('Manual Fallback', $registry->resolvedSecretaryName($barangay));
            $account = $this->secretary($barangay);
            $this->assertSame($account->display_name, $registry->resolvedSecretaryName($barangay));
        }
    }

    public function test_activation_approval_and_verification_cannot_make_a_second_secretary_operational(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barangay = Barangay::factory()->create();
        $this->secretary($barangay);
        $inactive = $this->secretary($barangay, ['is_active' => false]);
        $pending = $this->secretary($barangay, ['approval_status' => 'pending', 'is_active' => false]);
        $unverified = $this->secretary($barangay, ['email_verified_at' => null]);
        $this->actingAs($admin);
        foreach ([['toggle-status', $inactive], ['approve', $pending], ['verification.mark', $unverified]] as [$action, $candidate]) {
            $this->patch(route('admin.users.'.$action, $candidate))->assertSessionHasErrors('assigned_barangay_id');
        }
        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertSame('pending', $pending->fresh()->approval_status);
        $this->assertNull($unverified->fresh()->email_verified_at);
    }

    public function test_reassignment_and_role_change_cannot_displace_existing_secretary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barangay = Barangay::factory()->create();
        $this->secretary($barangay);
        $other = Barangay::factory()->create();
        $candidate = $this->secretary($other);
        $this->actingAs($admin)->put(route('admin.users.update', $candidate), $this->payload($barangay, $candidate))
            ->assertSessionHasErrors('assigned_barangay_id');
        $this->assertSame($other->id, $candidate->fresh()->assigned_barangay_id);
        $phn = User::factory()->create(['role' => 'phn']);
        $this->put(route('admin.users.update', $phn), $this->payload($barangay, $phn))
            ->assertSessionHasErrors('assigned_barangay_id');
        $this->assertSame('phn', $phn->fresh()->role);
    }

    public function test_restoration_and_bulk_activation_cannot_create_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barangay = Barangay::factory()->create();
        $deleted = $this->secretary($barangay);
        $deleted->delete();
        $this->secretary($barangay);
        $this->actingAs($admin)->patch(route('admin.users.restore', $deleted->id))->assertSessionHasErrors('assigned_barangay_id');
        $this->assertNotNull(User::withTrashed()->findOrFail($deleted->id)->deleted_at);
        $other = Barangay::factory()->create();
        $a = $this->secretary($other, ['is_active' => false]);
        $b = $this->secretary($other, ['is_active' => false]);
        $this->post(route('admin.users.bulk-toggle-status'), ['action' => 'activate', 'user_ids' => [$a->id, $b->id]])
            ->assertSessionHasErrors('assigned_barangay_id');
        $this->assertSame(0, User::operationalSecretaries()->where('assigned_barangay_id', $other->id)->count());
    }

    public function test_replacement_is_explicit_and_resolver_is_readonly_with_canonical_names(): void
    {
        $barangay = Barangay::factory()->create();
        $barangay->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => 'Preserved Fallback']);
        $registry = app(BarangayOfficialsRegistry::class);
        $account = $this->secretary($barangay, ['first_name' => 'Maria', 'middle_name' => 'Elena', 'last_name' => 'Santos', 'suffix' => 'II']);
        $before = DB::table('barangay_officials')->where('barangay_id', $barangay->id)->get()->toJson();
        $this->assertSame('Maria Elena Santos II', $registry->resolvedSecretaryName($barangay));
        $this->assertSame($before, DB::table('barangay_officials')->where('barangay_id', $barangay->id)->get()->toJson());
        $account->update(['is_active' => false]);
        $this->assertSame('Preserved Fallback', $registry->resolvedSecretaryName($barangay));
        $new = $this->secretary($barangay);
        $this->assertSame($new->display_name, $registry->resolvedSecretaryName($barangay));
    }

    public function test_unusable_barangay_and_missing_manual_row_resolve_to_no_account(): void
    {
        $barangay = Barangay::factory()->create(['is_active' => false]);
        $this->secretary($barangay);
        $barangay->officials()->where('role_key', 'barangay_secretary')->delete();
        $registry = app(BarangayOfficialsRegistry::class);
        $this->assertNull($registry->resolvedSecretaryName($barangay));
        $barangay->delete();
        $this->assertNull($registry->resolvedSecretaryName($barangay));
    }

    public function test_legacy_ambiguity_is_reported_instead_of_choosing_an_account(): void
    {
        $barangay = Barangay::factory()->create();
        $this->secretary($barangay);
        $legacy = $this->secretary($barangay, ['is_active' => false]);
        DB::table('users')->where('id', $legacy->id)->update(['is_active' => true]);
        $this->expectException(ValidationException::class);
        app(BarangayOfficialsRegistry::class)->resolvedSecretaryName($barangay);
    }

    public function test_barangay_reactivation_cannot_make_two_secretaries_operational(): void
    {
        $barangay = Barangay::factory()->create(['is_active' => false]);
        $this->secretary($barangay);
        $this->secretary($barangay);
        try {
            $barangay->update(['is_active' => true]);
            $this->fail('Ambiguous barangay activation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(OperationalSecretaryGuard::MESSAGE, $exception->errors()['is_active'][0]);
        }
        $this->assertFalse($barangay->fresh()->is_active);
    }

    public function test_stale_account_state_cannot_bypass_guard_when_verification_completes_eligibility(): void
    {
        $barangay = Barangay::factory()->create();
        $this->secretary($barangay);
        $candidate = $this->secretary($barangay, ['is_active' => false, 'email_verified_at' => null]);
        $stale = User::findOrFail($candidate->id);
        $candidate->update(['is_active' => true]);
        $this->expectException(ValidationException::class);
        $stale->markEmailAsVerified();
    }

    public function test_stale_barangay_state_cannot_bypass_guard_on_restoration(): void
    {
        $barangay = Barangay::factory()->create(['is_active' => false]);
        $this->secretary($barangay);
        $this->secretary($barangay);
        $barangay->delete();
        $stale = Barangay::withTrashed()->findOrFail($barangay->id);
        $barangay->update(['is_active' => true]);
        try {
            $stale->restore();
            $this->fail('Restoration must use the current barangay activation state.');
        } catch (ValidationException $exception) {
            $this->assertSame(OperationalSecretaryGuard::MESSAGE, $exception->errors()['is_active'][0]);
        }
        $this->assertNotNull(Barangay::withTrashed()->findOrFail($barangay->id)->deleted_at);
    }

    private function secretary(Barangay $barangay, array $state = []): User
    {
        return User::factory()->create($state + ['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
    }

    private function payload(Barangay $barangay, ?User $user = null): array
    {
        return ['first_name' => 'Secretary', 'last_name' => 'Candidate', 'email' => $user?->email ?? 'first-secretary@healthlink.test',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'secretary',
            'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => null, 'is_active' => true];
    }
}
