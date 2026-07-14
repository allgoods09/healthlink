<?php

namespace Tests\Feature\Admin;

use App\Models\ArchivedRecord;
use App\Models\Backup;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\RateLimitState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminIntegrityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->flush();
        $this->clearBackupDirectory();
    }

    public function test_restoring_an_archived_household_recursively_restores_its_archived_parent(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 5,
        ]);
        $household = Household::create([
            'purok_id' => $purok->id,
            'household_no' => 'HH-500',
            'household_address' => 'Purok 5, Tubigon',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $resident = Resident::create([
            'household_id' => $household->id,
            'philsys_card_no' => 'PS-500',
            'last_name' => 'Rivera',
            'first_name' => 'Juan',
            'middle_name' => 'Lopez',
            'suffix' => null,
            'birth_date' => '1990-06-01',
            'birth_place' => 'Tubigon',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'contact_number' => '09171111111',
            'email_address' => 'juan@example.com',
            'relationship_to_head' => 'Head',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.archive.store'), [
            'table' => 'households',
            'record_id' => $household->id,
            'reason' => 'Archive household first',
        ]);

        $this->actingAs($admin)->post(route('admin.archive.store'), [
            'table' => 'puroks',
            'record_id' => $purok->id,
            'reason' => 'Archive purok second',
        ]);

        $householdArchive = ArchivedRecord::query()
            ->where('original_table', 'households')
            ->where('original_id', $household->id)
            ->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.archive.restore', $householdArchive));

        $response->assertRedirect(route('admin.archive.index'));
        $this->assertFalse(Household::withTrashed()->findOrFail($household->id)->trashed());
        $this->assertFalse(Purok::withTrashed()->findOrFail($purok->id)->trashed());
        $this->assertFalse(Resident::withTrashed()->findOrFail($resident->id)->trashed());
        $this->assertDatabaseMissing('archived_records', [
            'original_table' => 'households',
            'original_id' => $household->id,
        ]);
        $this->assertDatabaseMissing('archived_records', [
            'original_table' => 'puroks',
            'original_id' => $purok->id,
        ]);
    }

    public function test_archiving_a_household_is_blocked_when_individually_archived_residents_exist(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 6,
        ]);
        $household = Household::create([
            'purok_id' => $purok->id,
            'household_no' => 'HH-600',
            'household_address' => 'Purok 6, Tubigon',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $resident = Resident::create([
            'household_id' => $household->id,
            'philsys_card_no' => 'PS-600',
            'last_name' => 'Santos',
            'first_name' => 'Ana',
            'middle_name' => 'Diaz',
            'suffix' => null,
            'birth_date' => '1992-08-12',
            'birth_place' => 'Tubigon',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'contact_number' => '09172222222',
            'email_address' => 'ana@example.com',
            'relationship_to_head' => 'Child',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.archive.store'), [
            'table' => 'residents',
            'record_id' => $resident->id,
            'reason' => 'Resident archived first',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.archive.create'))
            ->post(route('admin.archive.store'), [
                'table' => 'households',
                'record_id' => $household->id,
                'reason' => 'Attempt household archive',
            ]);

        $response->assertRedirect(route('admin.archive.create'));
        $response->assertSessionHas('error', 'Households with individually archived residents must be resolved before archiving the household.');
        $this->assertFalse(Household::withTrashed()->findOrFail($household->id)->trashed());
    }

    public function test_purging_a_parent_archive_is_blocked_when_dependent_archives_still_exist(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 7,
        ]);
        $household = Household::create([
            'purok_id' => $purok->id,
            'household_no' => 'HH-700',
            'household_address' => 'Purok 7, Tubigon',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.archive.store'), [
            'table' => 'households',
            'record_id' => $household->id,
            'reason' => 'Archive child first',
        ]);

        $this->actingAs($admin)->post(route('admin.archive.store'), [
            'table' => 'puroks',
            'record_id' => $purok->id,
            'reason' => 'Archive parent second',
        ]);

        $purokArchive = ArchivedRecord::query()
            ->where('original_table', 'puroks')
            ->where('original_id', $purok->id)
            ->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.archive.index'))
            ->delete(route('admin.archive.purge', $purokArchive));

        $response->assertRedirect(route('admin.archive.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('archived_records', [
            'id' => $purokArchive->id,
            'is_purged' => false,
        ]);
    }

    public function test_mobile_sync_marks_partial_failures_and_keeps_valid_updates(): void
    {
        $barangay = Barangay::factory()->create();
        $assignedPurok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 8,
        ]);
        $otherPurok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 9,
        ]);
        $validHousehold = Household::create([
            'purok_id' => $assignedPurok->id,
            'household_no' => 'HH-800',
            'household_address' => 'Old address',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $foreignResidentHousehold = Household::create([
            'purok_id' => $otherPurok->id,
            'household_no' => 'HH-900',
            'household_address' => 'Foreign address',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $foreignResident = Resident::create([
            'household_id' => $foreignResidentHousehold->id,
            'philsys_card_no' => 'PS-900',
            'last_name' => 'Garcia',
            'first_name' => 'Lia',
            'middle_name' => 'Mae',
            'suffix' => null,
            'birth_date' => '1991-01-15',
            'birth_place' => 'Tubigon',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'contact_number' => '09173333333',
            'email_address' => 'lia@example.com',
            'relationship_to_head' => 'Child',
            'is_active' => true,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $assignedPurok->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($bhw, ['mobile']);

        $response = $this->postJson('/api/mobile/sync', [
            'households' => [
                [
                    'id' => $validHousehold->id,
                    'household_address' => 'Updated from mobile',
                ],
            ],
            'residents' => [
                [
                    'id' => $foreignResident->id,
                    'contact_number' => '09999999999',
                ],
            ],
            'device_name' => 'Field Tablet',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', SyncLog::STATUS_PARTIAL)
            ->assertJsonPath('records_synced', 1);

        $this->assertSame('Updated from mobile', $validHousehold->fresh()->household_address);
        $this->assertSame('09173333333', $foreignResident->fresh()->contact_number);
        $this->assertDatabaseHas('sync_logs', [
            'status' => SyncLog::STATUS_PARTIAL,
            'records_synced' => 1,
        ]);
    }

    public function test_dashboard_surfaces_operational_alerts_for_admins(): void
    {
        Setting::setValue('api_rate_limit_auth', 1);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $pendingUser = User::factory()->create([
            'role' => 'bhw',
            'approval_status' => User::APPROVAL_PENDING,
            'is_active' => false,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
        ]);

        $token = $bhw->createToken('Old Device');
        $tokenModel = $bhw->tokens()->firstOrFail();
        $tokenModel->forceFill([
            'last_used_at' => now()->subDays(45),
            'created_at' => now()->subDays(60),
        ])->save();

        Backup::factory()->create([
            'generated_by' => $admin->id,
            'status' => Backup::STATUS_COMPLETED,
            'metadata' => ['integrity_status' => 'unverified'],
            'expires_at' => now()->subDay(),
        ]);

        SyncLog::create([
            'user_id' => $bhw->id,
            'device_name' => 'Field Tablet',
            'device_model' => 'RN',
            'app_version' => '1.0.0',
            'records_synced' => 0,
            'payload_size' => 120,
            'sync_duration' => 100,
            'status' => SyncLog::STATUS_FAILED,
            'error_message' => 'Upload failed',
            'ip_address' => '127.0.0.1',
            'network_type' => 'wifi',
            'sync_metadata' => ['failure_count' => 1],
        ]);

        RateLimitState::trackAuthKey('blocked-key', $pendingUser->email, '127.0.0.1');
        RateLimiter::hit('blocked-key', 60);
        RateLimiter::hit('blocked-key', 60);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Pending approvals need review')
            ->assertSee('Recent mobile sync issues detected')
            ->assertSee('Stale device tokens need review')
            ->assertSee('Backup hygiene needs attention')
            ->assertSee('Blocked rate-limit keys are active');
    }

    private function clearBackupDirectory(): void
    {
        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);

            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
