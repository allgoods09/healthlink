<?php

namespace Tests\Feature\Admin;

use App\Models\Barangay;
use App\Models\HouseholdDraft;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMunicipalOversightTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_command_center_and_oversight_monitors_render(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Municipal Command Center');

        $this->actingAs($admin)
            ->get(route('admin.oversight.field'))
            ->assertOk()
            ->assertSee('Field Operations Monitor');

        $this->actingAs($admin)
            ->get(route('admin.oversight.nutrition'))
            ->assertOk()
            ->assertSee('Nutrition Oversight');

        $this->actingAs($admin)
            ->get(route('admin.oversight.clinical'))
            ->assertOk()
            ->assertSee('Clinical Oversight');
    }

    public function test_every_oversight_panel_has_a_named_export_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $groups = [
            'field' => ['pending-drafts', 'pending-requests', 'reviewed-drafts', 'reviewed-requests'],
            'nutrition' => ['campaigns', 'open-flags', 'feeding-programs', 'maternal-profiles'],
            'clinical' => ['pending-triage', 'due-follow-ups', 'active-escalations', 'mho-reviews'],
        ];

        foreach ($groups as $area => $datasets) {
            $this->actingAs($admin)->get(route("admin.oversight.{$area}"))
                ->assertOk()->assertSee('Excel (.xlsx)');

            foreach ($datasets as $dataset) {
                $this->actingAs($admin)->get(route("admin.oversight.{$area}.export", [
                    'dataset' => $dataset, 'format' => 'csv',
                ]))->assertOk();
            }
        }
    }

    public function test_field_panel_export_removes_display_limit_but_keeps_barangay_scope(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $otherBarangay = Barangay::factory()->create();
        $otherPurok = Purok::factory()->create(['barangay_id' => $otherBarangay->id]);

        foreach (range(1, 12) as $number) {
            HouseholdDraft::query()->create([
                'submitted_by_user_id' => $admin->id,
                'barangay_id' => $barangay->id,
                'purok_id' => $purok->id,
                'draft_reference_code' => "LOCAL-{$number}",
                'household_address' => 'Local address',
                'draft_status' => HouseholdDraft::STATUS_PENDING,
            ]);
        }

        HouseholdDraft::query()->create([
            'submitted_by_user_id' => $admin->id,
            'barangay_id' => $otherBarangay->id,
            'purok_id' => $otherPurok->id,
            'draft_reference_code' => 'FOREIGN-1',
            'household_address' => 'Other address',
            'draft_status' => HouseholdDraft::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->get(route('admin.oversight.field', ['barangay_id' => $barangay->id]))
            ->assertOk()->assertViewHas('recentPendingDrafts', fn ($drafts) => $drafts->count() === 10);

        $csv = $this->actingAs($admin)->get(route('admin.oversight.field.export', [
            'dataset' => 'pending-drafts', 'format' => 'csv', 'barangay_id' => $barangay->id,
        ]))->assertOk()->streamedContent();

        $this->assertCount(13, explode("\n", trim($csv)));
        $this->assertStringNotContainsString('FOREIGN-1', $csv);
    }
}
