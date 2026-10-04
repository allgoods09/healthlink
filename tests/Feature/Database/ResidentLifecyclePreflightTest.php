<?php

namespace Tests\Feature\Database;

use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Support\ResidentLifecycleInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentLifecyclePreflightTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    public function test_command_is_read_only_and_reports_ambiguities_without_personal_data_or_fatal_exit(): void
    {
        $resident = $this->residentFixture(['is_active' => false, 'resident_status' => 'relocated']);
        DB::table('residents')->where('id', $resident->id)->update(['official_resident_code' => null]);
        $archived = $this->residentFixture();
        $archived->delete();
        $user = User::factory()->create();
        foreach (['resident' => $resident->id, 'household' => $resident->household_id] as $type => $id) {
            ProfileUpdateRequest::create(['subject_type' => $type, 'subject_id' => $id,
                'submitted_by_user_id' => $user->id, 'barangay_id' => $resident->household->purok->barangay_id,
                'current_snapshot' => [], 'proposed_changes' => ['occupation' => 'Private proposed value'],
                'request_reason' => 'Private reason', 'request_status' => 'pending']);
        }
        $tables = ['residents', 'households', 'profile_update_requests'];
        $before = array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->assertSame(0, Artisan::call('residents:lifecycle-preflight', ['--json' => true]));
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/^\s*(select|show)\b/i', $sql);
        }
        $this->assertSame($before, array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables));
        $this->assertSame(2, $report['summary']['total_residents']);
        $this->assertSame(1, $report['summary']['soft_deleted_residents']);
        $this->assertSame(1, $report['ambiguous']['legacy_relocated_requires_review']['count']);
        $this->assertSame(1, $report['identity']['null_code']['count']);
        $this->assertSame(0, $report['identity']['duplicate_code_groups']);
        $this->assertSame(1, $report['corrections']['unresolved']['resident']['count']);
        $this->assertSame(1, $report['corrections']['unresolved']['household']['count']);
        $this->assertSame([], $report['blockers']);
        foreach (['Fixture', '1994-03-12', 'Synthetic fixture address', 'Private proposed value', 'Private reason'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
        $this->artisan('residents:lifecycle-preflight')->expectsOutputToContain('SUMMARY')
            ->expectsOutputToContain('STATUS INVENTORY')->expectsOutputToContain('LEGACY / AMBIGUOUS RECORDS')
            ->expectsOutputToContain('IDENTITY / CODE CHECKS')->expectsOutputToContain('OWNERSHIP CHECKS')
            ->expectsOutputToContain('CORRECTION CHECKS')->expectsOutputToContain('BLOCKERS')->assertSuccessful();
    }

    public function test_command_returns_nonzero_only_when_the_inventory_reports_a_blocker(): void
    {
        $this->mock(ResidentLifecycleInventory::class)->shouldReceive('report')->once()->andReturn([
            'summary' => [], 'status_inventory' => [], 'ambiguous' => [], 'identity' => [],
            'ownership' => [], 'corrections' => [], 'blockers' => [['reason' => 'unsupported_status', 'resident_ids' => [1]]],
        ]);
        $this->artisan('residents:lifecycle-preflight', ['--json' => true])->assertFailed();
    }
}
