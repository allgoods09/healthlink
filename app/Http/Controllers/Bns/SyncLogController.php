<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\Purok;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SyncLogController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $logs = $this->filteredQuery($request)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.devices.sync.index', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'bns',
            'pageTitle' => 'Sync Logs - HealthLink BNS',
            'pageHeader' => 'BHW Sync Logs',
            'canClearOld' => false,
            'logs' => $logs,
            'users' => $this->bnsBhwsQuery(false)->active()->orderBy('name')->get(),
            'statuses' => [SyncLog::STATUS_SUCCESS, SyncLog::STATUS_FAILED, SyncLog::STATUS_PARTIAL],
            'puroks' => $this->bnsPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function show(SyncLog $syncLog): View
    {
        $this->ensureSyncLogBelongsToBarangay($syncLog);
        $syncLog->load('user.assignedPurok');

        return view('admin.devices.sync.show', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'bns',
            'pageTitle' => 'Sync Log Details - HealthLink BNS',
            'pageHeader' => 'Sync Log Details',
            'syncLog' => $syncLog,
        ]);
    }

    public function export(Request $request)
    {
        $logs = $this->filteredQuery($request)->latest()->get();

        $columns = [
            'ID' => 'id',
            'BHW' => fn (SyncLog $log) => $log->user?->name ?? 'Unknown',
            'Purok' => fn (SyncLog $log) => $log->user?->assignedPurok?->display_name ?? 'Unknown',
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
            'Purok' => Purok::find($request->integer('purok_id'))?->display_name,
            'Status' => $request->filled('status') ? ucfirst($request->status) : null,
            'Date From' => $request->date_from,
            'Date To' => $request->date_to,
        ];

        $timestamp = now()->format('Y-m-d_His');
        $format = $request->string('format', 'csv')->toString();

        ExportAudit::log('bns sync logs', $format, [
            'model_type' => SyncLog::class,
            'record_count' => $logs->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("bns_sync_logs_{$timestamp}.csv", $columns, $logs),
            'xlsx' => TabularExport::xlsx("bns_sync_logs_{$timestamp}.xlsx", 'BNS Sync Logs', $columns, $logs),
            'pdf' => TabularExport::pdf("bns_sync_logs_{$timestamp}.pdf", 'BHW Synchronization Report', $columns, $logs, $filters),
            default => abort(404),
        };
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->bnsSyncLogsQuery()->with('user.assignedPurok');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('purok_id')) {
            $query->whereHas('user', function (Builder $builder) use ($request): void {
                $builder->where('assigned_purok_id', $request->integer('purok_id'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('device_name', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
