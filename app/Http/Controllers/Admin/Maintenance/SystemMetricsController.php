<?php

namespace App\Http\Controllers\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class SystemMetricsController extends Controller
{
    /**
     * Display system metrics.
     */
    public function index()
    {
        Gate::authorize('viewAny', Setting::class);

        $dbMetrics = $this->getDatabaseMetrics();
        $storageMetrics = $this->getStorageMetrics();
        $systemMetrics = $this->getSystemMetrics($dbMetrics);
        $userMetrics = $this->getUserMetrics();

        return view('admin.maintenance.metrics.index', compact(
            'dbMetrics',
            'storageMetrics',
            'systemMetrics',
            'userMetrics',
        ));
    }

    /**
     * Get database metrics in a driver-aware way.
     */
    private function getDatabaseMetrics(): array
    {
        $connectionName = config('database.default');
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $databaseName = $this->resolveDatabaseName($connectionName, $driver);

        $metrics = [
            'connected' => false,
            'connection_status' => 'unavailable',
            'connection_name' => $connectionName,
            'driver' => $driver,
            'database_name' => $databaseName,
            'total_size_mb' => null,
            'total_size_label' => 'Unavailable',
            'supports_table_sizes' => false,
            'supports_row_estimates' => false,
            'tables' => [],
            'notes' => [],
        ];

        try {
            $connection->getPdo();
            $metrics['connected'] = true;
            $metrics['connection_status'] = 'connected';
        } catch (Throwable $exception) {
            $metrics['notes'][] = 'Database connection is unavailable: '.$this->safeMessage($exception);

            return $metrics;
        }

        try {
            return match ($driver) {
                'mysql' => array_merge($metrics, $this->getMysqlDatabaseMetrics($connectionName, (string) $databaseName)),
                'pgsql' => array_merge($metrics, $this->getPostgresDatabaseMetrics($connectionName)),
                'sqlite' => array_merge($metrics, $this->getSqliteDatabaseMetrics($connectionName)),
                default => array_merge($metrics, $this->getGenericDatabaseMetrics($connectionName, $driver)),
            };
        } catch (Throwable $exception) {
            $metrics['notes'][] = 'Detailed table metrics were unavailable: '.$this->safeMessage($exception);
            $fallbackMetrics = $this->getGenericDatabaseMetrics($connectionName, $driver);
            $fallbackMetrics['notes'] = array_merge($metrics['notes'], $fallbackMetrics['notes'] ?? []);

            return array_merge($metrics, $fallbackMetrics);
        }
    }

    /**
     * Get MySQL / MariaDB metrics using information_schema.
     */
    private function getMysqlDatabaseMetrics(string $connectionName, string $databaseName): array
    {
        $size = DB::connection($connectionName)->selectOne(
            'SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb
             FROM information_schema.tables
             WHERE table_schema = ?',
            [$databaseName],
        );

        $tables = DB::connection($connectionName)->select(
            'SELECT
                table_name,
                table_rows AS row_count,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables
             WHERE table_schema = ?
             ORDER BY (data_length + index_length) DESC, table_name ASC',
            [$databaseName],
        );

        return [
            'total_size_mb' => isset($size?->size_mb) ? round((float) $size->size_mb, 2) : null,
            'total_size_label' => isset($size?->size_mb) ? round((float) $size->size_mb, 2).' MB' : 'Unavailable',
            'supports_table_sizes' => true,
            'supports_row_estimates' => true,
            'tables' => array_map(function (object $table): array {
                return [
                    'table_name' => $table->table_name,
                    'row_count' => isset($table->row_count) ? (int) $table->row_count : null,
                    'size_mb' => isset($table->size_mb) ? (float) $table->size_mb : null,
                    'size_label' => isset($table->size_mb) ? ((float) $table->size_mb).' MB' : 'Unavailable',
                ];
            }, $tables),
            'notes' => [
                'MySQL table row counts are approximate for some storage engines such as InnoDB.',
            ],
        ];
    }

    /**
     * Get PostgreSQL metrics when pg_total_relation_size is available.
     */
    private function getPostgresDatabaseMetrics(string $connectionName): array
    {
        $size = DB::connection($connectionName)->selectOne(
            'SELECT pg_database_size(current_database()) / 1024.0 / 1024.0 AS size_mb',
        );

        $tables = DB::connection($connectionName)->select(
            "SELECT
                c.relname AS table_name,
                COALESCE(s.n_live_tup, 0) AS row_count,
                ROUND(pg_total_relation_size(c.oid)::numeric / 1024 / 1024, 2) AS size_mb
             FROM pg_class c
             INNER JOIN pg_namespace n ON n.oid = c.relnamespace
             LEFT JOIN pg_stat_user_tables s ON s.relid = c.oid
             WHERE c.relkind = 'r'
               AND n.nspname = 'public'
             ORDER BY pg_total_relation_size(c.oid) DESC, c.relname ASC",
        );

        return [
            'total_size_mb' => isset($size?->size_mb) ? round((float) $size->size_mb, 2) : null,
            'total_size_label' => isset($size?->size_mb) ? round((float) $size->size_mb, 2).' MB' : 'Unavailable',
            'supports_table_sizes' => true,
            'supports_row_estimates' => true,
            'tables' => array_map(function (object $table): array {
                return [
                    'table_name' => $table->table_name,
                    'row_count' => isset($table->row_count) ? (int) $table->row_count : null,
                    'size_mb' => isset($table->size_mb) ? (float) $table->size_mb : null,
                    'size_label' => isset($table->size_mb) ? ((float) $table->size_mb).' MB' : 'Unavailable',
                ];
            }, $tables),
            'notes' => [
                'PostgreSQL row counts come from statistics and may lag behind very recent writes.',
            ],
        ];
    }

    /**
     * Get SQLite metrics from the database file and live table counts.
     */
    private function getSqliteDatabaseMetrics(string $connectionName): array
    {
        $databasePath = config("database.connections.{$connectionName}.database");
        $notes = [];
        $totalSizeMb = null;

        if ($databasePath === ':memory:') {
            $notes[] = 'SQLite is running in-memory, so file size metrics are unavailable.';
        } elseif (is_string($databasePath) && is_file($databasePath)) {
            $totalSizeMb = round(filesize($databasePath) / 1048576, 2);
        } else {
            $notes[] = 'SQLite database file size could not be resolved from the configured path.';
        }

        $tables = $this->buildGenericTableMetrics($connectionName, true);

        return [
            'total_size_mb' => $totalSizeMb,
            'total_size_label' => $totalSizeMb !== null ? $totalSizeMb.' MB' : 'Unavailable',
            'supports_table_sizes' => false,
            'supports_row_estimates' => false,
            'tables' => $tables,
            'notes' => array_merge($notes, [
                'SQLite does not expose per-table storage usage the same way server databases do.',
            ]),
        ];
    }

    /**
     * Get generic table counts for unsupported or restricted drivers.
     */
    private function getGenericDatabaseMetrics(string $connectionName, string $driver): array
    {
        $notes = [
            'Detailed size metrics are not available for the current '.$driver.' driver, so HealthLink is falling back to safe table counts only.',
        ];

        return [
            'total_size_mb' => null,
            'total_size_label' => 'Unavailable',
            'supports_table_sizes' => false,
            'supports_row_estimates' => false,
            'tables' => $this->buildGenericTableMetrics($connectionName),
            'notes' => $notes,
        ];
    }

    /**
     * Build generic table metrics using schema listings and row counts.
     */
    private function buildGenericTableMetrics(string $connectionName, bool $sortByRowCount = false): array
    {
        $tableNames = Schema::connection($connectionName)->getTableListing();

        $tables = collect($tableNames)
            ->map(function (string $tableName) use ($connectionName): array {
                return [
                    'table_name' => $tableName,
                    'row_count' => $this->countTableRows($connectionName, $tableName),
                    'size_mb' => null,
                    'size_label' => 'Unavailable',
                ];
            });

        $tables = $sortByRowCount
            ? $tables->sortByDesc(fn (array $table) => $table['row_count'] ?? -1)
            : $tables->sortBy('table_name');

        return $tables->values()->all();
    }

    /**
     * Safely count rows in a single table.
     */
    private function countTableRows(string $connectionName, string $tableName): ?int
    {
        try {
            $wrappedTable = DB::connection($connectionName)->getQueryGrammar()->wrapTable($tableName);
            $row = DB::connection($connectionName)->selectOne("SELECT COUNT(*) AS aggregate FROM {$wrappedTable}");

            return isset($row?->aggregate) ? (int) $row->aggregate : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get storage metrics.
     */
    private function getStorageMetrics(): array
    {
        $storagePath = storage_path();
        $notes = [];

        $totalSpace = @disk_total_space($storagePath);
        $freeSpace = @disk_free_space($storagePath);

        $metrics = [
            'storage_root' => $storagePath,
            'disk_stats_available' => is_numeric($totalSpace) && is_numeric($freeSpace) && $totalSpace > 0,
            'total_gb' => null,
            'free_gb' => null,
            'used_gb' => null,
            'used_percentage' => null,
            'total_label' => 'Unavailable',
            'free_label' => 'Unavailable',
            'used_label' => 'Unavailable',
            'backup_size_mb' => null,
            'backup_size_label' => 'Unavailable',
            'log_size_mb' => null,
            'log_size_label' => 'Unavailable',
            'notes' => [],
        ];

        if ($metrics['disk_stats_available']) {
            $usedSpace = $totalSpace - $freeSpace;

            $metrics['total_gb'] = round($totalSpace / 1073741824, 2);
            $metrics['free_gb'] = round($freeSpace / 1073741824, 2);
            $metrics['used_gb'] = round($usedSpace / 1073741824, 2);
            $metrics['used_percentage'] = round(($usedSpace / $totalSpace) * 100, 2);
            $metrics['total_label'] = $metrics['total_gb'].' GB';
            $metrics['free_label'] = $metrics['free_gb'].' GB';
            $metrics['used_label'] = $metrics['used_gb'].' GB ('.$metrics['used_percentage'].'%)';
        } else {
            $notes[] = 'Disk-space statistics are unavailable on this deployment target, so only directory-level checks are shown.';
        }

        $backupSize = $this->getDirectorySize(storage_path('app/backups'));
        $logSize = $this->getDirectorySize(storage_path('logs'));

        if ($backupSize !== null) {
            $metrics['backup_size_mb'] = round($backupSize / 1048576, 2);
            $metrics['backup_size_label'] = $metrics['backup_size_mb'].' MB';
        } else {
            $notes[] = 'Backup directory size could not be calculated.';
        }

        if ($logSize !== null) {
            $metrics['log_size_mb'] = round($logSize / 1048576, 2);
            $metrics['log_size_label'] = $metrics['log_size_mb'].' MB';
        } else {
            $notes[] = 'Log directory size could not be calculated.';
        }

        $metrics['notes'] = $notes;

        return $metrics;
    }

    /**
     * Get system metrics.
     */
    private function getSystemMetrics(array $dbMetrics): array
    {
        $serverLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : null;

        $notes = [];

        if ($serverLoad === false || $serverLoad === null) {
            $notes[] = 'Server load averages are not exposed by the current PHP runtime or host environment.';
        }

        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
            'server_time' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug') ? 'Enabled' : 'Disabled',
            'session_driver' => config('session.driver'),
            'cache_driver' => config('cache.default'),
            'queue_connection' => config('queue.default'),
            'database_driver' => $dbMetrics['driver'] ?? 'unknown',
            'database_name' => $dbMetrics['database_name'] ?? 'unknown',
            'memory_limit' => ini_get('memory_limit') ?: 'Not Set',
            'max_execution_time' => (ini_get('max_execution_time') ?: '0').' seconds',
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'Unknown',
            'post_max_size' => ini_get('post_max_size') ?: 'Unknown',
            'hostname' => gethostname() ?: 'Unavailable',
            'server_load' => is_array($serverLoad) ? $serverLoad : null,
            'server_load_label' => is_array($serverLoad)
                ? implode(', ', array_map(fn (float|int $value): string => number_format((float) $value, 2), $serverLoad))
                : 'Unavailable',
            'notes' => $notes,
        ];
    }

    /**
     * Get user metrics.
     */
    private function getUserMetrics(): array
    {
        $notes = [];

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();

        $roles = [];
        foreach (User::ROLES as $key => $label) {
            $roles[$key] = User::where('role', $key)->count();
        }

        $onlineUsers = null;

        try {
            if (Schema::hasColumn('users', 'last_activity')) {
                $onlineUsers = User::where('last_activity', '>=', now()->subMinutes(15))->count();
            } else {
                $notes[] = 'The users table does not expose a last_activity column, so online-user estimation is unavailable.';
            }
        } catch (Throwable $exception) {
            $notes[] = 'Online-user estimation is unavailable: '.$this->safeMessage($exception);
        }

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $inactiveUsers,
            'online' => $onlineUsers,
            'by_role' => $roles,
            'notes' => $notes,
        ];
    }

    /**
     * Get directory size recursively.
     */
    private function getDirectorySize(string $path): ?int
    {
        if (! is_dir($path)) {
            return 0;
        }

        try {
            $size = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }

            return $size;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get database query performance.
     */
    public function queryPerformance(): JsonResponse
    {
        Gate::authorize('viewAny', Setting::class);

        $connectionName = config('database.default');
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        $payload = [
            'driver' => $driver,
            'connection_name' => $connectionName,
            'connection_status' => 'connected',
            'supports_slow_query_logs' => false,
            'slow_queries' => [],
            'meta' => [],
            'notes' => [],
        ];

        try {
            $connection->getPdo();
        } catch (Throwable $exception) {
            $payload['connection_status'] = 'unavailable';
            $payload['notes'][] = 'Database connection is unavailable: '.$this->safeMessage($exception);

            return response()->json($payload, 503);
        }

        try {
            if ($driver === 'mysql') {
                $payload = array_merge($payload, $this->getMysqlQueryPerformance($connectionName));
            } elseif ($driver === 'pgsql') {
                $payload = array_merge($payload, $this->getPostgresQueryPerformance($connectionName));
            } elseif ($driver === 'sqlite') {
                $payload['notes'][] = 'SQLite does not expose server-level slow query logs.';
            } else {
                $payload['notes'][] = 'Slow-query telemetry is not implemented for the current '.$driver.' driver.';
            }
        } catch (Throwable $exception) {
            $payload['notes'][] = 'Query-performance telemetry is unavailable: '.$this->safeMessage($exception);
        }

        $payload['connection_details'] = $this->safeConnectionStatus($connection);

        return response()->json($payload);
    }

    /**
     * Check system health.
     */
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'session' => $this->checkSession(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $status = collect($checks)->contains(fn (array $check): bool => ! $check['healthy'])
            ? 'unhealthy'
            : 'healthy';

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $status === 'healthy' ? 200 : 503);
    }

    /**
     * Resolve connection-specific database naming safely.
     */
    private function resolveDatabaseName(string $connectionName, string $driver): string
    {
        $database = config("database.connections.{$connectionName}.database");

        if ($driver === 'sqlite') {
            if ($database === ':memory:') {
                return 'SQLite In-Memory';
            }

            return is_string($database) && $database !== ''
                ? basename($database)
                : 'SQLite Database';
        }

        if (is_string($database) && $database !== '') {
            return $database;
        }

        return 'Unknown';
    }

    /**
     * Get MySQL query-performance details.
     */
    private function getMysqlQueryPerformance(string $connectionName): array
    {
        $slowQueryLogRow = DB::connection($connectionName)->selectOne("SHOW VARIABLES LIKE 'slow_query_log'");
        $longQueryTimeRow = DB::connection($connectionName)->selectOne("SHOW VARIABLES LIKE 'long_query_time'");

        $isEnabled = isset($slowQueryLogRow?->Value) && strtolower((string) $slowQueryLogRow->Value) === 'on';

        $result = [
            'supports_slow_query_logs' => true,
            'meta' => [
                'slow_query_log' => $slowQueryLogRow?->Value ?? 'unknown',
                'long_query_time' => $longQueryTimeRow?->Value ?? 'unknown',
            ],
            'notes' => [],
            'slow_queries' => [],
        ];

        if (! $isEnabled) {
            $result['notes'][] = 'MySQL slow_query_log is currently disabled on this server.';

            return $result;
        }

        try {
            $result['slow_queries'] = DB::connection($connectionName)->select(
                'SELECT start_time, query_time, sql_text
                 FROM mysql.slow_log
                 WHERE start_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                 ORDER BY query_time DESC
                 LIMIT 20',
            );
        } catch (Throwable $exception) {
            $result['notes'][] = 'Slow-log rows are not readable with the current database privileges: '.$this->safeMessage($exception);
        }

        return $result;
    }

    /**
     * Get PostgreSQL query-performance details via pg_stat_statements when available.
     */
    private function getPostgresQueryPerformance(string $connectionName): array
    {
        $extension = DB::connection($connectionName)->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM pg_extension
                WHERE extname = 'pg_stat_statements'
            ) AS available",
        );

        $available = (bool) ($extension?->available ?? false);

        $result = [
            'supports_slow_query_logs' => $available,
            'meta' => [
                'pg_stat_statements' => $available ? 'enabled' : 'disabled',
            ],
            'notes' => [],
            'slow_queries' => [],
        ];

        if (! $available) {
            $result['notes'][] = 'pg_stat_statements is not enabled, so PostgreSQL slow-query telemetry is unavailable.';

            return $result;
        }

        try {
            $result['slow_queries'] = DB::connection($connectionName)->select(
                'SELECT
                    LEFT(query, 500) AS sql_text,
                    calls,
                    ROUND(total_exec_time::numeric, 2) AS total_exec_time_ms,
                    ROUND(mean_exec_time::numeric, 2) AS mean_exec_time_ms
                 FROM pg_stat_statements
                 ORDER BY total_exec_time DESC
                 LIMIT 20',
            );
        } catch (Throwable $exception) {
            $result['notes'][] = 'pg_stat_statements is enabled but not readable with the current privileges: '.$this->safeMessage($exception);
        }

        return $result;
    }

    /**
     * Get a safe connection-status string from PDO when available.
     */
    private function safeConnectionStatus($connection): string
    {
        try {
            return (string) $connection->getPdo()->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        } catch (Throwable) {
            return 'Unavailable';
        }
    }

    /**
     * Check database health.
     */
    private function checkDatabase(): array
    {
        try {
            $connection = DB::connection();
            $connection->select('SELECT 1');

            return [
                'healthy' => true,
                'details' => 'Database connection responded successfully using the '.config('database.default').' driver.',
            ];
        } catch (Throwable $exception) {
            return [
                'healthy' => false,
                'details' => 'Database check failed: '.$this->safeMessage($exception),
            ];
        }
    }

    /**
     * Check storage health.
     */
    private function checkStorage(): array
    {
        $storagePath = storage_path();
        $totalSpace = @disk_total_space($storagePath);
        $freeSpace = @disk_free_space($storagePath);

        if (is_numeric($totalSpace) && is_numeric($freeSpace) && $totalSpace > 0) {
            $percentageUsed = (($totalSpace - $freeSpace) / $totalSpace) * 100;

            return [
                'healthy' => $percentageUsed < 80,
                'details' => 'Storage usage is currently '.number_format($percentageUsed, 2).'% of the available disk.',
            ];
        }

        return [
            'healthy' => is_writable($storagePath),
            'details' => is_writable($storagePath)
                ? 'Disk statistics are unavailable, but the storage directory is writable.'
                : 'Storage directory is not writable.',
        ];
    }

    /**
     * Check session health.
     */
    private function checkSession(): array
    {
        try {
            $key = 'health_check_'.uniqid('', true);
            session()->put($key, true);
            $result = session()->get($key);
            session()->forget($key);

            return [
                'healthy' => $result === true,
                'details' => $result === true
                    ? 'Session driver is responding normally.'
                    : 'Session driver did not round-trip the health-check value.',
            ];
        } catch (Throwable $exception) {
            return [
                'healthy' => false,
                'details' => 'Session check failed: '.$this->safeMessage($exception),
            ];
        }
    }

    /**
     * Check cache health.
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_'.uniqid('', true);
            Cache::put($key, true, 60);
            $result = Cache::get($key);
            Cache::forget($key);

            return [
                'healthy' => $result === true,
                'details' => $result === true
                    ? 'Cache driver is responding normally.'
                    : 'Cache driver did not round-trip the health-check value.',
            ];
        } catch (Throwable $exception) {
            return [
                'healthy' => false,
                'details' => 'Cache check failed: '.$this->safeMessage($exception),
            ];
        }
    }

    /**
     * Check queue configuration health.
     */
    private function checkQueue(): array
    {
        try {
            $connection = (string) config('queue.default');
            $config = config("queue.connections.{$connection}");

            return [
                'healthy' => $connection !== '' && is_array($config),
                'details' => $connection !== '' && is_array($config)
                    ? 'Queue connection ['.$connection.'] is configured.'
                    : 'Queue connection ['.$connection.'] is not configured correctly.',
            ];
        } catch (Throwable $exception) {
            return [
                'healthy' => false,
                'details' => 'Queue check failed: '.$this->safeMessage($exception),
            ];
        }
    }

    /**
     * Reduce noisy exception messages for operator-facing metrics.
     */
    private function safeMessage(Throwable $exception): string
    {
        return trim($exception->getMessage()) !== ''
            ? $exception->getMessage()
            : class_basename($exception);
    }
}
