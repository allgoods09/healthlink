<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_barangay_export_includes_every_match_in_listing_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Barangay::factory()->count(18)->create();

        $page = $this->actingAs($admin)->get(route('admin.barangays.index', ['page' => 2]));
        $page->assertOk()->assertViewHas('barangays', fn ($rows) => $rows->total() === 18 && $rows->count() === 3);

        $response = $this->actingAs($admin)->get(route('admin.barangays.export', ['format' => 'csv', 'page' => 2]));
        $response->assertOk();
        $lines = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertCount(19, $lines);
        $this->assertSame('Barangay', $lines[0][0]);
        $this->assertSame(Barangay::query()->latest()->orderByDesc('id')->pluck('name')->all(), array_column(array_slice($lines, 1), 0));
    }

    public function test_scoped_resident_export_respects_search_filters_and_ignores_page(): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = $this->household($purok);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);

        foreach (range(1, 18) as $number) {
            $this->resident($household, ['first_name' => "Pilot {$number}", 'sex' => 'Female']);
        }

        $this->resident($household, ['first_name' => 'Pilot Male', 'sex' => 'Male']);
        $otherPurok = Purok::factory()->create(['barangay_id' => Barangay::factory()->create()->id]);
        $this->resident($this->household($otherPurok), ['first_name' => 'Pilot Foreign', 'sex' => 'Female']);

        $query = ['search' => 'Pilot', 'sex' => 'Female', 'purok_id' => $purok->id, 'page' => 2];
        $this->actingAs($secretary)->get(route('secretary.residents.index', $query))
            ->assertOk()
            ->assertViewHas('residents', fn ($rows) => $rows->total() === 18 && $rows->count() === 3);

        $response = $this->actingAs($secretary)->get(route('secretary.residents.export', ['format' => 'csv'] + $query));
        $response->assertOk();
        $lines = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertCount(19, $lines);
        $this->assertNotContains('PhilSys ID', $lines[0]);
        $this->assertStringNotContainsString('Foreign', $response->streamedContent());
        $this->assertStringNotContainsString('Pilot Male', $response->streamedContent());
    }

    public function test_report_dropdowns_and_xlsx_information_are_dataset_specific(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barangay = Barangay::factory()->create();

        $this->actingAs($admin)->get(route('admin.reports.index', ['barangay_id' => $barangay->id]))
            ->assertOk()
            ->assertSee('Export Municipal Staffing Footprint')
            ->assertSee('Export Municipal Demographic Distribution')
            ->assertSee('Export Municipal Nutrition Statistics')
            ->assertSee('Export Municipal Clinical Throughput');

        $response = $this->actingAs($admin)->get(route('admin.reports.export', [
            'report' => 'staffing', 'format' => 'xlsx', 'barangay_id' => $barangay->id,
        ]));
        $response->assertOk();
        $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $this->assertSame(['Data', 'Export Information'], $spreadsheet->getSheetNames());
        $this->assertSame('Municipal Staffing Footprint', $spreadsheet->getSheetByName('Export Information')->getCell('B1')->getValue());
        $this->assertSame($barangay->name, $spreadsheet->getSheetByName('Export Information')->getCell('B3')->getValue());
        $this->assertSame(2, $spreadsheet->getSheetByName('Data')->getHighestRow());
        $spreadsheet->disconnectWorksheets();
    }

    public function test_shared_registry_views_offer_scoped_exports_with_curated_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $local = Barangay::factory()->create();
        $foreign = Barangay::factory()->create();
        $localPurok = Purok::factory()->create(['barangay_id' => $local->id, 'purok_number' => 1]);
        $foreignPurok = Purok::factory()->create(['barangay_id' => $foreign->id, 'purok_number' => 2]);
        $localHousehold = $this->household($localPurok);
        $foreignHousehold = $this->household($foreignPurok);
        $this->resident($localHousehold, ['first_name' => 'Local Resident', 'philsys_card_no' => 'SECRET-123']);
        $this->resident($foreignHousehold, ['first_name' => 'Foreign Resident']);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $local->id]);

        foreach (['puroks', 'households', 'residents'] as $page) {
            $this->actingAs($admin)->get(route("admin.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');
            $this->actingAs($secretary)->get(route("secretary.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');

            $csv = $this->actingAs($secretary)->get(route("secretary.{$page}.export", ['format' => 'csv']))
                ->assertOk()->streamedContent();
            $this->assertStringContainsString($local->name, $csv);
            $this->assertStringNotContainsString($foreign->name, $csv);
        }

        $adminResidents = $this->actingAs($admin)
            ->get(route('admin.residents.export', ['format' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringNotContainsString('PhilSys ID', $adminResidents);
        $this->assertStringNotContainsString('SECRET-123', $adminResidents);
    }

    public function test_clinical_listings_show_export_and_download_without_internal_ids(): void
    {
        $phn = User::factory()->create(['role' => 'phn']);
        $mho = User::factory()->create(['role' => 'mho']);

        foreach (['triage', 'encounters'] as $page) {
            $this->actingAs($phn)->get(route("phn.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');
            $csv = $this->actingAs($phn)->get(route("phn.{$page}.export", ['format' => 'csv']))
                ->assertOk()->streamedContent();
            $this->assertStringNotContainsString('Encounter ID', $csv);
        }

        $this->actingAs($mho)->get(route('mho.escalations.index'))
            ->assertOk()->assertSee('Excel (.xlsx)');
        $csv = $this->actingAs($mho)->get(route('mho.escalations.export', ['format' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Encounter ID', $csv);
    }

    public function test_admin_user_export_keeps_role_filter_and_excludes_credentials(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'bhw', 'name' => 'Filtered Worker', 'first_name' => 'Filtered', 'last_name' => 'Worker']);
        User::factory()->create(['role' => 'bns', 'name' => 'Different Worker', 'first_name' => 'Different', 'last_name' => 'Worker']);

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'bhw']))
            ->assertOk()->assertSee('Excel (.xlsx)');
        $csv = $this->actingAs($admin)->get(route('admin.users.export', [
            'format' => 'csv', 'role' => 'bhw', 'page' => 2,
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString('Filtered Worker', $csv);
        $this->assertStringNotContainsString('Different Worker', $csv);
        $this->assertStringNotContainsString('password', strtolower($csv));
        $this->assertStringNotContainsString('token', strtolower($csv));
    }

    public function test_admin_security_exports_use_dropdowns_and_safe_headers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['devices', 'Mobile Device Inventory'],
            ['sync-logs', 'Synchronization Report'],
            ['audit', 'Audit Trail Report'],
        ] as [$page, $title]) {
            $this->actingAs($admin)->get(route("admin.{$page}.index"))
                ->assertOk()->assertSee("Export {$title}");
            $csv = $this->actingAs($admin)->get(route("admin.{$page}.export", ['format' => 'csv']))
                ->assertOk()->streamedContent();
            $header = strtok($csv, "\n");
            $this->assertStringNotContainsString('ID,', $header);
            $this->assertStringNotContainsString('IP Address', $header);
            $this->assertStringNotContainsString('Error', $header);
            $this->assertStringNotContainsString('Token', $header);
        }
    }

    public function test_secretary_workflow_exports_render_for_each_filtered_queue(): void
    {
        $barangay = Barangay::factory()->create();
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);

        foreach (['certificates', 'drafts', 'update-requests', 'team', 'activity'] as $page) {
            $this->actingAs($secretary)->get(route("secretary.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');
            $this->actingAs($secretary)->get(route("secretary.{$page}.export", ['format' => 'csv']))
                ->assertOk();
        }
    }

    public function test_demographic_report_exports_each_named_dataset_from_same_filtered_residents(): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = $this->household($purok);
        $this->resident($household, ['first_name' => 'Included', 'sex' => 'Female']);
        $this->resident($household, ['first_name' => 'Excluded', 'sex' => 'Male']);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);

        $this->actingAs($secretary)->get(route('secretary.reports.demographics', ['sex' => 'Female']))
            ->assertOk()
            ->assertSee('Export Barangay Demographics by Purok')
            ->assertSee('Export Barangay Demographic Roster');

        foreach (['roster', 'breakdown'] as $dataset) {
            $csv = $this->actingAs($secretary)->get(route('secretary.reports.demographics.export', [
                'format' => 'csv', 'dataset' => $dataset, 'sex' => 'Female', 'page' => 2,
            ]))->assertOk()->streamedContent();
            $this->assertCount(2, explode("\n", trim($csv)));
            $this->assertStringNotContainsString('Excluded', $csv);
        }
    }

    public function test_new_bns_export_routes_match_their_listing_controls(): void
    {
        $barangay = Barangay::factory()->create();
        $bns = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => $barangay->id]);

        foreach (['campaign-periods', 'feeding-programs', 'maternal', 'micronutrients'] as $page) {
            $this->actingAs($bns)->get(route("bns.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');
            $this->actingAs($bns)->get(route("bns.{$page}.export", ['format' => 'csv']))
                ->assertOk();
        }
    }

    public function test_bhw_exports_keep_barangay_scope_and_offer_every_format(): void
    {
        $local = Barangay::factory()->create();
        $foreign = Barangay::factory()->create();
        $localPurok = Purok::factory()->create(['barangay_id' => $local->id]);
        $foreignPurok = Purok::factory()->create(['barangay_id' => $foreign->id]);
        $this->resident($this->household($localPurok), ['first_name' => 'Local Person']);
        $this->resident($this->household($foreignPurok), ['first_name' => 'Foreign Person']);
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $local->id, 'assigned_purok_id' => $localPurok->id]);

        foreach (['residents', 'households', 'drafts', 'update-requests', 'triage', 'campaigns'] as $page) {
            $this->actingAs($bhw)->get(route("bhw.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');
            $this->actingAs($bhw)->get(route("bhw.{$page}.export", ['format' => 'csv']))
                ->assertOk();
        }

        $csv = $this->actingAs($bhw)->get(route('bhw.residents.export', ['format' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Local Person', $csv);
        $this->assertStringNotContainsString('Foreign Person', $csv);
    }

    public function test_remaining_clinical_directories_and_queues_export(): void
    {
        $phn = User::factory()->create(['role' => 'phn']);
        $mho = User::factory()->create(['role' => 'mho']);

        foreach (['residents', 'follow-ups', 'update-requests'] as $page) {
            $this->actingAs($phn)->get(route("phn.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');
            $this->actingAs($phn)->get(route("phn.{$page}.export", ['format' => 'csv']))
                ->assertOk();
        }

        $this->actingAs($mho)->get(route('mho.residents.index'))
            ->assertOk()->assertSee('Excel (.xlsx)');
        $this->actingAs($mho)->get(route('mho.residents.export', ['format' => 'csv']))
            ->assertOk();
    }

    public function test_notifications_export_only_the_current_users_filtered_minimal_fields(): void
    {
        $user = User::factory()->create(['role' => 'bhw']);
        $other = User::factory()->create(['role' => 'bhw']);

        foreach (range(1, 23) as $number) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'HealthLinkNotice',
                'data' => ['title' => "Notice {$number}", 'body' => 'PRIVATE BODY'],
                'read_at' => $number <= 10 ? now() : null,
            ]);
        }

        $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'HealthLinkNotice',
            'data' => ['title' => 'Other User Notice', 'body' => 'OTHER PRIVATE BODY'],
        ]);

        $this->actingAs($user)->get(route('notifications.index', ['status' => 'unread']))
            ->assertOk()->assertSee('Export My Notifications');
        $csv = $this->actingAs($user)->get(route('notifications.export', [
            'format' => 'csv', 'status' => 'unread', 'page' => 2,
        ]))->assertOk()->streamedContent();

        $this->assertCount(14, explode("\n", trim($csv)));
        $this->assertStringNotContainsString('Notice 1,', $csv);
        $this->assertStringNotContainsString('Other User Notice', $csv);
        $this->assertStringNotContainsString('PRIVATE BODY', $csv);
    }

    public function test_admin_archive_and_backup_exports_omit_payload_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['archive', 'backups'] as $page) {
            $this->actingAs($admin)->get(route("admin.{$page}.index"))
                ->assertOk()->assertSee('Excel (.xlsx)');
            $csv = $this->actingAs($admin)->get(route("admin.{$page}.export", ['format' => 'csv']))
                ->assertOk()->streamedContent();
            $this->assertStringNotContainsString('data_snapshot', $csv);
            $this->assertStringNotContainsString('file_path', $csv);
        }
    }

    private function household(Purok $purok): Household
    {
        return Household::query()->create([
            'purok_id' => $purok->id,
            'household_no' => '001',
            'household_address' => 'Zone 1',
            'is_social_aid_beneficiary' => false,
            'is_active' => true,
        ]);
    }

    private function resident(Household $household, array $attributes): Resident
    {
        return Resident::query()->create(array_merge([
            'household_id' => $household->id,
            'last_name' => 'Example',
            'first_name' => 'Pilot',
            'birth_date' => now()->subYears(30)->toDateString(),
            'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'relationship_to_head' => 'Member',
            'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => true,
        ], $attributes));
    }
}
