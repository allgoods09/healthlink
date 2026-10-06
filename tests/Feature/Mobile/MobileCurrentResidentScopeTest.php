<?php

namespace Tests\Feature\Mobile;

use App\Models\Household;
use App\Models\PhilpenRiskAssessment;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileCurrentResidentScopeTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $purok = Purok::factory()->create();
        $user = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $purok->barangay_id,
            'assigned_purok_id' => $purok->id]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => '1',
            'household_address' => 'Synthetic scope fixture', 'is_active' => true]);
        return [$user, $purok, $household];
    }

    private function resident(Household $household, string $name, array $overrides = []): Resident
    {
        return Resident::create(array_replace(['mobile_uuid' => (string) Str::uuid(), 'household_id' => $household->id, 'first_name' => $name,
            'last_name' => 'Synthetic', 'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon',
            'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Child', 'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true], $overrides));
    }

    public function test_bootstrap_is_current_assigned_purok_only_without_availability_filter_or_history_mutation(): void
    {
        [$user, $purok, $household] = $this->team();
        $active = $this->resident($household, 'Active');
        $unavailable = $this->resident($household, 'Unavailable', ['is_active' => false]);
        $excluded = [];
        foreach ([Resident::STATUS_DECEASED, Resident::STATUS_MOVED_OUT, Resident::STATUS_RELOCATED] as $status) {
            $excluded[] = $this->resident($household, $status, ['resident_status' => $status]);
        }
        $deleted = $this->resident($household, 'Deleted');
        $deleted->delete();
        foreach ([Purok::factory()->create(['barangay_id' => $purok->barangay_id, 'purok_number' => 99]),
            Purok::factory()->create()] as $otherPurok) {
            $otherHousehold = Household::create(['purok_id' => $otherPurok->id, 'household_no' => '1',
                'household_address' => 'Outside scope', 'is_active' => true]);
            $excluded[] = $this->resident($otherHousehold, 'Other'.$otherPurok->id);
        }
        $assessment = PhilpenRiskAssessment::create(['resident_id' => $excluded[0]->id,
            'mobile_uuid' => (string) Str::uuid(),
            'barangay_id' => $purok->barangay_id, 'recorded_by_user_id' => $user->id,
            'assessment_date' => '2026-07-01', 'age_years' => 36, 'source' => 'mobile']);
        $before = Resident::withTrashed()->get()->toArray();
        Sanctum::actingAs($user, ['mobile']);
        $response = $this->getJson('/api/mobile/bootstrap')->assertOk()
            ->assertJsonPath('resident_contract_version', 1)->assertJsonCount(2, 'residents');
        $rows = collect($response->json('residents'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$active->id, $unavailable->id], $rows->keys()->all());
        $this->assertFalse($rows[$unavailable->id]['is_active']);
        foreach ($rows as $row) {
            $this->assertSame('active', $row['resident_status']);
            $this->assertNull($row['deleted_at']);
        }
        $this->assertContains($assessment->id, collect($response->json('risk_assessments'))->pluck('id'));
        $this->assertSame($before, Resident::withTrashed()->get()->toArray());
        $this->assertSame($excluded[0]->id, $assessment->fresh()->resident_id);
    }

    public static function invalidPuroks(): array
    {
        return array_map(fn ($state) => [$state], ['missing', 'invalid', 'inactive', 'deleted', 'foreign']);
    }

    #[DataProvider('invalidPuroks')]
    public function test_unusable_purok_fails_closed_at_bootstrap_verify_and_sync(string $state): void
    {
        [$user, $purok] = $this->team();
        match ($state) {
            'missing' => $user->update(['assigned_purok_id' => null]),
            'invalid' => $user->forceFill(['assigned_purok_id' => 99999999]),
            'inactive' => $purok->update(['is_active' => false]),
            'deleted' => $purok->delete(),
            'foreign' => $user->update(['assigned_purok_id' => Purok::factory()->create()->id]),
        };
        Sanctum::actingAs($user, ['mobile']);
        foreach (['bootstrap', 'seed', 'verify'] as $endpoint) {
            $this->getJson('/api/mobile/'.$endpoint)->assertForbidden()->assertJsonMissingPath('residents');
        }
        $this->postJson('/api/mobile/sync', [])->assertForbidden();
        $this->assertDatabaseCount('sync_logs', 0);
    }

    public function test_inactive_or_foreign_purok_cannot_log_in_and_issue_a_token(): void
    {
        [$user, $purok] = $this->team();
        $purok->update(['is_active' => false]);
        $this->postJson('/api/mobile/auth/login', ['email' => $user->email, 'password' => 'password',
            'device_name' => 'Test'])->assertForbidden();
        $purok->update(['is_active' => true]);
        $user->update(['assigned_purok_id' => Purok::factory()->create()->id]);
        $this->postJson('/api/mobile/auth/login', ['email' => $user->email, 'password' => 'password',
            'device_name' => 'Test'])->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_current_correction_scope_is_authoritative_and_does_not_directly_update_residents(): void
    {
        [$user, $purok, $household] = $this->team();
        $current = $this->resident($household, 'Current', ['is_active' => false]);
        $historical = $this->resident($household, 'Historical', ['resident_status' => Resident::STATUS_MOVED_OUT]);
        $deleted = $this->resident($household, 'Deleted');
        $deleted->delete();
        $otherHousehold = Household::create(['purok_id' => Purok::factory()->create([
            'barangay_id' => $purok->barangay_id, 'purok_number' => 99])->id,
            'household_no' => '1', 'household_address' => 'Other scope']);
        $other = $this->resident($otherHousehold, 'Other');
        Sanctum::actingAs($user, ['mobile']);
        foreach ([$historical, $other] as $denied) {
            $this->postJson('/api/mobile/sync', ['residents' => [['id' => $denied->id,
                'household_id' => $denied->household_id, 'first_name' => 'Denied', 'local_revision' => 1]]])
                ->assertOk()->assertJsonPath('status', 'failed');
        }
        $this->assertDatabaseCount('profile_update_requests', 0);
        $this->postJson('/api/mobile/sync', ['residents' => [['mobile_uuid' => $deleted->mobile_uuid,
            'household_id' => $household->id, 'first_name' => 'Denied', 'local_revision' => 1]]])
            ->assertOk()->assertJsonPath('status', 'failed');
        $this->assertDatabaseCount('resident_drafts', 0);
        $this->postJson('/api/mobile/sync', ['residents' => [['id' => $current->id,
            'household_id' => $household->id, 'first_name' => 'Proposed', 'local_revision' => 1]]])
            ->assertOk()->assertJsonPath('resolved_records.residents.0.verification_status', 'submitted');
        $this->assertSame('Current', $current->fresh()->first_name);
        $this->assertSame($current->id, ProfileUpdateRequest::sole()->subject_id);
    }
}
