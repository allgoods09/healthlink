<?php

namespace Tests\Feature\Mobile;

use App\Models\Barangay;
use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_verified_bhw_can_log_in_and_old_device_tokens_are_revoked(): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 1,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $bhw->createToken('Old Device', ['mobile']);

        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => $bhw->email,
            'password' => 'password',
            'device_name' => 'New Device',
            'device_platform' => 'android',
            'app_version' => '1.0.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('single_device_enforced', true)
            ->assertJsonPath('revoked_tokens', 1)
            ->assertJsonPath('user.id', $bhw->id);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame('New Device', $bhw->fresh()->tokens()->firstOrFail()->name);
    }

    public function test_unverified_bhw_cannot_log_in_to_mobile_app(): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 2,
        ]);
        $bhw = User::factory()->unverified()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => $bhw->email,
            'password' => 'password',
            'device_name' => 'Field Tablet',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Verify your email address on the web before signing in to the mobile app.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_bootstrap_returns_the_whole_assigned_barangay_but_excludes_other_barangays(): void
    {
        $barangay = Barangay::factory()->create();
        $foreignBarangay = Barangay::factory()->create();
        $assignedPurok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 3,
        ]);
        $otherPurok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 4,
        ]);
        $foreignBarangayPurok = Purok::factory()->create([
            'barangay_id' => $foreignBarangay->id,
            'purok_number' => 1,
        ]);
        $assignedHousehold = Household::create([
            'purok_id' => $assignedPurok->id,
            'household_no' => 'HH-301',
            'household_address' => 'Assigned address',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $sameBarangayHousehold = Household::create([
            'purok_id' => $otherPurok->id,
            'household_no' => 'HH-401',
            'household_address' => 'Same barangay address',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $foreignHousehold = Household::create([
            'purok_id' => $foreignBarangayPurok->id,
            'household_no' => 'HH-901',
            'household_address' => 'Foreign barangay address',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $assignedResident = Resident::create([
            'household_id' => $assignedHousehold->id,
            'philsys_card_no' => 'PS-301',
            'last_name' => 'Perez',
            'first_name' => 'Luna',
            'middle_name' => 'Mae',
            'suffix' => null,
            'birth_date' => '1999-01-01',
            'birth_place' => 'Tubigon',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'contact_number' => '09171111111',
            'email_address' => 'luna@example.com',
            'relationship_to_head' => 'Head',
            'is_active' => true,
        ]);
        $sameBarangayResident = Resident::create([
            'household_id' => $sameBarangayHousehold->id,
            'philsys_card_no' => 'PS-401',
            'last_name' => 'Lopez',
            'first_name' => 'Mico',
            'middle_name' => 'Diaz',
            'suffix' => null,
            'birth_date' => '1998-02-02',
            'birth_place' => 'Tubigon',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'contact_number' => '09172222222',
            'email_address' => 'mico@example.com',
            'relationship_to_head' => 'Head',
            'is_active' => true,
        ]);
        Resident::create([
            'household_id' => $foreignHousehold->id,
            'philsys_card_no' => 'PS-901',
            'last_name' => 'Rivera',
            'first_name' => 'Dina',
            'middle_name' => 'Santos',
            'suffix' => null,
            'birth_date' => '1997-02-02',
            'birth_place' => 'Tubigon',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'contact_number' => '09174444444',
            'email_address' => 'dina@example.com',
            'relationship_to_head' => 'Head',
            'is_active' => true,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $assignedPurok->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        FieldVisit::create([
            'mobile_uuid' => (string) fake()->uuid(),
            'household_id' => $assignedHousehold->id,
            'recorded_by_user_id' => $bhw->id,
            'visited_at' => now(),
            'notes' => 'Assigned visit',
            'photos' => [],
            'source' => 'mobile',
            'last_synced_at' => now(),
        ]);
        FieldVisit::create([
            'mobile_uuid' => (string) fake()->uuid(),
            'household_id' => $sameBarangayHousehold->id,
            'recorded_by_user_id' => $bhw->id,
            'visited_at' => now()->subDay(),
            'notes' => 'Same barangay visit',
            'photos' => [],
            'source' => 'mobile',
            'last_synced_at' => now(),
        ]);
        FieldVisit::create([
            'mobile_uuid' => (string) fake()->uuid(),
            'household_id' => $foreignHousehold->id,
            'recorded_by_user_id' => $bhw->id,
            'visited_at' => now()->subDays(2),
            'notes' => 'Foreign barangay visit',
            'photos' => [],
            'source' => 'mobile',
            'last_synced_at' => now(),
        ]);

        Sanctum::actingAs($bhw, ['mobile']);

        $response = $this->getJson('/api/mobile/bootstrap');

        $response->assertOk()
            ->assertJsonCount(2, 'households')
            ->assertJsonCount(2, 'residents')
            ->assertJsonCount(2, 'field_visits')
            ->assertJsonPath('sync.supports_auto_upload_when_online', false);

        $payload = $response->json();

        $this->assertEqualsCanonicalizing(
            [$assignedHousehold->id, $sameBarangayHousehold->id],
            collect($payload['households'])->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$assignedResident->id, $sameBarangayResident->id],
            collect($payload['residents'])->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$assignedHousehold->id, $sameBarangayHousehold->id],
            collect($payload['field_visits'])->pluck('household_id')->all()
        );
        $this->assertNotContains($foreignHousehold->id, collect($payload['households'])->pluck('id')->all());
    }

    public function test_mobile_sync_still_rejects_writes_outside_the_assigned_purok(): void
    {
        $barangay = Barangay::factory()->create();
        $assignedPurok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 5,
        ]);
        $otherPurok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 6,
        ]);
        $foreignHousehold = Household::create([
            'purok_id' => $otherPurok->id,
            'household_no' => 'HH-611',
            'household_address' => 'Other purok address',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $assignedPurok->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($bhw, ['mobile']);

        $response = $this->postJson('/api/mobile/sync', [
            'field_visits' => [
                [
                    'mobile_uuid' => (string) fake()->uuid(),
                    'household_id' => $foreignHousehold->id,
                    'visited_at' => now()->toIso8601String(),
                    'notes' => 'Attempted out-of-scope visit',
                ],
            ],
            'device_name' => 'Field Tablet',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('records_synced', 0)
            ->assertJsonPath('failed_records.0.collection', 'field_visits')
            ->assertJsonPath('failed_records.0.message', 'Visit household not found in the assigned purok.');

        $this->assertDatabaseCount('field_visits', 0);
    }

    public function test_mobile_sync_can_create_household_resident_and_field_visit_using_mobile_uuids(): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 5,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $householdUuid = (string) fake()->uuid();
        $residentUuid = (string) fake()->uuid();
        $visitUuid = (string) fake()->uuid();

        Sanctum::actingAs($bhw, ['mobile']);

        $response = $this->postJson('/api/mobile/sync', [
            'households' => [
                [
                    'mobile_uuid' => $householdUuid,
                    'household_no' => 'HH-501',
                    'household_address' => 'Purok 5, Tubigon',
                    'is_social_aid_beneficiary' => false,
                    'is_active' => true,
                ],
            ],
            'residents' => [
                [
                    'mobile_uuid' => $residentUuid,
                    'household_mobile_uuid' => $householdUuid,
                    'last_name' => 'Santos',
                    'first_name' => 'Mira',
                    'middle_name' => 'Diaz',
                    'suffix' => null,
                    'birth_date' => '1994-03-12',
                    'birth_place' => 'Tubigon',
                    'sex' => 'Female',
                    'civil_status' => 'Single',
                    'citizenship' => 'Filipino',
                    'religion' => 'Catholic',
                    'contact_number' => '09173333333',
                    'email_address' => 'mira@example.com',
                    'relationship_to_head' => 'Head',
                    'is_active' => true,
                ],
            ],
            'field_visits' => [
                [
                    'mobile_uuid' => $visitUuid,
                    'household_mobile_uuid' => $householdUuid,
                    'visited_at' => now()->toIso8601String(),
                    'notes' => 'Initial household visit',
                    'photos' => [
                        [
                            'file_name' => 'visit.jpg',
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode('visit-photo-data'),
                            'captured_at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ],
            'device_name' => 'Field Tablet',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('records_synced', 3)
            ->assertJsonPath('resolved_records.households.0.mobile_uuid', $householdUuid)
            ->assertJsonPath('resolved_records.residents.0.mobile_uuid', $residentUuid)
            ->assertJsonPath('resolved_records.field_visits.0.mobile_uuid', $visitUuid);

        $household = Household::where('mobile_uuid', $householdUuid)->firstOrFail();
        $resident = Resident::where('mobile_uuid', $residentUuid)->firstOrFail();
        $visit = FieldVisit::where('mobile_uuid', $visitUuid)->firstOrFail();

        $this->assertSame($purok->id, $household->purok_id);
        $this->assertSame($household->id, $resident->household_id);
        $this->assertSame($household->id, $visit->household_id);
        $this->assertCount(1, $visit->photos ?? []);
    }

    public function test_mobile_forgot_password_sends_a_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/mobile/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
