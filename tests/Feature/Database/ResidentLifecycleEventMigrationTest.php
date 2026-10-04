<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResidentLifecycleEventMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->refreshTestDatabase();
        // As in L1A, avoid older OPT migrations' deliberately prohibited bulk rollback.
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_empty_event_table_can_be_rolled_back_and_recreated_without_touching_residents(): void
    {
        $migration = require database_path('migrations/2026_10_05_000002_create_resident_lifecycle_events_table.php');
        $columns = Schema::getColumns('residents');
        $migration->down();
        $this->assertFalse(Schema::hasTable('resident_lifecycle_events'));
        $migration->up();
        $this->assertSame(0, DB::table('resident_lifecycle_events')->count());
        $this->assertSame($columns, Schema::getColumns('residents'));
    }
}
