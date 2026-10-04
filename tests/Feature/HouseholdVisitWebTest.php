<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdVisitWebTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO_PATH = 'visit-photos/2026/10/00000000-0000-4000-8000-000000000901.jpg';

    private function context(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = Household::create([
            'purok_id' => $purok->id, 'household_no' => 'V-101',
            'household_address' => 'Purok Test', 'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id, 'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $secretary = User::factory()->create([
            'role' => 'secretary', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);
        $visit = FieldVisit::create([
            'mobile_uuid' => '00000000-0000-4000-8000-000000000902',
            'household_id' => $household->id, 'recorded_by_user_id' => $bhw->id,
            'visited_at' => '2026-10-02 09:30:00', 'notes' => 'Water source checked at home.',
            'photos' => [['path' => self::PHOTO_PATH, 'mime_type' => 'image/jpeg', 'file_name' => 'field.jpg']],
            'source' => 'mobile',
        ]);

        return [$secretary, $bhw, $household, $visit];
    }

    public function test_existing_visit_appears_in_secretary_household_history_and_detail_with_original_recorder(): void
    {
        [$secretary, $bhw, $household, $visit] = $this->context();

        $this->actingAs($secretary)->get(route('secretary.households.show', $household))
            ->assertOk()->assertSee('Household Visits')->assertSee('Water source checked at home.')
            ->assertSee($bhw->name)->assertSee(route('secretary.visits.show', $visit));
        $this->actingAs($secretary)->get(route('secretary.visits.show', $visit))
            ->assertOk()->assertSee('Water source checked at home.')
            ->assertSee($bhw->name)->assertSee('Photo unavailable');
        $this->assertSame($bhw->id, $visit->fresh()->recorded_by_user_id);
    }

    public function test_authorized_secretary_and_bhw_can_view_private_photo_without_exposing_its_path(): void
    {
        Storage::fake('local');
        [$secretary, $bhw, $household, $visit] = $this->context();
        Storage::disk('local')->put(self::PHOTO_PATH, 'private-image-bytes');

        $this->actingAs($secretary)->get(route('secretary.visits.show', $visit))
            ->assertOk()->assertSee(route('secretary.visits.photo', [$visit, 0]))
            ->assertDontSee(self::PHOTO_PATH);
        $photoResponse = $this->actingAs($secretary)->get(route('secretary.visits.photo', [$visit, 0]))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $photoResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $photoResponse->headers->get('Cache-Control'));
        $this->actingAs($bhw)->get(route('bhw.households.show', $household))
            ->assertOk()->assertSee(route('bhw.visits.show', $visit));
        $this->actingAs($bhw)->get(route('bhw.visits.photo', [$visit, 0]))->assertOk();
        $this->actingAs($bhw)->get(route('bhw.visits.photo', [$visit, 9]))->assertNotFound();
        $this->assertSame($bhw->id, $visit->fresh()->recorded_by_user_id);
    }

    public function test_other_barangay_and_other_purok_cannot_view_visit_or_photo(): void
    {
        Storage::fake('local');
        [, , $household, $visit] = $this->context();
        Storage::disk('local')->put(self::PHOTO_PATH, 'private-image-bytes');
        $otherBarangay = Barangay::factory()->create();
        $otherPurok = Purok::factory()->create(['barangay_id' => $otherBarangay->id]);
        $otherSecretary = User::factory()->create([
            'role' => 'secretary', 'assigned_barangay_id' => $otherBarangay->id,
        ]);
        $otherBhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $otherBarangay->id,
            'assigned_purok_id' => $otherPurok->id,
        ]);
        $sameBarangayOtherPurok = Purok::factory()->create(['barangay_id' => $household->purok->barangay_id]);
        $sameBarangayBhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $household->purok->barangay_id,
            'assigned_purok_id' => $sameBarangayOtherPurok->id,
        ]);
        $samePurokBhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $household->purok->barangay_id,
            'assigned_purok_id' => $household->purok_id,
        ]);
        $unassignedBhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $household->purok->barangay_id,
            'assigned_purok_id' => null,
        ]);

        $this->actingAs($otherSecretary)->get(route('secretary.visits.show', $visit))->assertForbidden();
        $this->actingAs($otherSecretary)->get(route('secretary.visits.photo', [$visit, 0]))->assertForbidden();
        $this->actingAs($otherBhw)->get(route('bhw.visits.show', $visit))->assertForbidden();
        $this->actingAs($otherBhw)->get(route('bhw.visits.photo', [$visit, 0]))->assertForbidden();
        $this->actingAs($sameBarangayBhw)->get(route('bhw.visits.show', $visit))->assertForbidden();
        $this->actingAs($sameBarangayBhw)->get(route('bhw.visits.photo', [$visit, 0]))->assertForbidden();
        $this->actingAs($samePurokBhw)->get(route('bhw.visits.show', $visit))->assertOk();
        $this->actingAs($samePurokBhw)->get(route('bhw.visits.photo', [$visit, 0]))->assertOk();
        $this->actingAs($unassignedBhw)->get(route('bhw.visits.show', $visit))->assertForbidden();
        $this->actingAs($unassignedBhw)->get(route('bhw.visits.photo', [$visit, 0]))->assertForbidden();
    }

    public function test_photo_path_cannot_be_used_to_access_another_private_file(): void
    {
        Storage::fake('local');
        [$secretary, , , $visit] = $this->context();
        Storage::disk('local')->put('private-secret.txt', 'not-a-visit-photo');
        $visit->update(['photos' => [['path' => '../private-secret.txt', 'mime_type' => 'text/plain']]]);

        $this->actingAs($secretary)->get(route('secretary.visits.show', $visit))
            ->assertOk()->assertSee('Photo unavailable')->assertDontSee('private-secret.txt');
        $this->actingAs($secretary)->get(route('secretary.visits.photo', [$visit, 0]))->assertNotFound();
        $this->actingAs($secretary)->get('/storage/private-secret.txt')->assertForbidden();
    }

    public function test_missing_photo_is_logged_and_does_not_break_visit_page(): void
    {
        Storage::fake('local');
        [$secretary, , , $visit] = $this->context();
        Log::spy();

        $this->actingAs($secretary)->get(route('secretary.visits.show', $visit))
            ->assertOk()->assertSee('Photo unavailable');
        $this->actingAs($secretary)->get(route('secretary.visits.photo', [$visit, 0]))->assertNotFound();
        Log::shouldHaveReceived('warning')->with('Household visit photo is unavailable.', [
            'field_visit_id' => $visit->id, 'photo_index' => 0,
        ]);
    }

    public function test_admin_has_read_only_municipal_visibility_but_bns_phn_mho_do_not_gain_visit_access(): void
    {
        Storage::fake('local');
        [, , $household, $visit] = $this->context();
        Storage::disk('local')->put(self::PHOTO_PATH, 'private-image-bytes');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.households.show', $household))
            ->assertOk()->assertSee(route('admin.visits.show', $visit));
        $this->actingAs($admin)->get(route('admin.visits.show', $visit))->assertOk();
        $this->actingAs($admin)->get(route('admin.visits.photo', [$visit, 0]))->assertOk();

        foreach (['bns', 'phn', 'mho'] as $role) {
            $user = User::factory()->create([
                'role' => $role, 'assigned_barangay_id' => $household->purok->barangay_id,
            ]);
            $this->assertFalse(Gate::forUser($user)->allows('view', $visit));
            $this->actingAs($user)->get(route('secretary.visits.show', $visit))->assertForbidden();
            $this->actingAs($user)->get(route('secretary.visits.photo', [$visit, 0]))->assertForbidden();
        }
    }

    public function test_visit_for_new_mobile_household_stays_pending_until_secretary_approval_then_appears(): void
    {
        [$secretary, $bhw] = $this->context();
        $householdUuid = '00000000-0000-4000-8000-000000000911';
        $visitUuid = '00000000-0000-4000-8000-000000000912';
        $visitPayload = [
            'mobile_uuid' => $visitUuid, 'household_mobile_uuid' => $householdUuid,
            'visited_at' => '2026-10-03T09:00:00+08:00', 'notes' => 'New household visit.',
        ];
        Sanctum::actingAs($bhw, ['mobile']);
        $this->postJson('/api/mobile/sync', [
            'households' => [[
                'mobile_uuid' => $householdUuid, 'household_no' => 'V-202',
                'household_address' => 'New home', 'is_social_aid_beneficiary' => false,
                'is_active' => true,
            ]],
            'field_visits' => [$visitPayload],
        ])->assertOk()->assertJsonPath('status', 'partial');
        $this->assertDatabaseMissing('households', ['mobile_uuid' => $householdUuid]);
        $this->assertDatabaseMissing('field_visits', ['mobile_uuid' => $visitUuid]);

        $draft = HouseholdDraft::where('mobile_uuid', $householdUuid)->firstOrFail();
        $this->actingAs($secretary)->patch(route('secretary.drafts.approve', $draft), [
            'purok_id' => $bhw->assigned_purok_id, 'household_no' => 'V-202',
            'household_address' => 'New home',
        ])->assertSessionHasNoErrors();

        Sanctum::actingAs($bhw, ['mobile']);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$visitPayload]])
            ->assertOk()->assertJsonPath('status', 'success');
        $household = Household::where('mobile_uuid', $householdUuid)->firstOrFail();
        $visit = FieldVisit::where('mobile_uuid', $visitUuid)->firstOrFail();
        $this->assertSame($household->id, $visit->household_id);
        $this->assertSame($bhw->id, $visit->recorded_by_user_id);
        $this->actingAs($secretary)->get(route('secretary.households.show', $household))
            ->assertOk()->assertSee('New household visit.');
    }
}
