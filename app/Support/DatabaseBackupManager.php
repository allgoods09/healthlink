<?php

namespace App\Support;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseBackupManager
{
    /**
     * Describe the current backup tooling and runtime support.
     */
    public function capabilities(): array
    {
        $driver = $this->driver();
        $backupDirectory = storage_path('app/backups');
        $storageReady = is_dir($backupDirectory) || @mkdir($backupDirectory, 0755, true);
        $storageWritable = $storageReady && is_writable($backupDirectory);
        $dumpBinary = $this->findExecutable('mysqldump');
        $restoreBinary = $this->findExecutable('mysql');

        $generateSupported = in_array($driver, ['mysql', 'mariadb'], true) && $storageWritable && (bool) $dumpBinary;
        $restoreSupported = in_array($driver, ['mysql', 'mariadb'], true) && (bool) $restoreBinary;

        $issues = [];

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $issues[] = "Current database driver '{$driver}' is not supported by the production backup commands.";
        }

        if (! $storageWritable) {
            $issues[] = 'Backup storage directory is missing or not writable.';
        }

        if (! $dumpBinary) {
            $issues[] = 'The mysqldump binary is not available on this server.';
        }

        if (! $restoreBinary) {
            $issues[] = 'The mysql client binary is not available on this server.';
        }

        if ($driver === 'mysql' && ! $this->shouldDumpEvents()) {
            $issues[] = 'Database events will be skipped in generated backups because the event scheduler is disabled or unavailable.';
        }

        if ($driver === 'mysql' && ! $this->shouldDumpRoutines()) {
            $issues[] = 'Stored procedures and functions will be skipped in generated backups because routine metadata is unavailable on this server.';
        }

        return [
            'driver' => $driver,
            'storage_directory' => $backupDirectory,
            'storage_ready' => $storageWritable,
            'dump_binary' => $dumpBinary,
            'restore_binary' => $restoreBinary,
            'generate_supported' => $generateSupported,
            'restore_supported' => $restoreSupported,
            'issues' => $issues,
        ];
    }

    /**
     * Generate a database backup for the current connection.
     */
    public function generate(Backup $backup): array
    {
        $directory = $this->ensureBackupDirectory();

        return match ($this->driver()) {
            'mysql', 'mariadb' => $this->generateMysqlBackup($backup, $directory),
            default => throw new \RuntimeException(
                "Backup generation is only supported for MySQL/MariaDB connections. Current driver: {$this->driver()}."
            ),
        };
    }

    /**
     * Verify that a backup still exists and matches its recorded metadata.
     */
    public function verify(Backup $backup): array
    {
        $path = $backup->absolute_path;

        if (! is_file($path)) {
            return [
                'valid' => false,
                'message' => 'Backup file is missing from local storage.',
                'file_size' => null,
                'metadata' => [
                    'integrity_status' => 'missing',
                    'integrity_checked_at' => now()->toIso8601String(),
                    'last_integrity_error' => 'Backup file is missing from local storage.',
                ],
            ];
        }

        $fileSize = filesize($path) ?: 0;

        if ($fileSize === 0) {
            return [
                'valid' => false,
                'message' => 'Backup file is empty and cannot be restored safely.',
                'file_size' => 0,
                'metadata' => [
                    'integrity_status' => 'empty',
                    'integrity_checked_at' => now()->toIso8601String(),
                    'last_integrity_error' => 'Backup file is empty.',
                ],
            ];
        }

        $checksum = hash_file('sha256', $path);
        $recordedChecksum = $backup->metadataValue('checksum_sha256');
        $checksumMatches = $recordedChecksum ? hash_equals($recordedChecksum, $checksum) : true;
        $fileSizeMatches = $backup->file_size ? (int) $backup->file_size === (int) $fileSize : true;
        $markers = $this->inspectSqlMarkers($path);
        $status = ($checksumMatches && $fileSizeMatches) ? 'verified' : 'mismatch';

        return [
            'valid' => $status === 'verified',
            'message' => $status === 'verified'
                ? 'Backup integrity verified successfully.'
                : 'Backup file no longer matches the recorded checksum or file size.',
            'file_size' => $fileSize,
            'metadata' => [
                'checksum_sha256' => $checksum,
                'integrity_status' => $status,
                'integrity_checked_at' => now()->toIso8601String(),
                'checksum_matches_generated' => $checksumMatches,
                'file_size_matches_record' => $fileSizeMatches,
                'contains_schema_statements' => $markers['contains_schema'],
                'contains_data_statements' => $markers['contains_data'],
                'verification_driver' => $this->driver(),
                'last_integrity_error' => $status === 'verified'
                    ? null
                    : 'Backup file no longer matches the recorded checksum or file size.',
            ],
        ];
    }

    /**
     * Restore a verified backup into the active database connection.
     */
    public function restore(Backup $backup, ?string $notes = null): array
    {
        $verification = $this->verify($backup);

        if (! $verification['valid']) {
            throw new \RuntimeException($verification['message']);
        }

        return match ($this->driver()) {
            'mysql', 'mariadb' => $this->restoreMysqlBackup($backup, $verification, $notes),
            default => throw new \RuntimeException(
                "Backup restore is only supported for MySQL/MariaDB connections. Current driver: {$this->driver()}."
            ),
        };
    }

    /**
     * Generate a MySQL/MariaDB SQL dump.
     */
    private function generateMysqlBackup(Backup $backup, string $directory): array
    {
        $binary = $this->requireExecutable('mysqldump');
        $connection = $this->connectionConfig();
        $filePath = $directory.DIRECTORY_SEPARATOR.$backup->filename;
        $arguments = [
            $binary,
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--quick',
            '--triggers',
        ];

        if ($this->shouldDumpRoutines()) {
            $arguments[] = '--routines';
        }

        if ($this->dumpBinarySupportsColumnStatistics($binary)) {
            $arguments[] = '--column-statistics=0';
        }

        if ($this->shouldDumpEvents()) {
            $arguments[] = '--events';
        }

        $arguments[] = '--result-file='.$filePath;
        $arguments[] = '--databases';
        $arguments[] = $connection['database'];

        if ($backup->backup_type === 'schema_only') {
            $arguments[] = '--no-data';
        }

        if ($backup->backup_type === 'data_only') {
            $arguments[] = '--no-create-info';
        }

        $process = $this->newMysqlProcess($arguments, $connection['password']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException($this->processErrorMessage($process, 'Backup generation failed.'));
        }

        $verification = $this->verify($backup->forceFill([
            'file_size' => is_file($filePath) ? filesize($filePath) : null,
        ]));

        if (! $verification['valid']) {
            throw new \RuntimeException($verification['message']);
        }

        return [
            'file_size' => $verification['file_size'],
            'metadata' => array_merge($verification['metadata'], [
                'generated_driver' => $this->driver(),
                'database_name' => $connection['database'],
                'dump_binary' => basename($binary),
                'dumped_routines' => $this->shouldDumpRoutines(),
                'dumped_events' => $this->shouldDumpEvents(),
                'column_statistics_disabled' => $this->dumpBinarySupportsColumnStatistics($binary),
                'table_count' => $this->currentTableCount($connection['database']),
            ]),
        ];
    }

    /**
     * Restore a verified SQL dump using the MySQL client.
     */
    private function restoreMysqlBackup(Backup $backup, array $verification, ?string $notes = null): array
    {
        $binary = $this->requireExecutable('mysql');
        $connection = $this->connectionConfig();
        $path = $backup->absolute_path;
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('Backup file could not be opened for restore.');
        }

        $process = $this->newMysqlProcess([
            $binary,
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            $connection['database'],
        ], $connection['password']);

        $process->setInput($handle);
        $process->run();

        if (is_resource($handle)) {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException($this->processErrorMessage($process, 'Backup restore failed.'));
        }

        return [
            'message' => 'Backup restored into the active database successfully.',
            'metadata' => array_merge($verification['metadata'], [
                'restore_count' => (int) $backup->metadataValue('restore_count', 0) + 1,
                'last_restored_at' => now()->toIso8601String(),
                'last_restore_notes' => $notes,
                'last_restore_driver' => $this->driver(),
                'last_restore_error' => null,
                'restored_database' => $connection['database'],
            ]),
        ];
    }

    /**
     * Build a Process instance with consistent timeouts and MySQL password handling.
     */
    private function newMysqlProcess(array $arguments, ?string $password): Process
    {
        $environment = $password !== null && $password !== ''
            ? ['MYSQL_PWD' => $password]
            : null;

        $process = new Process($arguments, base_path(), $environment);
        $process->setTimeout(600);

        return $process;
    }

    /**
     * Resolve the current database connection config.
     */
    private function connectionConfig(): array
    {
        $connection = config('database.connections.'.$this->connectionName());

        return [
            'host' => $connection['host'] ?? '127.0.0.1',
            'port' => (string) ($connection['port'] ?? '3306'),
            'username' => $connection['username'] ?? '',
            'password' => $connection['password'] ?? '',
            'database' => $connection['database'] ?? '',
        ];
    }

    /**
     * Ensure the local backup directory exists and is writable.
     */
    private function ensureBackupDirectory(): string
    {
        $directory = storage_path('app/backups');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Backup storage directory could not be created.');
        }

        if (! is_writable($directory)) {
            throw new \RuntimeException('Backup storage directory is not writable.');
        }

        return $directory;
    }

    /**
     * Find an executable by name.
     */
    private function findExecutable(string $binary): ?string
    {
        return (new ExecutableFinder())->find($binary);
    }

    /**
     * Require an executable to be available before proceeding.
     */
    private function requireExecutable(string $binary): string
    {
        $path = $this->findExecutable($binary);

        if (! $path) {
            throw new \RuntimeException("Required binary '{$binary}' is not available on this server.");
        }

        return $path;
    }

    /**
     * Parse a SQL backup file for basic structure markers.
     */
    private function inspectSqlMarkers(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [
                'contains_schema' => false,
                'contains_data' => false,
            ];
        }

        $containsSchema = false;
        $containsData = false;
        $bytesRead = 0;

        while (! feof($handle) && $bytesRead < 262144) {
            $line = fgets($handle);

            if ($line === false) {
                break;
            }

            $bytesRead += strlen($line);
            $upper = strtoupper($line);

            if (! $containsSchema && str_contains($upper, 'CREATE TABLE')) {
                $containsSchema = true;
            }

            if (! $containsData && str_contains($upper, 'INSERT INTO')) {
                $containsData = true;
            }

            if ($containsSchema && $containsData) {
                break;
            }
        }

        fclose($handle);

        return [
            'contains_schema' => $containsSchema,
            'contains_data' => $containsData,
        ];
    }

    /**
     * Count database tables for generation metadata.
     */
    private function currentTableCount(string $databaseName): ?int
    {
        try {
            $result = DB::connection($this->connectionName())->selectOne(
                'SELECT COUNT(*) AS aggregate FROM information_schema.tables WHERE table_schema = ?',
                [$databaseName]
            );

            return isset($result->aggregate) ? (int) $result->aggregate : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Format a useful process error message.
     */
    private function processErrorMessage(Process $process, string $fallback): string
    {
        $message = trim($process->getErrorOutput() ?: $process->getOutput());

        return $message !== '' ? $message : $fallback;
    }

    /**
     * Resolve the active database driver.
     */
    private function driver(): string
    {
        return (string) config('database.connections.'.$this->connectionName().'.driver', $this->connectionName());
    }

    /**
     * Resolve the active database connection name.
     */
    private function connectionName(): string
    {
        return (string) config('database.default');
    }

    /**
     * Determine whether the current server should dump event definitions.
     */
    private function shouldDumpEvents(): bool
    {
        try {
            $row = DB::connection($this->connectionName())
                ->selectOne("SHOW VARIABLES LIKE 'event_scheduler'");

            return strtoupper((string) ($row->Value ?? $row->value ?? 'OFF')) === 'ON';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Determine whether the current server can safely enumerate routines.
     */
    private function shouldDumpRoutines(): bool
    {
        try {
            $connection = DB::connection($this->connectionName());
            $database = $this->connectionConfig()['database'];

            $connection->select('SHOW FUNCTION STATUS WHERE Db = ?', [$database]);
            $connection->select('SHOW PROCEDURE STATUS WHERE Db = ?', [$database]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Detect whether the dump client supports disabling column statistics.
     */
    private function dumpBinarySupportsColumnStatistics(string $binary): bool
    {
        static $supports = [];

        if (array_key_exists($binary, $supports)) {
            return $supports[$binary];
        }

        try {
            $process = new Process([$binary, '--help']);
            $process->setTimeout(10);
            $process->run();

            $output = $process->getOutput().$process->getErrorOutput();

            return $supports[$binary] = str_contains($output, '--column-statistics');
        } catch (\Throwable) {
            return $supports[$binary] = false;
        }
    }
}
