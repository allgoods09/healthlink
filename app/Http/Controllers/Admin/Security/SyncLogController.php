<?php

namespace App\Http\Controllers\Admin\Devices;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class SyncLogController extends Controller
{
    /**
     * Display a listing of sync logs.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', SyncLog::class);

        $query = SyncLog::query()->with('user');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'LIKE', "%{$search}%")
                  ->orWhere('error_message', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->latest()
                      ->paginate(25)
                      ->withQueryString();

        $users = User::where('role', 'bhw')->active()->get();
        $statuses = ['success', 'failed', 'partial'];

        return view('admin.devices.sync.index', compact('logs', 'users', 'statuses'));
    }

    /**
     * Display the specified sync log.
     */
    public function show(SyncLog $syncLog)
    {
        Gate::authorize('view', $syncLog);

        $syncLog->load('user');

        return view('admin.devices.sync.show', compact('syncLog'));
    }

    /**
     * Get sync statistics for the dashboard.
     */
    public function stats(Request $request)
    {
        Gate::authorize('viewAny', SyncLog::class);

        $query = SyncLog::query();

        // Filter by date range
        if ($request->filled('days')) {
            $days = (int) $request->days;
            $query->where('created_at', '>=', now()->subDays($days));
        } else {
            $query->where('created_at', '>=', now()->subDays(7));
        }

        $stats = [
            'total_syncs' => $query->count(),
            'successful' => (clone $query)->where('status', 'success')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'partial' => (clone $query)->where('status', 'partial')->count(),
            'total_records' => (clone $query)->sum('records_synced'),
            'avg_duration' => (clone $query)->whereNotNull('sync_duration')->avg('sync_duration'),
            'total_payload' => (clone $query)->sum('payload_size'),
        ];

        // Get daily sync counts for chart
        $daily = (clone $query)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $stats['daily'] = $daily;

        return response()->json($stats);
    }

    /**
     * Export sync logs as CSV.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', SyncLog::class);

        $query = SyncLog::query()->with('user');

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->get();

        // Generate CSV
        $filename = 'sync_logs_' . now()->format('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');

        // Headers
        fputcsv($handle, [
            'ID',
            'User',
            'Device',
            'Records Synced',
            'Payload Size',
            'Duration',
            'Status',
            'Timestamp'
        ]);

        // Data
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->user?->name ?? 'Unknown',
                $log->device_name ?? 'Unknown',
                $log->records_synced,
                $log->formatted_payload_size,
                $log->formatted_duration,
                $log->status,
                $log->created_at->format('Y-m-d H:i:s')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    /**
     * Clear old sync logs (older than 30 days).
     */
    public function clearOld()
    {
        Gate::authorize('viewAny', SyncLog::class);

        $cutoffDate = now()->subDays(30);
        $deletedCount = SyncLog::where('created_at', '<', $cutoffDate)->delete();

        // Log this action
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'deleted',
            'event_description' => "Cleared {$deletedCount} old sync logs older than 30 days",
            'model_type' => SyncLog::class,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->back()
            ->with('success', "Cleared {$deletedCount} sync logs older than 30 days.");
    }
}