<?php

namespace Tests\Feature\Bhw;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhwTriageEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_renders_with_the_same_scoped_active_resident_options_as_create(): void
    {
        [$bhw, $triage, $purok] = $this->context();
        $otherPurok = Purok::factory()->create(['barangay_id' => $purok->barangay_id, 'purok_number' => 2]);
        $sameBarangay = $this->resident($otherPurok, 'AllowedOtherPurok');
        $foreignPurok = Purok::factory()->create(['barangay_id' => Barangay::factory()->create()->id]);
        $foreign = $this->resident($foreignPurok, 'ForeignResidentSentinel');
        $inactive = $this->resident($purok, 'InactiveResidentSentinel');
        $inactive->update(['is_active' => false]);

        $this->actingAs($bhw);
        $create = $this->get(route('bhw.triage.create'))->assertOk();
        $edit = $this->get(route('bhw.triage.edit', $triage))->assertOk()
            ->assertSee(route('bhw.triage.update', $triage), false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('Original triage note')
            ->assertDontSee('ForeignResidentSentinel')
            ->assertDontSee('InactiveResidentSentinel')
            ->assertDontSee('name="resident_id"', false);

        $options = $edit->viewData('residentOptions');
        $this->assertEqualsCanonicalizing([$triage->resident_id, $sameBarangay->id], $options->modelKeys());
        $this->assertSame($create->viewData('residentOptions')->modelKeys(), $options->modelKeys());
        $this->assertFalse($options->contains('id', $foreign->id));
        $this->assertFalse($options->contains('id', $inactive->id));
    }

    public function test_edit_still_rejects_another_recorder_and_another_barangay(): void
    {
        [$bhw, $triage, $purok] = $this->context();
        $otherRecorder = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $purok->barangay_id,
            'assigned_purok_id' => $purok->id,
        ]);
        $this->actingAs($otherRecorder)->get(route('bhw.triage.edit', $triage))->assertNotFound();
        $bhw->update(['assigned_barangay_id' => Barangay::factory()->create()->id, 'assigned_purok_id' => null]);
        $this->actingAs($bhw)->get(route('bhw.triage.edit', $triage))->assertNotFound();
    }

    public function test_consumed_triage_remains_uneditable_and_unchanged(): void
    {
        [$bhw, $triage] = $this->context();
        $triage->update(['consumed_at' => now(), 'consumed_by_user_id' => User::factory()->create(['role' => 'phn'])->id]);
        $this->actingAs($bhw)->get(route('bhw.triage.edit', $triage))->assertForbidden();
        $this->put(route('bhw.triage.update', $triage), [
            'resident_id' => $triage->resident_id,
            'measured_at' => $triage->measured_at->format('Y-m-d H:i:s'),
            'triage_notes' => 'Unauthorized correction',
        ])->assertForbidden();
        $this->assertSame('Original triage note', $triage->fresh()->triage_notes);
    }

    private function context(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1]);
        $resident = $this->resident($purok, 'AssignedResident');
        $bhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => $purok->id,
        ]);
        $triage = TriageRecord::query()->create([
            'resident_id' => $resident->id, 'household_id' => $resident->household_id,
            'barangay_id' => $barangay->id, 'purok_id' => $purok->id, 'recorded_by_user_id' => $bhw->id,
            'triage_status' => TriageRecord::STATUS_PENDING, 'measured_at' => now()->subMinute(),
            'triage_notes' => 'Original triage note',
        ]);

        return [$bhw, $triage, $purok];
    }

    private function resident(Purok $purok, string $name): Resident
    {
        $household = Household::query()->create([
            'purok_id' => $purok->id, 'household_no' => $name,
            'household_address' => 'Fixture address', 'is_active' => true,
        ]);

        return Resident::query()->create([
            'household_id' => $household->id, 'first_name' => $name, 'last_name' => 'Fixture',
            'birth_date' => now()->subYears(30)->toDateString(), 'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Head of Household',
            'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true,
        ]);
    }
}
