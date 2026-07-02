<?php

namespace App\Http\Controllers\Admin\Geometry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geometry\PurokStoreRequest;
use App\Http\Requests\Admin\Geometry\PurokUpdateRequest;
use App\Models\Barangay;
use App\Models\Purok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class PurokGridController extends Controller
{
    /**
     * Display a listing of puroks.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Purok::class);

        $query = Purok::query()->with('barangay');

        // Filter by barangay
        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->barangay_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('purok_number', $search)
                  ->orWhere('purok_name', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $puroks = $query->withCount(['households', 'assignedUsers'])
                        ->orderBy('barangay_id')
                        ->orderBy('purok_number')
                        ->paginate(15)
                        ->withQueryString();

        $barangays = Barangay::active()->get();

        return view('admin.geometry.puroks.index', compact('puroks', 'barangays'));
    }

    /**
     * Show the form for creating a new purok.
     */
    public function create()
    {
        Gate::authorize('create', Purok::class);

        $barangays = Barangay::active()->get();

        return view('admin.geometry.puroks.create', compact('barangays'));
    }

    /**
     * Store a newly created purok.
     */
    public function store(PurokStoreRequest $request)
    {
        Gate::authorize('create', Purok::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $purok = Purok::create($data);

        // Log the creation
        \App\Models\AuditLog::logMutation('created', Auth::user(), $purok);

        return redirect()
            ->route('admin.puroks.index')
            ->with('success', "Purok {$purok->display_name} created successfully.");
    }

    /**
     * Display the specified purok.
     */
    public function show(Purok $purok)
    {
        Gate::authorize('view', $purok);

        $purok->load(['barangay', 'households', 'assignedUsers']);
        $totalHouseholds = $purok->total_households;
        $totalResidents = $purok->total_residents;

        return view('admin.geometry.puroks.show', compact('purok', 'totalHouseholds', 'totalResidents'));
    }

    /**
     * Show the form for editing the specified purok.
     */
    public function edit(Purok $purok)
    {
        Gate::authorize('update', $purok);

        $barangays = Barangay::active()->get();

        return view('admin.geometry.puroks.edit', compact('purok', 'barangays'));
    }

    /**
     * Update the specified purok.
     */
    public function update(PurokUpdateRequest $request, Purok $purok)
    {
        Gate::authorize('update', $purok);

        $oldValues = $purok->toArray();
        $data = $request->validated();

        $purok->update($data);

        // Log the update
        \App\Models\AuditLog::logMutation('updated', Auth::user(), $purok, $oldValues, $purok->toArray());

        return redirect()
            ->route('admin.puroks.index')
            ->with('success', "Purok {$purok->display_name} updated successfully.");
    }

    /**
     * Remove the specified purok.
     */
    public function destroy(Purok $purok)
    {
        Gate::authorize('delete', $purok);

        // Check if purok has households
        if ($purok->households()->count() > 0) {
            return redirect()
                ->back()
                ->with('error', "Cannot delete {$purok->display_name} because it has existing households.");
        }

        // Check if purok has assigned users
        if ($purok->assignedUsers()->count() > 0) {
            return redirect()
                ->back()
                ->with('error', "Cannot delete {$purok->display_name} because it has assigned users.");
        }

        $oldValues = $purok->toArray();
        $purokName = $purok->display_name;

        $purok->delete();

        // Log the deletion
        \App\Models\AuditLog::logMutation('deleted', Auth::user(), $purok, $oldValues);

        return redirect()
            ->route('admin.puroks.index')
            ->with('success', "Purok {$purokName} deleted successfully.");
    }

    /**
     * Toggle purok active status.
     */
    public function toggleStatus(Purok $purok)
    {
        Gate::authorize('toggleStatus', $purok);

        $oldStatus = $purok->is_active;
        $newStatus = !$oldStatus;

        $purok->update(['is_active' => $newStatus]);

        // Log the status change
        \App\Models\AuditLog::logMutation('status_toggled', Auth::user(), $purok, [
            'is_active' => $oldStatus
        ], [
            'is_active' => $newStatus
        ]);

        $status = $newStatus ? 'activated' : 'deactivated';

        return redirect()
            ->back()
            ->with('success', "Purok {$purok->display_name} has been {$status}.");
    }

    /**
     * Restore the specified purok.
     */
    public function restore($id)
    {
        $purok = Purok::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $purok);

        $purok->restore();

        \App\Models\AuditLog::logMutation('restored', Auth::user(), $purok);

        return redirect()
            ->route('admin.puroks.index')
            ->with('success', "Purok {$purok->display_name} restored successfully.");
    }

    /**
     * Get puroks for a specific barangay (AJAX).
     */
    public function getByBarangay(Request $request)
    {
        $barangayId = $request->barangay_id;

        if (!$barangayId) {
            return response()->json([]);
        }

        $puroks = Purok::where('barangay_id', $barangayId)
                       ->active()
                       ->orderBy('purok_number')
                       ->get(['id', 'purok_number', 'purok_name']);

        return response()->json($puroks);
    }
}