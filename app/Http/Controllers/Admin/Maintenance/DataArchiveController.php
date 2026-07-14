<?php

namespace App\Http\Controllers\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\ArchivedRecord;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'barangays' => Barangay::class,
            'puroks' => Purok::class,
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

        $table = $request->string('table')->toString();
        $recordId = $request->integer('record_id');

        // Map table to model
        $models = [
            'residents' => Resident::class,
            'households' => Household::class,
            'users' => User::class,
            'barangays' => Barangay::class,
            'puroks' => Purok::class,
        ];

        $modelClass = $models[$table];
        $model = $modelClass::find($recordId);

        if (!$model) {
            return redirect()
                ->back()
                ->with('error', "Record not found in {$table}.");
        }

        // Check if already archived
        $exists = ArchivedRecord::notPurged()
                                ->where('original_table', $table)
                                ->where('original_id', $recordId)
                                ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->with('error', 'This record has already been archived.');
        }

        try {
            DB::transaction(function () use ($model, $table, $request): void {
                $this->ensureArchivable($model, $table);

                ArchivedRecord::archive($model, Auth::user(), $request->reason);

                if ($model instanceof Household) {
                    $model->residents()->delete();
                }

                $oldValues = $model->toArray();
                $model->delete();

                \App\Models\AuditLog::logMutation('deleted', Auth::user(), $model, $oldValues);
            });
        } catch (\Throwable $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

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
            DB::transaction(function () use ($archivedRecord): void {
                $archivedRecord->restore();
            });

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

        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', "Failed to restore record: {$e->getMessage()}");
        }
    }

    /**
     * Purge an archived record (permanently remove from archive).
     */
    public function purge(Request $request, ArchivedRecord $archivedRecord)
    {
        Gate::authorize('delete', $archivedRecord);

        $validated = $request->validate([
            'confirmation_phrase' => ['required', 'in:PURGE'],
            'action_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($archivedRecord): void {
                $archivedRecord->purge(Auth::user());
            });
        } catch (\Throwable $exception) {
            return redirect()
                ->back()
                ->with('error', $exception->getMessage());
        }

        // Log the purge
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'force_deleted',
            'event_description' => "Purged archived {$archivedRecord->original_table} #{$archivedRecord->original_id}",
            'model_type' => ArchivedRecord::class,
            'model_id' => $archivedRecord->id,
            'metadata' => [
                'confirmation_phrase' => $validated['confirmation_phrase'],
                'action_reason' => $validated['action_reason'],
                'original_table' => $archivedRecord->original_table,
                'original_id' => $archivedRecord->original_id,
            ],
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
            'barangays' => Barangay::class,
            'puroks' => Purok::class,
        ];

        $modelClass = $models[$table];
        $query = $modelClass::query();
        $archivedIds = ArchivedRecord::where('original_table', $table)->pluck('original_id');

        if ($archivedIds->isNotEmpty()) {
            $query->whereNotIn('id', $archivedIds);
        }

        // Different search logic per table
        if ($table === 'residents') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('philsys_card_no', 'LIKE', "%{$search}%");
            });
        } elseif ($table === 'households') {
            $query->where('household_no', 'LIKE', "%{$search}%");
        } elseif ($table === 'users') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        } elseif ($table === 'barangays') {
            $query->where('name', 'LIKE', "%{$search}%");
        } elseif ($table === 'puroks') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('purok_name', 'LIKE', "%{$search}%")
                    ->orWhere('purok_number', 'LIKE', "%{$search}%");
            });
        }

        if ($table === 'households') {
            $query->with('purok.barangay');
        } elseif ($table === 'residents') {
            $query->with('household.purok.barangay');
        } elseif ($table === 'puroks') {
            $query->with('barangay');
        }

        $results = $query->limit(10)->get()->map(function ($record) use ($table) {
            return [
                'id' => $record->id,
                'label' => match ($table) {
                    'residents' => $record->full_name,
                    'households' => 'Household #' . $record->household_no,
                    'users' => $record->name,
                    'barangays' => $record->name,
                    'puroks' => $record->display_name,
                },
                'details' => match ($table) {
                    'residents' => $record->household
                        ? "{$record->household->purok->barangay->name} / {$record->household->purok->display_name} / Household #{$record->household->household_no}"
                        : ($record->philsys_card_no ?: 'Resident record'),
                    'households' => $record->purok
                        ? "{$record->purok->barangay->name} / {$record->purok->display_name}"
                        : ($record->household_address ?: 'Household record'),
                    'users' => $record->email,
                    'barangays' => $record->full_address,
                    'puroks' => $record->barangay ? $record->barangay->name : 'Purok record',
                },
            ];
        });

        return response()->json($results->values());
    }

    /**
     * Ensure archiving a record will not leave invalid dependent data behind.
     */
    private function ensureArchivable(object $model, string $table): void
    {
        if ($table === 'barangays') {
            if ($model->puroks()->count() > 0) {
                throw new \RuntimeException('Barangays with existing puroks must be cleaned up before archiving.');
            }

            if ($model->assignedUsers()->count() > 0) {
                throw new \RuntimeException('Barangays with assigned users cannot be archived.');
            }
        }

        if ($table === 'puroks') {
            if ($model->households()->count() > 0) {
                throw new \RuntimeException('Puroks with existing households must be cleaned up before archiving.');
            }

            if ($model->assignedUsers()->count() > 0) {
                throw new \RuntimeException('Puroks with assigned BHWs cannot be archived.');
            }
        }

        if ($table === 'households') {
            $residentIds = Resident::withTrashed()
                ->where('household_id', $model->id)
                ->pluck('id');

            if ($residentIds->isNotEmpty() && ArchivedRecord::query()
                ->notPurged()
                ->where('original_table', 'residents')
                ->whereIn('original_id', $residentIds)
                ->exists()) {
                throw new \RuntimeException('Households with individually archived residents must be resolved before archiving the household.');
            }
        }
    }
}
