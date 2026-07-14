<?php

namespace Tests\Support;

use App\Models\Backup;
use App\Support\DatabaseBackupManager;

class FakeDatabaseBackupManager extends DatabaseBackupManager
{
    public function capabilities(): array
    {
        return [
            'driver' => 'fake-mysql',
            'storage_directory' => storage_path('app/backups'),
            'storage_ready' => true,
            'dump_binary' => '/usr/bin/mysqldump',
            'restore_binary' => '/usr/bin/mysql',
            'generate_supported' => true,
            'restore_supported' => true,
            'issues' => [],
        ];
    }

    public function generate(Backup $backup): array
    {
        $this->ensureDirectory($backup);

        file_put_contents(
            $backup->absolute_path,
            "-- fake backup\nCREATE TABLE sample (id INT);\nINSERT INTO sample VALUES (1);\n"
        );

        return $this->verifyPayload($backup);
    }

    public function verify(Backup $backup): array
    {
        return array_merge(
            ['message' => 'Backup integrity verified successfully.', 'valid' => true],
            $this->verifyPayload($backup)
        );
    }

    public function restore(Backup $backup, ?string $notes = null): array
    {
        return [
            'message' => 'Backup restored into the active database successfully.',
            'metadata' => [
                'integrity_status' => 'verified',
                'last_restored_at' => now()->toIso8601String(),
                'last_restore_notes' => $notes,
                'restore_count' => (int) $backup->metadataValue('restore_count', 0) + 1,
                'checksum_sha256' => hash_file('sha256', $backup->absolute_path),
            ],
        ];
    }

    private function verifyPayload(Backup $backup): array
    {
        $fileSize = filesize($backup->absolute_path);

        return [
            'file_size' => $fileSize,
            'metadata' => [
                'checksum_sha256' => hash_file('sha256', $backup->absolute_path),
                'integrity_status' => 'verified',
                'integrity_checked_at' => now()->toIso8601String(),
                'contains_schema_statements' => true,
                'contains_data_statements' => true,
                'verification_driver' => 'fake-mysql',
            ],
        ];
    }

    private function ensureDirectory(Backup $backup): void
    {
        $directory = dirname($backup->absolute_path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
