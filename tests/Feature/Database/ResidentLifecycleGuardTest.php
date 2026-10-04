<?php

namespace Tests\Feature\Database;

use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Lifecycle\ResidentLifecycleGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentLifecycleGuardTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    public function test_guard_reads_current_version_and_does_not_advance_it(): void
    {
        $resident = $this->residentFixture()->fresh();
        $guard = app(ResidentLifecycleGuard::class);
        $this->assertSame($resident->id, $guard->requireResident($resident->id, 0, true)->id);
        DB::table('residents')->where('id', $resident->id)->update(['lifecycle_version' => 1]);
        $this->expectException(ValidationException::class);
        $guard->requireResident($resident->id, 0);
    }

    #[DataProvider('unavailableStates')]
    public function test_explicit_active_check_rejects_unavailable_legacy_states(array $state): void
    {
        $resident = $this->residentFixture($state);
        $this->expectException(ValidationException::class);
        app(ResidentLifecycleGuard::class)->requireResident($resident->id, requireActive: true);
    }

    public static function unavailableStates(): array
    {
        return [[['resident_status' => 'deceased']], [['resident_status' => 'relocated']], [['resident_status' => 'moved_out']], [['is_active' => false]]];
    }

    public function test_soft_deleted_and_missing_residents_are_not_available(): void
    {
        $resident = $this->residentFixture();
        $resident->delete();
        foreach ([$resident->id, 99999999] as $id) {
            try {
                app(ResidentLifecycleGuard::class)->requireResident($id);
                $this->fail('Unavailable resident accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('resident_lifecycle', $exception->errors());
            }
        }
    }

    public function test_ownership_guard_checks_fresh_context_and_expected_ids(): void
    {
        $resident = $this->residentFixture();
        $household = $resident->household;
        $guard = app(ResidentLifecycleGuard::class);
        $this->assertSame($household->id, $guard->requireOwnership($resident->id, $household->id, $household->purok_id, $household->purok->barangay_id)->id);
        foreach (['householdId', 'purokId', 'barangayId'] as $field) {
            try {
                $guard->requireOwnership($resident->id, ...[$field => 99999999]);
                $this->fail('Mismatched ownership accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('resident_lifecycle', $exception->errors());
            }
        }
        $household->delete();
        $this->expectException(ValidationException::class);
        $guard->requireOwnership($resident->id);
    }

    public function test_operational_secretary_reuses_existing_account_and_barangay_eligibility(): void
    {
        $resident = $this->residentFixture();
        $barangay = $resident->household->purok->barangay;
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $guard = app(ResidentLifecycleGuard::class);
        $this->assertSame($secretary->id, $guard->requireOperationalSecretary($secretary->id, $barangay->id)->id);
        $secretary->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $guard->requireOperationalSecretary($secretary->id, $barangay->id);
    }

    #[DataProvider('nonOperationalSecretaries')]
    public function test_secretary_guard_rejects_ineligible_accounts_or_barangays(array $attributes, bool $foreign, bool $inactiveBarangay): void
    {
        $resident = $this->residentFixture();
        $barangay = $resident->household->purok->barangay;
        $assigned = $foreign ? $this->residentFixture()->household->purok->barangay_id : $barangay->id;
        $user = User::factory()->create($attributes + ['role' => 'secretary', 'assigned_barangay_id' => $assigned]);
        if ($inactiveBarangay) {
            $barangay->update(['is_active' => false]);
        }
        $this->expectException(ValidationException::class);
        app(ResidentLifecycleGuard::class)->requireOperationalSecretary($user->id, $barangay->id);
    }

    public static function nonOperationalSecretaries(): array
    {
        return [[['role' => 'admin'], false, false], [['approval_status' => 'pending'], false, false],
            [['email_verified_at' => null], false, false], [['is_active' => false], false, false],
            [[], true, false], [[], false, true], [['assigned_barangay_id' => null], false, false]];
    }

    #[DataProvider('corrections')]
    public function test_only_pending_correctly_typed_relevant_corrections_block_without_mutation(string $subject, string $status, bool $relevant, bool $expected): void
    {
        $resident = $this->residentFixture();
        $other = $this->residentFixture();
        $target = $relevant ? $resident : $other;
        $request = ProfileUpdateRequest::create(['submitted_by_user_id' => User::factory()->create()->id,
            'barangay_id' => $target->household->purok->barangay_id, 'subject_type' => $subject,
            'subject_id' => $subject === 'resident' ? $target->id : $target->household_id,
            'current_snapshot' => [], 'proposed_changes' => [], 'request_reason' => 'Fixture', 'request_status' => $status]);
        $before = $request->fresh()->getRawOriginal();
        $guard = app(ResidentLifecycleGuard::class);
        $this->assertSame($expected, $guard->hasPendingCorrections($resident->id));
        if ($expected) {
            try {
                $guard->requireNoPendingCorrections($resident->id);
                $this->fail('Pending correction accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('resident_lifecycle', $exception->errors());
            }
        } else {
            $guard->requireNoPendingCorrections($resident->id);
        }
        if (! $relevant && $subject === 'household' && $status === 'pending') {
            $this->assertTrue($guard->hasPendingCorrections($resident->id, [$other->household_id]));
        }
        $this->assertSame($before, $request->fresh()->getRawOriginal());
    }

    public static function corrections(): array
    {
        return [['resident', 'pending', true, true], ['household', 'pending', true, true], ['resident', 'approved', true, false],
            ['household', 'rejected', true, false], ['resident', 'pending', false, false], ['household', 'pending', false, false]];
    }
}
