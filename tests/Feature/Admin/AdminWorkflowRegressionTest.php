<?php

namespace Tests\Feature\Admin;

use App\Models\ArchivedRecord;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AdminWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_a_pending_bhw_registration(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 1,
        ]);
        $pendingUser = User::factory()->create([
            'role' => 'bhw',
            'approval_status' => User::APPROVAL_PENDING,
            'is_active' => false,
            'requested_role' => 'bhw',
            'requested_barangay_id' => $barangay->id,
            'requested_purok_id' => $purok->id,
            'assigned_barangay_id' => null,
            'assigned_purok_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.approve', $pendingUser));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue($pendingUser->fresh()->is_active);
        $this->assertSame(User::APPROVAL_APPROVED, $pendingUser->fresh()->approval_status);
        $this->assertSame($barangay->id, $pendingUser->fresh()->assigned_barangay_id);
        $this->assertSame($purok->id, $pendingUser->fresh()->assigned_purok_id);
    }

    public function test_admin_can_restore_a_deleted_user_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $targetUser = User::factory()->create([
            'role' => 'bhw',
        ]);

        $targetUser->delete();

        $response = $this->actingAs($admin)->patch(route('admin.users.restore', $targetUser->id));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertFalse($targetUser->fresh()->trashed());
    }

    public function test_admin_can_issue_and_revoke_a_mobile_device_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 2,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'approval_status' => User::APPROVAL_APPROVED,
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
            'is_active' => true,
        ]);

        $issueResponse = $this->actingAs($admin)->post(route('admin.devices.issue'), [
            'user_id' => $bhw->id,
            'device_name' => 'BHW Tablet',
        ]);

        $issueResponse->assertRedirect(route('admin.devices.index'));
        $issueResponse->assertSessionHas('issued_token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $token = PersonalAccessToken::firstOrFail();

        $revokeResponse = $this->actingAs($admin)->delete(route('admin.devices.revoke', $token->id));

        $revokeResponse->assertRedirect(route('admin.devices.index'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_can_archive_and_restore_a_household_with_residents(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 3,
        ]);
        $household = Household::create([
            'purok_id' => $purok->id,
            'household_no' => 'HH-001',
            'household_address' => 'Purok 3, Tubigon',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $resident = Resident::create([
            'household_id' => $household->id,
            'philsys_card_no' => 'PS-123',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'suffix' => null,
            'birth_date' => '1995-04-10',
            'birth_place' => 'Tubigon',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'contact_number' => '09170000000',
            'email_address' => 'maria@example.com',
            'relationship_to_head' => 'Head',
            'is_active' => true,
        ]);

        $archiveResponse = $this->actingAs($admin)->post(route('admin.archive.store'), [
            'table' => 'households',
            'record_id' => $household->id,
            'reason' => 'Regression test archive',
        ]);

        $archiveResponse->assertRedirect(route('admin.archive.index'));
        $archive = ArchivedRecord::query()->firstOrFail();
        $this->assertTrue(Household::withTrashed()->findOrFail($household->id)->trashed());
        $this->assertTrue(Resident::withTrashed()->findOrFail($resident->id)->trashed());

        $restoreResponse = $this->actingAs($admin)->patch(route('admin.archive.restore', $archive));

        $restoreResponse->assertRedirect(route('admin.archive.index'));
        $this->assertFalse(Household::withTrashed()->findOrFail($household->id)->trashed());
        $this->assertFalse(Resident::withTrashed()->findOrFail($resident->id)->trashed());
        $this->assertDatabaseMissing('archived_records', ['id' => $archive->id]);
    }

    public function test_user_exports_are_audited(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        User::factory()->create([
            'role' => 'bhw',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.export', ['format' => 'csv']));

        $response->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'exported',
            'event_description' => 'Exported user registry as CSV',
        ]);
    }
}
