<?php

namespace Tests\Feature\Database;

use App\Support\ResidentLifecycleInventory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentLifecycleMigrationTest extends TestCase
{
    use DatabaseMigrations, LifecycleResidentFixture;

    public function runDatabaseMigrations(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->refreshTestDatabase();
        // Exercise only L1A rollback: older OPT migrations deliberately prohibit bulk rollback.
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    private function migration(): object
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());

        return require database_path('migrations/2026_10_05_000001_expand_resident_lifecycle_foundation.php');
    }

    public function test_real_mariadb_round_trip_does_not_rewrite_legacy_residents_or_constraints(): void
    {
        $migration = $this->migration();
        $migration->down();
        foreach (['active', 'deceased', 'relocated'] as $status) {
            $this->residentFixture(['resident_status' => $status, 'is_active' => false]);
        }
        $before = DB::table('residents')->orderBy('id')->get()->toArray();
        $indexes = Schema::getIndexes('residents');
        $foreignKeys = Schema::getForeignKeys('residents');
        $this->assertSame([], app(ResidentLifecycleInventory::class)->report()['blockers']);
        $migration->up();
        $after = DB::table('residents')->orderBy('id')->get()->toArray();
        foreach ($after as $row) {
            $this->assertSame(0, $row->lifecycle_version);
            unset($row->lifecycle_version);
        }
        $this->assertEquals($before, $after);
        $this->assertSame($indexes, Schema::getIndexes('residents'));
        $this->assertSame($foreignKeys, Schema::getForeignKeys('residents'));
        $migration->down();
        $this->assertEquals($before, DB::table('residents')->orderBy('id')->get()->toArray());
        $migration->up();
    }

    public function test_rollback_refuses_even_soft_deleted_moved_out_rows_before_changing_schema(): void
    {
        $resident = $this->residentFixture(['resident_status' => 'moved_out']);
        $resident->delete();
        try {
            try {
                $this->migration()->down();
                $this->fail('Unsafe rollback was accepted.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Cannot roll back', $exception->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('residents', 'lifecycle_version'));
            $this->assertStringContainsString("'moved_out'", array_column(Schema::getColumns('residents'), 'type', 'name')['resident_status']);
            $this->assertSame('moved_out', DB::table('residents')->where('id', $resident->id)->value('resident_status'));
        } finally {
            DB::table('residents')->where('id', $resident->id)->delete();
        }
    }

    public function test_rollback_refuses_nonzero_versions_without_losing_metadata(): void
    {
        $resident = $this->residentFixture();
        DB::table('residents')->where('id', $resident->id)->update(['lifecycle_version' => 2]);
        try {
            try {
                $this->migration()->down();
                $this->fail('Unsafe rollback was accepted.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Cannot roll back', $exception->getMessage());
            }
            $this->assertSame(2, DB::table('residents')->where('id', $resident->id)->value('lifecycle_version'));
        } finally {
            DB::table('residents')->where('id', $resident->id)->delete();
        }
    }

    public function test_missing_constraint_and_duplicate_codes_are_real_blockers_not_repairs(): void
    {
        $first = $this->residentFixture();
        $second = $this->residentFixture();
        Schema::table('residents', fn ($table) => $table->dropUnique(['official_resident_code']));
        try {
            DB::table('residents')->where('id', $second->id)->update(['official_resident_code' => $first->official_resident_code]);
            $this->assertSame(1, Artisan::call('residents:lifecycle-preflight', ['--json' => true]));
            $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(1, $report['identity']['duplicate_code_groups']);
            $this->assertContains('duplicate_official_resident_code', array_column($report['blockers'], 'reason'));
            $this->assertContains('missing_unique_constraint', array_column($report['blockers'], 'reason'));
            $this->assertSame($first->official_resident_code, $second->fresh()->official_resident_code);
        } finally {
            DB::table('residents')->where('id', $second->id)->delete();
            Schema::table('residents', fn ($table) => $table->unique('official_resident_code'));
        }
    }
}
