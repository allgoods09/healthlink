<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class AuditTrailController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('user');

        // Filter by event type
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
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
                $q->where('event_description', 'LIKE', "%{$search}%")
                  ->orWhere('model_type', 'LIKE', "%{$search}%")
                  ->orWhere('ip_address', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->latest()
                      ->paginate(25)
                      ->withQueryString();

        $eventTypes = AuditLog::EVENT_TYPES;
        $users = User::active()->get();

        return view('admin.security.audit.index', compact('logs', 'eventTypes', 'users'));
    }

    /**
     * Display the specified audit log.
     */
    public function show(AuditLog $auditLog)
    {
        Gate::authorize('view', $auditLog);

        $auditLog->load('user');

        return view('admin.security.audit.show', compact('auditLog'));
    }

    /**
     * Export audit logs as CSV.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('user');

        // Apply same filters as index
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->get();

        // Generate CSV
        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');

        // Headers
        fputcsv($handle, [
            'ID',
            'User',
            'Event Type',
            'Description',
            'Model',
            'IP Address',
            'Timestamp'
        ]);

        // Data
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->actor_name,
                $log->event_type_label,
                $log->event_description,
                $log->model_type ? class_basename($log->model_type) : 'N/A',
                $log->ip_address ?? 'N/A',
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
     * Clear old audit logs (older than 90 days).
     */
    public function clearOld()
    {
        Gate::authorize('viewAny', AuditLog::class);

        $cutoffDate = now()->subDays(90);
        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

        // Log this action
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'deleted',
            'event_description' => "Cleared {$deletedCount} old audit logs older than 90 days",
            'model_type' => AuditLog::class,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->back()
            ->with('success', "Cleared {$deletedCount} audit logs older than 90 days.");
    }
}