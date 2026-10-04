<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResidentCodeSequenceMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->refreshTestDatabase();
        // Prior OPT migrations intentionally forbid bulk rollback; exercise only this migration.
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_only_an_empty_sequence_table_can_be_rolled_back_and_resident_schema_is_unchanged(): void
    {
        $before = Schema::getColumns('residents');
        $migration = require database_path('migrations/2026_10_05_000003_create_resident_code_sequences_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('resident_code_sequences'));
        $migration->up();
        $this->assertSame($before, Schema::getColumns('residents'));
        DB::table('resident_code_sequences')->insert(['origin_barangay_id' => 999, 'last_value' => 0]);
        try {
            $migration->down();
            $this->fail('Even zero-valued namespace history must be retained.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('permanent Resident code sequence history', $exception->getMessage());
        }
        $this->assertDatabaseHas('resident_code_sequences', ['origin_barangay_id' => 999, 'last_value' => 0]);
    }
}
