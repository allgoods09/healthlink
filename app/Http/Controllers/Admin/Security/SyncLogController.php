<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
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

        $logs = $this->filteredQuery($request)
                      ->latest()
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

        $logs = $this->filteredQuery($request)->latest()->get();

        $columns = [
            'ID' => 'id',
            'BHW' => fn (SyncLog $log) => $log->user?->name ?? 'Unknown',
            'Device' => fn (SyncLog $log) => $log->device_name ?? 'Unknown',
            'Records Synced' => 'records_synced',
            'Payload Size' => fn (SyncLog $log) => $log->formatted_payload_size,
            'Duration' => fn (SyncLog $log) => $log->formatted_duration,
            'Status' => fn (SyncLog $log) => ucfirst($log->status),
            'Error' => fn (SyncLog $log) => $log->error_message ?: 'N/A',
            'Timestamp' => fn (SyncLog $log) => $log->created_at?->format('Y-m-d H:i:s'),
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'BHW' => User::find($request->integer('user_id'))?->name,
            'Status' => $request->filled('status') ? ucfirst($request->status) : null,
            'Date From' => $request->date_from,
            'Date To' => $request->date_to,
        ];

        $timestamp = now()->format('Y-m-d_His');
        $format = $request->string('format', 'csv')->toString();

        ExportAudit::log('sync logs', $format, [
            'model_type' => SyncLog::class,
            'record_count' => $logs->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("sync_logs_{$timestamp}.csv", $columns, $logs),
            'xlsx' => TabularExport::xlsx("sync_logs_{$timestamp}.xlsx", 'Sync Logs', $columns, $logs),
            'pdf' => TabularExport::pdf("sync_logs_{$timestamp}.pdf", 'Synchronization Report', $columns, $logs, $filters),
            default => abort(404),
        };
    }

    /**
     * Clear old sync logs (older than 30 days).
     */
    public function clearOld(Request $request)
    {
        Gate::authorize('viewAny', SyncLog::class);

        $validated = $request->validate([
            'confirmation_phrase' => ['required', 'in:CLEAR'],
            'action_reason' => ['required', 'string', 'max:500'],
        ]);

        $cutoffDate = now()->subDays(30);
        $deletedCount = SyncLog::where('created_at', '<', $cutoffDate)->delete();

        // Log this action
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'deleted',
            'event_description' => "Cleared {$deletedCount} old sync logs older than 30 days",
            'model_type' => SyncLog::class,
            'metadata' => [
                'confirmation_phrase' => $validated['confirmation_phrase'],
                'action_reason' => $validated['action_reason'],
                'cutoff_date' => $cutoffDate->toDateString(),
                'deleted_count' => $deletedCount,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->back()
            ->with('success', "Cleared {$deletedCount} sync logs older than 30 days.");
    }

    /**
     * Build the filtered sync log query.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = SyncLog::query()->with('user');

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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('device_name', 'LIKE', "%{$search}%")
                    ->orWhere('error_message', 'LIKE', "%{$search}%");
            });
        }

        return $query;
    }
}
