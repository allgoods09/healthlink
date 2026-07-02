<?php

namespace App\Http\Controllers\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\ArchivedRecord;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class DataArchiveController extends Controller
{
    /**
     * Display a listing of archived records.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ArchivedRecord::class);

        $query = ArchivedRecord::query()
            ->with(['archivedBy', 'purgedBy']);

        // Filter by table
        if ($request->filled('table')) {
            $query->where('original_table', $request->table);
        }

        // Filter by purged status
        if ($request->filled('purged')) {
            $query->where('is_purged', $request->purged === 'true');
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
                $q->where('original_table', 'LIKE', "%{$search}%")
                  ->orWhere('archiving_reason', 'LIKE', "%{$search}%");
            });
        }

        $archives = $query->latest()
                          ->paginate(20)
                          ->withQueryString();

        $tables = ['residents', 'households', 'users', 'barangays', 'puroks'];

        return view('admin.maintenance.archive.index', compact('archives', 'tables'));
    }

    /**
     * Show the form for archiving a record.
     */
    public function create()
    {
        Gate::authorize('create', ArchivedRecord::class);

        $tables = [
            'residents' => Resident::class,
            'households' => Household::class,
            'users' => User::class,
        ];

        return view('admin.maintenance.archive.create', compact('tables'));
    }

    /**
     * Archive a specific record.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', ArchivedRecord::class);

        $request->validate([
            'table' => ['required', 'in:residents,households,users,barangays,puroks'],
            'record_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $table = $request->table;
        $recordId = $request->record_id;

        // Map table to model
        $models = [
            'residents' => Resident::class,
            'households' => Household::class,
            'users' => User::class,
            'barangays' => \App\Models\Barangay::class,
            'puroks' => \App\Models\Purok::class,
        ];

        $modelClass = $models[$table];
        $model = $modelClass::find($recordId);

        if (!$model) {
            return redirect()
                ->back()
                ->with('error', "Record not found in {$table}.");
        }

        // Check if already archived
        $exists = ArchivedRecord::where('original_table', $table)
                                ->where('original_id', $recordId)
                                ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->with('error', 'This record has already been archived.');
        }

        // Create archive
        $archive = ArchivedRecord::archive($model, Auth::user(), $request->reason);

        // Log the archival
        \App\Models\AuditLog::logMutation('deleted', Auth::user(), $model, $model->toArray());

        return redirect()
            ->route('admin.archive.index')
            ->with('success', "Record successfully archived.");
    }

    /**
     * Display the specified archived record.
     */
    public function show(ArchivedRecord $archivedRecord)
    {
        Gate::authorize('view', $archivedRecord);

        $archivedRecord->load(['archivedBy', 'purgedBy']);

        return view('admin.maintenance.archive.show', compact('archivedRecord'));
    }

    /**
     * Restore an archived record.
     */
    public function restore(ArchivedRecord $archivedRecord)
    {
        Gate::authorize('update', $archivedRecord);

        if ($archivedRecord->is_purged) {
            return redirect()
                ->back()
                ->with('error', 'This archived record has been purged and cannot be restored.');
        }

        try {
            $model = $archivedRecord->restore();

            // Log the restoration
            \App\Models\AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'restored',
                'event_description' => "Restored {$archivedRecord->original_table} #{$archivedRecord->original_id} from archive",
                'model_type' => $archivedRecord->original_model,
                'model_id' => $archivedRecord->original_id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('admin.archive.index')
                ->with('success', "Record restored successfully.");

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', "Failed to restore record: {$e->getMessage()}");
        }
    }

    /**
     * Purge an archived record (permanently remove from archive).
     */
    public function purge(ArchivedRecord $archivedRecord)
    {
        Gate::authorize('delete', $archivedRecord);

        $archivedRecord->purge(Auth::user());

        // Log the purge
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'force_deleted',
            'event_description' => "Purged archived {$archivedRecord->original_table} #{$archivedRecord->original_id}",
            'model_type' => ArchivedRecord::class,
            'model_id' => $archivedRecord->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.archive.index')
            ->with('success', "Archived record purged successfully.");
    }

    /**
     * Search for records to archive.
     */
    public function search(Request $request)
    {
        Gate::authorize('create', ArchivedRecord::class);

        $request->validate([
            'table' => ['required', 'in:residents,households,users,barangays,puroks'],
            'search' => ['required', 'string', 'min:2'],
        ]);

        $table = $request->table;
        $search = $request->search;

        $models = [
            'residents' => Resident::class,
            'households' => Household::class,
            'users' => User::class,
            'barangays' => \App\Models\Barangay::class,
            'puroks' => \App\Models\Purok::class,
        ];

        $modelClass = $models[$table];
        $query = $modelClass::query();

        // Different search logic per table
        if ($table === 'residents') {
            $query->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('philsys_card_no', 'LIKE', "%{$search}%");
        } elseif ($table === 'households') {
            $query->where('household_no', 'LIKE', "%{$search}%");
        } elseif ($table === 'users') {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
        } else {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $results = $query->limit(10)->get();

        return response()->json($results);
    }
}