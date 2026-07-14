<?php

namespace App\Http\Controllers\Admin\Geometry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geometry\BarangayStoreRequest;
use App\Http\Requests\Admin\Geometry\BarangayUpdateRequest;
use App\Models\Barangay;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class BarangayRegistryController extends Controller
{
    /**
     * Display a listing of barangays.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Barangay::class);

        $barangays = $this->filteredQuery($request)
                           ->withCount(['puroks', 'assignedUsers'])
                           ->latest()
                           ->paginate(15)
                           ->withQueryString();

        return view('admin.geometry.barangays.index', compact('barangays'));
    }

    /**
     * Export the barangay registry.
     */
    public function export(Request $request, string $format)
    {
        Gate::authorize('viewAny', Barangay::class);

        $barangays = $this->filteredQuery($request)
            ->withCount(['puroks', 'assignedUsers'])
            ->latest()
            ->get();

        $columns = [
            'Barangay' => 'name',
            'PSGC Code' => 'psgc_code',
            'Municipality' => 'municipality',
            'Province' => 'province',
            'Region' => 'region',
            'Puroks' => 'puroks_count',
            'Assigned Users' => 'assigned_users_count',
            'Status' => fn (Barangay $barangay) => $barangay->is_active ? 'Active' : 'Inactive',
            'Deleted At' => fn (Barangay $barangay) => $barangay->deleted_at?->format('Y-m-d H:i:s') ?? 'N/A',
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'Status' => $request->filled('status') ? ucfirst($request->status) : null,
            'Lifecycle' => $request->filled('lifecycle') ? ucfirst($request->lifecycle) : 'Current',
        ];

        $timestamp = now()->format('Y-m-d_His');

        ExportAudit::log('barangay registry', $format, [
            'model_type' => Barangay::class,
            'record_count' => $barangays->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("barangays_{$timestamp}.csv", $columns, $barangays),
            'xlsx' => TabularExport::xlsx("barangays_{$timestamp}.xlsx", 'Barangays', $columns, $barangays),
            'pdf' => TabularExport::pdf("barangays_{$timestamp}.pdf", 'Barangay Registry', $columns, $barangays, $filters),
            default => abort(404),
        };
    }

    /**
     * Show the form for creating a new barangay.
     */
    public function create()
    {
        Gate::authorize('create', Barangay::class);

        return view('admin.geometry.barangays.create');
    }

    /**
     * Store a newly created barangay.
     */
    public function store(BarangayStoreRequest $request)
    {
        Gate::authorize('create', Barangay::class);

        $data = $request->validated();

        // Set defaults if not provided
        $data['municipality'] = $data['municipality'] ?? 'Tubigon';
        $data['province'] = $data['province'] ?? 'Bohol';
        $data['region'] = $data['region'] ?? 'VII';
        $data['is_active'] = $data['is_active'] ?? true;

        $barangay = Barangay::create($data);

        // Log the creation
        \App\Models\AuditLog::logMutation('created', Auth::user(), $barangay);

        return redirect()
            ->route('admin.barangays.index')
            ->with('success', "Barangay {$barangay->name} created successfully.");
    }

    /**
     * Display the specified barangay.
     */
    public function show(Barangay $barangay)
    {
        Gate::authorize('view', $barangay);

        $barangay->load(['puroks', 'assignedUsers']);
        $totalHouseholds = $barangay->total_households;
        $totalResidents = $barangay->total_residents;

        return view('admin.geometry.barangays.show', compact('barangay', 'totalHouseholds', 'totalResidents'));
    }

    /**
     * Show the form for editing the specified barangay.
     */
    public function edit(Barangay $barangay)
    {
        Gate::authorize('update', $barangay);

        return view('admin.geometry.barangays.edit', compact('barangay'));
    }

    /**
     * Update the specified barangay.
     */
    public function update(BarangayUpdateRequest $request, Barangay $barangay)
    {
        Gate::authorize('update', $barangay);

        $oldValues = $barangay->toArray();
        $data = $request->validated();

        $barangay->update($data);

        // Log the update
        \App\Models\AuditLog::logMutation('updated', Auth::user(), $barangay, $oldValues, $barangay->toArray());

        return redirect()
            ->route('admin.barangays.index')
            ->with('success', "Barangay {$barangay->name} updated successfully.");
    }

    /**
     * Remove the specified barangay.
     */
    public function destroy(Barangay $barangay)
    {
        Gate::authorize('delete', $barangay);

        // Check if barangay has puroks
        if ($barangay->puroks()->count() > 0) {
            return redirect()
                ->back()
                ->with('error', "Cannot delete {$barangay->name} because it has existing puroks.");
        }

        // Check if barangay has assigned users
        if ($barangay->assignedUsers()->count() > 0) {
            return redirect()
                ->back()
                ->with('error', "Cannot delete {$barangay->name} because it has assigned users.");
        }

        $oldValues = $barangay->toArray();
        $barangayName = $barangay->name;

        $barangay->delete();

        // Log the deletion
        \App\Models\AuditLog::logMutation('deleted', Auth::user(), $barangay, $oldValues);

        return redirect()
            ->route('admin.barangays.index')
            ->with('success', "Barangay {$barangayName} deleted successfully.");
    }

    /**
     * Toggle barangay active status.
     */
    public function toggleStatus(Barangay $barangay)
    {
        Gate::authorize('toggleStatus', $barangay);

        $oldStatus = $barangay->is_active;
        $newStatus = !$oldStatus;

        $barangay->update(['is_active' => $newStatus]);

        // Log the status change
        \App\Models\AuditLog::logMutation('status_toggled', Auth::user(), $barangay, [
            'is_active' => $oldStatus
        ], [
            'is_active' => $newStatus
        ]);

        $status = $newStatus ? 'activated' : 'deactivated';

        return redirect()
            ->back()
            ->with('success', "Barangay {$barangay->name} has been {$status}.");
    }

    /**
     * Restore the specified barangay.
     */
    public function restore($id)
    {
        $barangay = Barangay::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $barangay);

        $barangay->restore();

        \App\Models\AuditLog::logMutation('restored', Auth::user(), $barangay);

        return redirect()
            ->route('admin.barangays.index')
            ->with('success', "Barangay {$barangay->name} restored successfully.");
    }

    /**
     * Build the filtered query used for listing and export.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = Barangay::query();

        if ($request->input('lifecycle') === 'all') {
            $query->withTrashed();
        } elseif ($request->input('lifecycle') === 'deleted') {
            $query->onlyTrashed();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('psgc_code', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        return $query;
    }
}
