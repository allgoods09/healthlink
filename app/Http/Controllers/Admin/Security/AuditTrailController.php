<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
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

        $logs = $this->filteredQuery($request)
                      ->latest()
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

        $logs = $this->filteredQuery($request)->latest()->get();

        $columns = [
            'ID' => 'id',
            'User' => fn (AuditLog $log) => $log->actor_name,
            'Event Type' => fn (AuditLog $log) => $log->event_type_label,
            'Description' => 'event_description',
            'Model' => fn (AuditLog $log) => $log->model_type ? class_basename($log->model_type) : 'N/A',
            'IP Address' => fn (AuditLog $log) => $log->ip_address ?? 'N/A',
            'Timestamp' => fn (AuditLog $log) => $log->created_at?->format('Y-m-d H:i:s'),
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'Event Type' => $request->filled('event_type') ? (AuditLog::EVENT_TYPES[$request->event_type] ?? $request->event_type) : null,
            'User' => User::find($request->integer('user_id'))?->name,
            'Date From' => $request->date_from,
            'Date To' => $request->date_to,
        ];

        $timestamp = now()->format('Y-m-d_His');
        $format = $request->string('format', 'csv')->toString();

        ExportAudit::log('audit trail', $format, [
            'model_type' => AuditLog::class,
            'record_count' => $logs->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("audit_logs_{$timestamp}.csv", $columns, $logs),
            'xlsx' => TabularExport::xlsx("audit_logs_{$timestamp}.xlsx", 'Audit Logs', $columns, $logs),
            'pdf' => TabularExport::pdf("audit_logs_{$timestamp}.pdf", 'Audit Trail Report', $columns, $logs, $filters),
            default => abort(404),
        };
    }

    /**
     * Clear old audit logs (older than 90 days).
     */
    public function clearOld(Request $request)
    {
        Gate::authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'confirmation_phrase' => ['required', 'in:CLEAR'],
            'action_reason' => ['required', 'string', 'max:500'],
        ]);

        $cutoffDate = now()->subDays(90);
        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

        // Log this action
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'deleted',
            'event_description' => "Cleared {$deletedCount} old audit logs older than 90 days",
            'model_type' => AuditLog::class,
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
            ->with('success', "Cleared {$deletedCount} audit logs older than 90 days.");
    }

    /**
     * Build the shared filtered query.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()->with('user');

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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('event_description', 'LIKE', "%{$search}%")
                    ->orWhere('model_type', 'LIKE', "%{$search}%")
                    ->orWhere('ip_address', 'LIKE', "%{$search}%");
            });
        }

        return $query;
    }
}
