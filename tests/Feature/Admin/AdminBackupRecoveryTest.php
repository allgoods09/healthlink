<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\User;
use App\Support\DatabaseBackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDatabaseBackupManager;
use Tests\TestCase;

class AdminBackupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearBackupDirectory();
    }

    public function test_admin_can_generate_verify_and_restore_a_backup(): void
    {
        $this->app->instance(DatabaseBackupManager::class, new FakeDatabaseBackupManager());

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.backups.generate'), [
            'backup_type' => 'full',
            'notes' => 'Before production rollout',
        ]);

        $backup = Backup::query()->firstOrFail();

        $response->assertRedirect(route('admin.backups.show', $backup));
        $this->assertSame(Backup::STATUS_COMPLETED, $backup->fresh()->status);
        $this->assertSame('verified', $backup->fresh()->integrity_status);
        $this->assertFileExists($backup->absolute_path);

        $verifyResponse = $this->actingAs($admin)->post(route('admin.backups.verify', $backup));

        $verifyResponse->assertRedirect(route('admin.backups.show', $backup));
        $this->assertSame('verified', $backup->fresh()->integrity_status);

        $restoreResponse = $this->actingAs($admin)->post(route('admin.backups.restore', $backup), [
            'confirmation' => $backup->filename,
            'restore_notes' => 'Recovery drill',
            'restore_acknowledged' => '1',
        ]);

        $restoreResponse->assertRedirect(route('admin.backups.show', $backup));
        $this->assertSame(1, $backup->fresh()->restore_count);
        $this->assertSame($admin->id, $backup->fresh()->metadataValue('last_restored_by'));
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'backup_restored',
            'model_type' => Backup::class,
            'model_id' => $backup->id,
        ]);
    }

    public function test_backup_restore_requires_exact_filename_confirmation(): void
    {
        $this->app->instance(DatabaseBackupManager::class, new FakeDatabaseBackupManager());

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $backup = Backup::factory()->create([
            'generated_by' => $admin->id,
            'status' => Backup::STATUS_COMPLETED,
            'metadata' => ['integrity_status' => 'verified'],
        ]);

        $this->writeBackupFile($backup);

        $response = $this->actingAs($admin)
            ->from(route('admin.backups.show', $backup))
            ->post(route('admin.backups.restore', $backup), [
                'confirmation' => 'wrong-file.sql',
                'restore_notes' => 'No-op',
                'restore_acknowledged' => '1',
            ]);

        $response->assertRedirect(route('admin.backups.show', $backup));
        $response->assertSessionHas('error', 'Confirmation filename did not match. Restore cancelled.');
        $this->assertSame(0, $backup->fresh()->restore_count);
    }

    public function test_missing_backup_file_is_marked_missing_on_download_attempt(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $backup = Backup::factory()->create([
            'generated_by' => $admin->id,
            'status' => Backup::STATUS_COMPLETED,
            'metadata' => ['integrity_status' => 'verified'],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.backups.show', $backup))
            ->get(route('admin.backups.download', $backup));

        $response->assertRedirect(route('admin.backups.show', $backup));
        $response->assertSessionHas('error', 'Backup file not found.');
        $this->assertSame('missing', $backup->fresh()->integrity_status);
    }

    public function test_admin_can_delete_expired_backups_and_files(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $backup = Backup::factory()->create([
            'generated_by' => $admin->id,
            'status' => Backup::STATUS_COMPLETED,
            'expires_at' => now()->subDay(),
        ]);

        $this->writeBackupFile($backup);

        $response = $this->actingAs($admin)->delete(route('admin.backups.delete-expired'));

        $response->assertRedirect(route('admin.backups.index'));
        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
        $this->assertFileDoesNotExist($backup->absolute_path);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'deleted',
            'event_description' => 'Deleted 1 expired backups',
        ]);
    }

    private function writeBackupFile(Backup $backup): void
    {
        $directory = dirname($backup->absolute_path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($backup->absolute_path, "-- fake backup\nCREATE TABLE demo (id INT);\n");
    }

    private function clearBackupDirectory(): void
    {
        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);

            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
