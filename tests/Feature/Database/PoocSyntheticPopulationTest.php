<?php

namespace Tests\Feature\Database;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\Population\PoocFamilyGenerator;
use App\Support\Population\PoocNames;
use App\Support\Population\PoocPopulation;
use App\Support\Population\PoocPopulationAudit;
use Carbon\CarbonImmutable;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PoocPilotConfigurationSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PoocSyntheticPopulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_population_is_coherent_scoped_and_contains_no_operational_records(): void
    {
        $this->travelTo(CarbonImmutable::parse(config('pooc_population.as_of')));
        $this->seed(PoocPilotConfigurationSeeder::class);
        $report = app(PoocPopulation::class)->seed();
        $this->assertSame(3000, $report['residents']);
        $this->assertSame(34, $report['barangays']);
        $this->assertSame(0, array_sum($report['invalid']), json_encode($report['invalid']));
        $this->assertSame(0, array_sum($report['operational_counts']));
        $this->assertSame(7, $report['puroks_populated']);
        foreach ($report['ages'] as $count) {
            $this->assertGreaterThan(0, $count);
        }
        $this->assertGreaterThan(0, $report['infants']);
        $this->assertGreaterThan(100, $report['children_0_59_months']);
        $this->assertGreaterThan(0, $report['caregivers']['linked_under_five']);
        $this->assertGreaterThan(0, $report['caregivers']['unconfirmed_under_five']);
        $this->assertSame(1, $report['smallest_household']);
        $this->assertGreaterThanOrEqual(8, $report['largest_household']);
        $this->assertSame(7, Household::where('household_no', '1')->count());
        $this->assertSame(6, User::count());
        $this->assertSame(0, DB::table('barangay_officials')->whereNotNull('official_name')->count());
        foreach (Resident::all() as $resident) {
            $this->assertSame((int) floor($resident->birth_date->diffInYears(CarbonImmutable::parse(config('pooc_population.as_of')))),
                $resident->birth_date->age);
        }
        $this->assertSame($report['invalid'], app(PoocPopulationAudit::class)->report()['invalid']);
        try {
            app(PoocPopulation::class)->seed();
            $this->fail('Rerun should refuse existing population.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already exist', $e->getMessage());
        }
        $this->assertSame(3000, Resident::count());
    }

    public function test_generation_is_deterministic_unique_and_uses_supplied_surnames(): void
    {
        $config = config('pooc_population');
        $first = (new PoocFamilyGenerator($config))->generate(range(1, 7));
        $second = (new PoocFamilyGenerator($config))->generate(range(1, 7));
        $this->assertSame($first, $second);
        $this->assertSame('be4364f3822d479659e1c6766da06f69801df6ab5719798bf16cf26944370a28', hash('sha256', json_encode($first)), 'Version 1 population changed; review rules and increment the dataset version intentionally.');
        $pool = array_column(require database_path('data/tubigon-surnames.php'), 'surname');
        $this->assertCount(201, $pool);
        $all = [];
        $inherited = 0;
        foreach ($first as $household) {
            $mother = collect($household['members'])->first(fn ($m) => $m['sex'] === 'Female' && $m['civil_status'] === 'Married' && in_array($m['relationship_to_head'], ['Spouse', 'Head of Household']));
            foreach ($household['members'] as $member) {
                $this->assertContains($member['last_name'], $pool);
                $this->assertContains($member['middle_name'], $pool);
                $all[] = PoocNames::normalized($member);
                if ($mother && $member['relationship_to_head'] === 'Child' && $member['last_name'] === $mother['last_name']) {
                    $this->assertSame($mother['middle_name'], $member['middle_name']);
                    $inherited++;
                }
            }
        }
        $this->assertCount(3000, $all);
        $this->assertCount(3000, array_unique($all));
        $this->assertGreaterThan(500, $inherited);
        $config['seed']++;
        $this->assertNotSame($first, (new PoocFamilyGenerator($config))->generate(range(1, 7)));
    }

    public function test_default_seeding_retains_municipality_without_population_or_operations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pilot = Barangay::where('name', 'Pooc Oriental')->firstOrFail();
        $pilot->update(['psgc_code' => 'preserved-id', 'region' => 'Preserved']);
        $this->seed(BarangaySeeder::class);
        $this->assertSame('preserved-id', $pilot->fresh()->psgc_code);
        $this->assertSame('Preserved', $pilot->fresh()->region);
        $this->assertSame(34, Barangay::count());
        $this->assertSame(0, Purok::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, Resident::count());
        $this->assertSame(0, array_sum(PoocPopulation::operationalCounts()));
        $this->expectExceptionMessage('no configured puroks');
        app(PoocPopulation::class)->seed();
    }

    public function test_existing_records_are_not_deleted_and_failed_capture_rolls_back(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        config(['pooc_population.target' => 30]);
        $dispatcher = Resident::getEventDispatcher();
        Resident::setEventDispatcher(clone $dispatcher);
        Resident::creating(function (): void {
            throw new RuntimeException('Forced capture failure');
        });
        try {
            app(PoocPopulation::class)->seed();
            $this->fail('Expected rollback.');
        } catch (RuntimeException $e) {
            $this->assertSame('Forced capture failure', $e->getMessage());
        } finally {
            Resident::setEventDispatcher($dispatcher);
        }
        $this->assertSame(0, Household::count());
        $this->assertSame(0, Resident::count());
        $existing = Household::create(['purok_id' => Purok::first()->id, 'household_no' => 'manual', 'household_address' => 'Manual development record']);
        try {
            app(PoocPopulation::class)->seed();
            $this->fail('Existing records must block seeding.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already exist', $e->getMessage());
        }
        $this->assertNotNull($existing->fresh());
        $this->assertSame(1, Household::count());
    }

    public function test_non_pilot_scoped_user_blocks_population(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        $other = Barangay::where('name', '!=', 'Pooc Oriental')->firstOrFail();
        User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => $other->id]);
        try {
            $this->seed(PoocPilotConfigurationSeeder::class);
            $this->fail('Configuration must also refuse non-pilot frontline accounts.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Non-pilot frontline accounts', $e->getMessage());
        }
        $this->assertSame(7, User::count());
        $this->expectExceptionMessage('Non-pilot scoped data');
        app(PoocPopulation::class)->seed();
    }

    public function test_empty_barangays_render_municipal_and_configuration_pages_without_inventing_puroks(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        $other = Barangay::where('name', '!=', 'Pooc Oriental')->firstOrFail();
        $this->get(route('register'))->assertOk();
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        foreach (['admin.dashboard', 'admin.users.create', 'admin.households.create', 'admin.barangays.index', 'admin.puroks.index',
            'admin.residents.index', 'admin.reports.index', 'admin.oversight.nutrition', 'admin.oversight.clinical', 'admin.oversight.field'] as $route) {
            $this->get(route($route, ['barangay_id' => $other->id]))->assertOk();
        }
        $this->get(route('admin.barangays.show', $other))->assertOk();
        $this->getJson(route('admin.users.get-puroks', ['barangay_id' => $other->id]))->assertOk()->assertExactJson([]);
        $this->assertSame(0, $other->puroks()->count());
        $this->assertSame(0, Resident::count());
    }

    public function test_non_pilot_puroks_block_population_without_removing_them(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        $other = Barangay::where('name', '!=', 'Pooc Oriental')->firstOrFail();
        $purok = Purok::create(['barangay_id' => $other->id, 'purok_number' => 1, 'purok_name' => 'Existing development record']);
        try {
            app(PoocPopulation::class)->seed();
            $this->fail('Expected refusal.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Non-pilot scoped data', $e->getMessage());
        }
        $this->assertNotNull($purok->fresh());
        $this->assertSame(0, Resident::count());
    }

    public function test_operational_records_block_population_without_removing_history(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        DB::table('jobs')->insert(['queue' => 'existing', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => 1, 'created_at' => 1]);
        try {
            app(PoocPopulation::class)->seed();
            $this->fail('Expected refusal.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Operational records already exist', $e->getMessage());
        }
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, Household::count());
    }

    public function test_population_commands_are_development_only(): void
    {
        app()['env'] = 'production';
        $this->expectExceptionMessage('development/testing only');
        app(PoocPopulation::class)->seed();
    }

    public function test_database_rejects_duplicate_household_number_in_same_purok(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        $puroks = Purok::orderBy('purok_number')->get();
        foreach ([$puroks[0], $puroks[1]] as $purok) {
            Household::create(['purok_id' => $purok->id, 'household_no' => '1', 'household_address' => 'Synthetic test']);
        }
        $this->assertSame(2, Household::count());
        $this->expectException(UniqueConstraintViolationException::class);
        Household::create(['purok_id' => $puroks[0]->id, 'household_no' => '1', 'household_address' => 'Synthetic duplicate test']);
    }

    public function test_audit_detects_invalid_caregiver_and_future_dob(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        config(['pooc_population.target' => 100]);
        app(PoocPopulation::class)->seed();
        $profile = DB::table('child_nutrition_profiles')->whereNotNull('caregiver_resident_id')->first();
        $this->assertNotNull($profile);
        DB::table('child_nutrition_profiles')->where('resident_id', $profile->resident_id)->update(['caregiver_resident_id' => $profile->resident_id]);
        DB::table('residents')->where('id', $profile->resident_id)->update(['birth_date' => '2099-01-01']);
        $report = app(PoocPopulationAudit::class)->report();
        $this->assertSame(1, $report['invalid']['invalid_dob']);
        $this->assertSame(1, $report['invalid']['invalid_caregiver_references']);
    }

    public function test_configuration_reseeding_preserves_accounts_and_does_not_add_non_pilot_data(): void
    {
        $this->seed(PoocPilotConfigurationSeeder::class);
        $original = User::orderBy('id')->get()->toArray();
        $this->seed(PoocPilotConfigurationSeeder::class);
        $this->assertSame($original, User::orderBy('id')->get()->toArray());
        $this->assertSame(7, Purok::count());
        $this->assertSame(34, Barangay::count());
        $this->assertSame(0, Resident::count());
    }
}
