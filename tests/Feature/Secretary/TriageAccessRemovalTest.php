<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TriageAccessRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_navigation_and_dashboard_do_not_expose_triage(): void
    {
        [$secretary, , , $triageAudit, $registryAudit] = $this->context();

        $response = $this->actingAs($secretary)->get(route('secretary.dashboard'));
        $response->assertOk()
            ->assertDontSee('Pending Triage')
            ->assertDontSee('/secretary/triage-queue')
            ->assertDontSee('Confidential triage sentinel')
            ->assertViewMissing('pendingTriageQueue')
            ->assertViewHas('activeResidents', 1)
            ->assertViewHas('pendingDraftPackages', 0)
            ->assertViewHas('pendingUpdateRequests', 0)
            ->assertViewHas('recentActivity', fn ($logs) =>
                $logs->contains('id', $registryAudit->id) && ! $logs->contains('id', $triageAudit->id));
    }

    public function test_former_secretary_triage_routes_and_clinical_role_urls_are_inaccessible(): void
    {
        [$secretary, , $triage] = $this->context();
        $this->actingAs($secretary);

        foreach (['index', 'show', 'export'] as $action) {
            $this->assertFalse(Route::has('secretary.triage.'.$action));
        }

        foreach (['', '/'.$triage->id, '/export/csv', '/export/xlsx', '/export/pdf'] as $suffix) {
            $this->get('/secretary/triage-queue'.$suffix)->assertNotFound();
        }

        $this->get(route('phn.triage.show', $triage))->assertForbidden();
        $this->get(route('bhw.triage.show', $triage))->assertForbidden();
        $this->assertDatabaseHas('triage_records', ['id' => $triage->id]);
    }

    public function test_secretary_activity_listing_search_and_detail_exclude_triage_audits(): void
    {
        [$secretary, , , $triageAudit, $registryAudit] = $this->context();
        $this->actingAs($secretary)->get(route('secretary.activity.index'))
            ->assertOk()->assertSee('Registry sentinel')->assertDontSee('Confidential triage sentinel')
            ->assertViewHas('logs', fn ($logs) => ! $logs->contains('id', $triageAudit->id));

        foreach (['Confidential triage sentinel', 'TriageRecord'] as $search) {
            $this->get(route('secretary.activity.index', ['search' => $search]))
                ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
        }

        $this->get(route('secretary.activity.show', $triageAudit))->assertNotFound();
        $this->get(route('secretary.activity.show', $registryAudit))->assertOk();
        $this->get(route('admin.audit.show', $triageAudit))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['id' => $triageAudit->id]);
    }

    public function test_secretary_activity_exports_use_the_same_non_clinical_scope(): void
    {
        [$secretary] = $this->context();
        $this->actingAs($secretary);

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            foreach ([null, 'TriageRecord'] as $search) {
                $response = $this->get(route('secretary.activity.export', array_filter([
                    'format' => $format, 'search' => $search,
                ])))->assertOk();
                $export = AuditLog::query()->where('event_type', 'exported')->latest('id')->firstOrFail();
                $this->assertSame($search ? 0 : 1, $export->metadata['record_count']);
                if ($format === 'csv') {
                    $this->assertStringNotContainsString('Confidential triage sentinel', $response->streamedContent());
                    if (! $search) {
                        $this->assertStringContainsString('Registry sentinel', $response->streamedContent());
                    }
                }
            }
        }
    }

    public function test_admin_retains_original_triage_audit_history_and_values(): void
    {
        [, , $triage, $triageAudit] = $this->context();
        $original = $triage->fresh()->getRawOriginal();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.audit.index', ['search' => 'Confidential triage sentinel']))
            ->assertOk()->assertSee('Confidential triage sentinel');
        $this->get(route('admin.audit.show', $triageAudit))->assertOk()
            ->assertSee('Private clinical note')->assertSee('bp_systolic');
        $this->assertSame($original, $triage->fresh()->getRawOriginal());
        $this->assertDatabaseCount('triage_records', 1);
        $this->assertSame('Private clinical note', $triageAudit->fresh()->new_values['triage_notes']);
    }

    public function test_bhw_capture_edit_and_phn_review_consumption_and_notifications_still_work(): void
    {
        [$secretary, $bhw, $existing] = $this->context();
        $phn = User::factory()->create(['role' => 'phn']);
        $payload = [
            'resident_id' => $existing->resident_id,
            'measured_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'bp_systolic' => 120,
            'bp_diastolic' => 80,
            'triage_notes' => 'Clinical workflow sentinel',
        ];
        $this->actingAs($bhw)->post(route('bhw.triage.store'), $payload)->assertSessionHasNoErrors();
        $triage = TriageRecord::query()->latest('id')->firstOrFail();
        $this->assertNotSame($existing->id, $triage->id);
        $this->assertSame(TriageRecord::STATUS_PENDING, $triage->triage_status);
        $this->assertTrue($phn->notifications()->get()->contains(fn ($notification) =>
            ($notification->data['category'] ?? null) === 'triage'));
        $this->assertFalse($secretary->notifications()->get()->contains(fn ($notification) =>
            ($notification->data['category'] ?? null) === 'triage'));

        $payload['triage_notes'] = 'Corrected clinical sentinel';
        $this->get(route('bhw.triage.edit', $triage))->assertOk();
        $this->put(route('bhw.triage.update', $triage), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('bhw.triage.show', $triage));
        $this->assertSame('Corrected clinical sentinel', $triage->fresh()->triage_notes);
        $this->get(route('bhw.triage.export', ['format' => 'csv']))->assertOk();

        $this->actingAs($phn)->get(route('phn.triage.index'))->assertOk()->assertSee('Pending Triage');
        $this->get(route('phn.triage.show', $triage))->assertOk()->assertSee('Corrected clinical sentinel');
        $this->get(route('phn.triage.export', ['format' => 'csv']))->assertOk();
        $this->post(route('phn.encounters.store'), [
            'resident_id' => $triage->resident_id,
            'triage_record_id' => $triage->id,
            'encountered_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'consultation_notes' => 'PHN assessment sentinel',
            'follow_up_date' => now()->addWeek()->toDateString(),
            'follow_up_status' => ClinicalEncounter::FOLLOW_UP_DUE,
        ])->assertSessionHasNoErrors();
        $encounter = ClinicalEncounter::query()->where('triage_record_id', $triage->id)->firstOrFail();
        $this->assertSame(ClinicalEncounter::SOURCE_TRIAGE, $encounter->encounter_source);
        $this->assertSame($phn->id, $triage->fresh()->consumed_by_user_id);
        $this->assertNotNull($triage->fresh()->consumed_at);
        $this->assertSame(TriageRecord::STATUS_REVIEWED, $triage->fresh()->triage_status);
        $this->actingAs($bhw)->get(route('bhw.triage.edit', $triage))->assertForbidden();
        $encounter->update(['closed_at' => now()]);
        $this->assertSame(TriageRecord::STATUS_CLOSED, $triage->fresh()->triage_status);
    }

    private function context(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1]);
        $household = Household::query()->create([
            'purok_id' => $purok->id, 'household_no' => '001', 'is_active' => true,
            'household_address' => 'Fixture address',
        ]);
        $resident = Resident::query()->create([
            'household_id' => $household->id, 'first_name' => 'Registry', 'last_name' => 'Fixture',
            'birth_date' => now()->subYears(30)->toDateString(), 'sex' => 'Female',
            'birth_place' => 'Tubigon, Bohol',
            'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Head of Household',
            'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true,
        ]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $bhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => $purok->id,
        ]);
        $triage = TriageRecord::query()->create([
            'resident_id' => $resident->id, 'household_id' => $household->id,
            'barangay_id' => $barangay->id, 'purok_id' => $purok->id,
            'recorded_by_user_id' => $bhw->id, 'triage_status' => TriageRecord::STATUS_PENDING,
            'measured_at' => now(), 'bp_systolic' => 120, 'bp_diastolic' => 80,
            'triage_notes' => 'Private clinical note',
        ]);
        $triageAudit = AuditLog::query()->create([
            'user_id' => $bhw->id, 'event_type' => 'updated',
            'event_description' => 'Confidential triage sentinel',
            'model_type' => TriageRecord::class, 'model_id' => $triage->id,
            'old_values' => ['bp_systolic' => 110],
            'new_values' => ['bp_systolic' => 120, 'triage_notes' => 'Private clinical note'],
        ]);
        $registryAudit = AuditLog::query()->create([
            'user_id' => $bhw->id, 'event_type' => 'updated', 'event_description' => 'Registry sentinel',
            'model_type' => Resident::class, 'model_id' => $resident->id,
        ]);

        return [$secretary, $bhw, $triage, $triageAudit, $registryAudit];
    }
}
