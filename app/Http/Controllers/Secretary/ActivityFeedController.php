<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ActivityFeedController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $logs = $this->filteredQuery($request)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.security.audit.index', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary.activity',
            'pageTitle' => 'Barangay Activity Feed - HealthLink Secretary',
            'pageHeader' => 'Barangay Activity Feed',
            'canClearOld' => false,
            'logs' => $logs,
            'eventTypes' => AuditLog::EVENT_TYPES,
            'users' => $this->barangayUsersQuery()->active()->orderBy('name')->get(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        Gate::authorize('view', $auditLog);
        $this->ensureAuditLogBelongsToBarangay($auditLog);

        $auditLog->load('user');

        return view('admin.security.audit.show', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary.activity',
            'pageTitle' => 'Activity Entry - HealthLink Secretary',
            'pageHeader' => 'Activity Entry',
            'auditLog' => $auditLog,
        ]);
    }

    public function export(Request $request): Response
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
            'User' => $this->barangayUsersQuery()->find($request->integer('user_id'))?->name,
            'Date From' => $request->date_from,
            'Date To' => $request->date_to,
        ];

        $format = $request->string('format', 'csv')->toString();
        $timestamp = now()->format('Y-m-d_His');

        ExportAudit::log('secretary activity feed', $format, [
            'model_type' => AuditLog::class,
            'record_count' => $logs->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("secretary_activity_{$timestamp}.csv", $columns, $logs),
            'xlsx' => TabularExport::xlsx("secretary_activity_{$timestamp}.xlsx", 'Secretary Activity', $columns, $logs),
            'pdf' => TabularExport::pdf("secretary_activity_{$timestamp}.pdf", 'Barangay Activity Feed', $columns, $logs, $filters),
            default => abort(404),
        };
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->secretaryActivityQuery();

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
            $search = $request->string('search')->toString();
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('event_description', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
