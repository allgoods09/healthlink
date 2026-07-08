<?php

namespace App\Http\Controllers\Admin\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class SystemMetricsController extends Controller
{
    /**
     * Display system metrics.
     */
    public function index()
    {
        Gate::authorize('viewAny', \App\Models\Setting::class);

        // Database metrics
        $dbMetrics = $this->getDatabaseMetrics();

        // Storage metrics
        $storageMetrics = $this->getStorageMetrics();

        // System metrics
        $systemMetrics = $this->getSystemMetrics();

        // User metrics
        $userMetrics = $this->getUserMetrics();

        return view('admin.maintenance.metrics.index', compact(
            'dbMetrics',
            'storageMetrics',
            'systemMetrics',
            'userMetrics'
        ));
    }

    /**
     * Get database metrics.
     */
    private function getDatabaseMetrics()
    {
        $databaseName = config('database.connections.mysql.database');

        // Get total database size
        $size = DB::select("
            SELECT 
                SUM(data_length + index_length) / 1024 / 1024 AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = ?
        ", [$databaseName]);

        // Get table counts
        $tables = DB::select("
            SELECT 
                table_name,
                table_rows AS row_count,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = ?
            ORDER BY (data_length + index_length) DESC
        ", [$databaseName]);

        return [
            'total_size_mb' => round($size[0]->size_mb ?? 0, 2),
            'tables' => $tables,
            'connection_status' => 'connected',
            'connection_name' => config('database.default'),
        ];
    }

    /**
     * Get storage metrics.
     */
    private function getStorageMetrics()
    {
        $storagePath = storage_path();
        $totalSpace = disk_total_space($storagePath);
        $freeSpace = disk_free_space($storagePath);
        $usedSpace = $totalSpace - $freeSpace;

        // Get backup storage
        $backupPath = storage_path('app/backups');
        $backupSize = 0;
        if (is_dir($backupPath)) {
            $backupSize = $this->getDirectorySize($backupPath);
        }

        // Get log storage
        $logPath = storage_path('logs');
        $logSize = 0;
        if (is_dir($logPath)) {
            $logSize = $this->getDirectorySize($logPath);
        }

        return [
            'total_gb' => round($totalSpace / 1073741824, 2),
            'free_gb' => round($freeSpace / 1073741824, 2),
            'used_gb' => round($usedSpace / 1073741824, 2),
            'used_percentage' => round(($usedSpace / $totalSpace) * 100, 2),
            'backup_size_mb' => round($backupSize / 1048576, 2),
            'log_size_mb' => round($logSize / 1048576, 2),
        ];
    }

    /**
     * Get system metrics.
     */
    private function getSystemMetrics()
    {
        return [
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'server_time' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug') ? 'Enabled' : 'Disabled',
            'session_driver' => config('session.driver'),
            'cache_driver' => config('cache.default'),
            'queue_connection' => config('queue.default'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . ' seconds',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        ];
    }

    /**
     * Get user metrics.
     */
    private function getUserMetrics()
    {
        $totalUsers = \App\Models\User::count();
        $activeUsers = \App\Models\User::where('is_active', true)->count();
        $inactiveUsers = \App\Models\User::where('is_active', false)->count();

        $roles = [];
        foreach (\App\Models\User::ROLES as $key => $label) {
            $roles[$key] = \App\Models\User::where('role', $key)->count();
        }

        $onlineUsers = \App\Models\User::where('last_activity', '>=', now()->subMinutes(15))->count();

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $inactiveUsers,
            'online' => $onlineUsers,
            'by_role' => $roles,
        ];
    }

    /**
     * Get directory size recursively.
     */
    private function getDirectorySize($path)
    {
        $size = 0;
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                $size += filesize($filePath);
            } elseif (is_dir($filePath)) {
                $size += $this->getDirectorySize($filePath);
            }
        }
        return $size;
    }

    /**
     * Get database query performance.
     */
    public function queryPerformance()
    {
        Gate::authorize('viewAny', \App\Models\Setting::class);

        // Get slow queries if enabled in MySQL
        try {
            $slowQueries = DB::select("
                SELECT * FROM mysql.slow_log 
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                ORDER BY query_time DESC 
                LIMIT 20
            ");
        } catch (\Exception $e) {
            $slowQueries = [];
        }

        return response()->json([
            'slow_queries' => $slowQueries,
            'connection_pool' => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_CONNECTION_STATUS),
        ]);
    }

    /**
     * Check system health.
     */
    public function health()
    {
        $health = [
            'status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'storage' => $this->checkStorage(),
                'session' => $this->checkSession(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        foreach ($health['checks'] as $key => $check) {
            if (!$check) {
                $health['status'] = 'unhealthy';
                break;
            }
        }

        return response()->json($health);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        $storagePath = storage_path();
        $freeSpace = disk_free_space($storagePath);
        $totalSpace = disk_total_space($storagePath);
        $percentageUsed = (($totalSpace - $freeSpace) / $totalSpace) * 100;

        // Storage is unhealthy if more than 80% full
        return $percentageUsed < 80;
    }

    private function checkSession(): bool
    {
        try {
            session()->put('health_check', true);
            $result = session()->get('health_check');
            session()->forget('health_check');
            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            cache()->put('health_check', true, 1);
            $result = cache()->get('health_check');
            cache()->forget('health_check');
            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            $connection = config('queue.default');
            // Just check if the connection is configured
            return !empty($connection);
        } catch (\Exception $e) {
            return false;
        }
    }
}