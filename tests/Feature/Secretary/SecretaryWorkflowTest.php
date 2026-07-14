<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretaryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_can_issue_a_barangay_certificate_for_an_active_resident(): void
    {
        [$secretary, $resident] = $this->secretaryWithResident();

        $response = $this->actingAs($secretary)->post(route('secretary.certificates.store'), [
            'certificate_type' => 'barangay_clearance',
            'recipient_type' => 'resident',
            'resident_id' => $resident->id,
            'purpose' => 'Medical assistance application',
            'remarks' => 'Verified against active registry record',
            'issued_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $certificate = \App\Models\BarangayCertificate::query()->firstOrFail();

        $response->assertRedirect(route('secretary.certificates.show', $certificate));

        $this->assertSame($secretary->assigned_barangay_id, $certificate->barangay_id);
        $this->assertSame($resident->id, $certificate->resident_id);
        $this->assertSame($secretary->id, $certificate->issued_by_user_id);
        $this->assertNotEmpty($certificate->certificate_no);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'created',
            'model_type' => \App\Models\BarangayCertificate::class,
            'model_id' => $certificate->id,
            'user_id' => $secretary->id,
        ]);
    }

    public function test_secretary_can_relocate_a_resident_to_a_new_household_and_reassign_headship(): void
    {
        [$secretary, $resident] = $this->secretaryWithResident();
        $oldHousehold = $resident->household;
        $oldHousehold->update(['head_resident_id' => $resident->id]);

        $targetPurok = Purok::factory()->create([
            'barangay_id' => $secretary->assigned_barangay_id,
            'purok_number' => 9,
        ]);

        $response = $this->actingAs($secretary)
            ->patch(route('secretary.residents.relocate.update', $resident), [
                'target_purok_id' => $targetPurok->id,
                'destination' => 'new_household',
                'new_household_no' => '009-A',
                'new_household_address' => 'Sitio New Dawn',
                'set_as_household_head' => '1',
                'moved_in_at' => now()->format('Y-m-d'),
                'status_notes' => 'Transferred after internal purok movement.',
            ]);

        $resident->refresh();
        $oldHousehold->refresh();
        $newHousehold = Household::query()->where('household_no', '009-A')->firstOrFail();

        $response->assertRedirect(route('secretary.residents.show', $resident));

        $this->assertSame($newHousehold->id, $resident->household_id);
        $this->assertSame('Head of Household', $resident->relationship_to_head);
        $this->assertSame(Resident::STATUS_ACTIVE, $resident->resident_status);
        $this->assertTrue($resident->is_active);
        $this->assertNull($oldHousehold->head_resident_id);
        $this->assertSame($resident->id, $newHousehold->head_resident_id);
    }

    public function test_secretary_cannot_view_a_resident_from_another_barangay(): void
    {
        [$secretary] = $this->secretaryWithResident();
        $foreignBarangay = Barangay::factory()->create();
        $foreignPurok = Purok::factory()->create([
            'barangay_id' => $foreignBarangay->id,
            'purok_number' => 4,
        ]);
        $foreignHousehold = $this->createHousehold($foreignPurok, ['household_no' => '004']);
        $foreignResident = $this->createResident($foreignHousehold, ['first_name' => 'Foreign', 'last_name' => 'Resident']);

        $this->actingAs($secretary)
            ->get(route('secretary.residents.show', $foreignResident))
            ->assertForbidden();
    }

    public function test_secretary_activity_feed_only_shows_records_from_their_barangay_scope(): void
    {
        [$secretary, $resident] = $this->secretaryWithResident();

        $foreignBarangay = Barangay::factory()->create();
        $foreignPurok = Purok::factory()->create([
            'barangay_id' => $foreignBarangay->id,
            'purok_number' => 6,
        ]);
        $foreignHousehold = $this->createHousehold($foreignPurok, ['household_no' => '006']);
        $foreignResident = $this->createResident($foreignHousehold, ['first_name' => 'Out', 'last_name' => 'Scope']);

        AuditLog::create([
            'user_id' => $secretary->id,
            'event_type' => 'updated',
            'event_description' => 'Scoped resident updated',
            'model_type' => Resident::class,
            'model_id' => $resident->id,
        ]);

        AuditLog::create([
            'user_id' => $secretary->id,
            'event_type' => 'updated',
            'event_description' => 'Foreign resident updated',
            'model_type' => Resident::class,
            'model_id' => $foreignResident->id,
        ]);

        $response = $this->actingAs($secretary)->get(route('secretary.activity.index'));

        $response->assertOk();
        $response->assertSee('Scoped resident updated');
        $response->assertDontSee('Foreign resident updated');
    }

    private function secretaryWithResident(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 1,
        ]);
        $household = $this->createHousehold($purok);
        $resident = $this->createResident($household);
        $secretary = User::factory()->create([
            'role' => 'secretary',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => null,
        ]);

        return [$secretary, $resident];
    }

    private function createHousehold(Purok $purok, array $attributes = []): Household
    {
        return Household::query()->create(array_merge([
            'purok_id' => $purok->id,
            'household_no' => '001',
            'household_address' => 'Zone 1',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ], $attributes));
    }

    private function createResident(Household $household, array $attributes = []): Resident
    {
        return Resident::query()->create(array_merge([
            'household_id' => $household->id,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juana',
            'birth_date' => now()->subYears(32)->toDateString(),
            'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'relationship_to_head' => 'Daughter',
            'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => true,
        ], $attributes));
    }
}
