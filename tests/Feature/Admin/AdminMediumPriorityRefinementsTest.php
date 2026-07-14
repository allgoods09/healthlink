<?php

namespace Tests\Feature\Admin;

use App\Models\ArchivedRecord;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminMediumPriorityRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reports_hub_renders_and_supports_exports(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Municipal Reports Hub')
            ->assertSee('Municipal Clinical Throughput');

        $this->actingAs($admin)
            ->get(route('admin.reports.export', ['report' => 'staffing', 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_protected_admin_actions_require_confirmation_phrase_and_reason(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'event_type' => 'created',
            'event_description' => 'Seed audit log entry',
        ]);

        SyncLog::create([
            'user_id' => $admin->id,
            'status' => 'success',
            'records_synced' => 4,
        ]);

        $archivedRecord = ArchivedRecord::create([
            'original_table' => 'residents',
            'original_id' => 99,
            'data_snapshot' => [
                'first_name' => 'Test',
                'last_name' => 'Resident',
            ],
            'archived_by' => $admin->id,
            'archiving_reason' => 'Testing archive safeguards',
        ]);

        $backup = Backup::create([
            'filename' => 'test_restore.sql',
            'file_path' => 'backups/test_restore.sql',
            'backup_type' => 'full',
            'status' => Backup::STATUS_COMPLETED,
            'generated_by' => $admin->id,
            'storage_location' => Backup::STORAGE_LOCAL,
            'metadata' => [],
        ]);

        File::ensureDirectoryExists(dirname($backup->absolute_path));
        File::put($backup->absolute_path, '-- restore test');

        $this->actingAs($admin)
            ->from(route('admin.audit.index'))
            ->delete(route('admin.audit.clear-old'))
            ->assertRedirect(route('admin.audit.index'))
            ->assertSessionHasErrors(['confirmation_phrase', 'action_reason']);

        $this->actingAs($admin)
            ->from(route('admin.sync-logs.index'))
            ->delete(route('admin.sync-logs.clear-old'))
            ->assertRedirect(route('admin.sync-logs.index'))
            ->assertSessionHasErrors(['confirmation_phrase', 'action_reason']);

        $this->actingAs($admin)
            ->from(route('admin.archive.show', $archivedRecord))
            ->delete(route('admin.archive.purge', $archivedRecord))
            ->assertRedirect(route('admin.archive.show', $archivedRecord))
            ->assertSessionHasErrors(['confirmation_phrase', 'action_reason']);

        $this->actingAs($admin)
            ->from(route('admin.backups.show', $backup))
            ->post(route('admin.backups.restore', $backup))
            ->assertRedirect(route('admin.backups.show', $backup))
            ->assertSessionHasErrors(['confirmation_phrase', 'action_reason']);
    }
}
