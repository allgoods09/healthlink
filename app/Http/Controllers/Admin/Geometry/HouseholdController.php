<?php

namespace App\Http\Controllers\Admin\Geometry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geometry\HouseholdStoreRequest;
use App\Http\Requests\Admin\Geometry\HouseholdUpdateRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HouseholdController extends Controller
{
    /**
     * Display a listing of households.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Household::class);

        $query = $this->filteredQuery($request);

        $households = $query->with(['purok.barangay'])
            ->withCount('residents')
            ->orderBy('purok_id')
            ->orderBy('household_no')
            ->paginate(15)
            ->withQueryString();

        return view('admin.geometry.households.index', [
            'households' => $households,
            'barangays' => Barangay::active()->orderBy('name')->get(),
            'puroks' => Purok::with('barangay')->active()->orderBy('barangay_id')->orderBy('purok_number')->get(),
        ]);
    }

    /**
     * Export households in CSV, PDF, or XLSX format.
     */
    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', Household::class);

        $households = $this->filteredQuery($request)
            ->with(['purok.barangay'])
            ->withCount('residents')
            ->orderBy('purok_id')
            ->orderBy('household_no')
            ->get();

        $columns = [
            'Household No.' => 'household_no',
            'Barangay' => fn (Household $household) => $household->purok?->barangay?->name,
            'Purok' => fn (Household $household) => $household->purok?->display_name,
            'Address' => 'household_address',
            'Residents' => 'residents_count',
            'Social Aid' => fn (Household $household) => $household->is_social_aid_beneficiary ? 'Yes' : 'No',
            'Status' => fn (Household $household) => $household->is_active ? 'Active' : 'Inactive',
            'Created At' => fn (Household $household) => optional($household->created_at)?->format('Y-m-d H:i:s'),
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => Barangay::find($request->input('barangay_id'))?->name,
            'Purok' => Purok::find($request->input('purok_id'))?->display_name,
            'Status' => $request->input('status'),
            'Social Aid' => $request->input('social_aid'),
        ];

        ExportAudit::log('household registry', $format, [
            'model_type' => Household::class,
            'record_count' => $households->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv('households.csv', $columns, $households),
            'xlsx' => TabularExport::xlsx('households.xlsx', 'Households', $columns, $households),
            'pdf' => TabularExport::pdf('households.pdf', 'Household Registry', $columns, $households, $filters),
            default => abort(404),
        };
    }

    /**
     * Download a single household profile as PDF.
     */
    public function pdf(Household $household): Response
    {
        Gate::authorize('view', $household);

        $household->load(['purok.barangay', 'headResident'])->loadCount('residents');

        $columns = [
            'Household No.' => 'household_no',
            'Address' => 'household_address',
            'Barangay' => fn (Household $item) => $item->purok?->barangay?->name ?: 'N/A',
            'Purok' => fn (Household $item) => $item->purok?->display_name ?: 'N/A',
            'Head of Household' => fn (Household $item) => $item->headResident?->formal_name ?: 'Unassigned',
            'Residents' => 'residents_count',
            'Social Aid' => fn (Household $item) => $item->is_social_aid_beneficiary ? 'Yes' : 'No',
            'Water Source' => fn (Household $item) => $item->water_source ?: 'N/A',
            'Sanitary Toilet' => fn (Household $item) => $item->sanitary_toilet_type ?: 'N/A',
            'Garbage Disposal' => fn (Household $item) => $item->garbage_disposal_method_label ?: 'N/A',
            'Backyard Garden' => fn (Household $item) => $item->has_backyard_garden ? 'Yes' : 'No',
            'Housing Material' => fn (Household $item) => $item->housing_material_type_label ?: 'N/A',
            'Status' => fn (Household $item) => $item->is_active ? 'Active' : 'Inactive',
        ];

        ExportAudit::log('household profile', 'pdf', [
            'model_type' => Household::class,
            'record_count' => 1,
            'record_ids' => [$household->id],
        ]);

        return TabularExport::pdf(
            'household-profile-'.$household->id.'.pdf',
            'Household Profile',
            $columns,
            collect([$household]),
            ['Exported At' => now()->format('Y-m-d H:i:s')]
        );
    }

    /**
     * Show the form for creating a new household.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Household::class);

        $selectedBarangayId = $request->input('barangay_id');
        $availablePuroks = $selectedBarangayId
            ? Purok::where('barangay_id', $selectedBarangayId)->active()->orderBy('purok_number')->get()
            : collect();

        return view('admin.geometry.households.create', [
            'household' => new Household([
                'is_active' => true,
                'is_social_aid_beneficiary' => false,
            ]),
            'barangays' => Barangay::active()->orderBy('name')->get(),
            'selectedBarangayId' => $selectedBarangayId,
            'selectedPurokId' => $request->input('purok_id'),
            'availablePuroks' => $availablePuroks,
        ]);
    }

    /**
     * Store a newly created household.
     */
    public function store(HouseholdStoreRequest $request)
    {
        Gate::authorize('create', Household::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_social_aid_beneficiary'] = $data['is_social_aid_beneficiary'] ?? false;

        $household = Household::create($data);

        AuditLog::logMutation('created', Auth::user(), $household);

        return redirect()
            ->route('admin.households.show', $household)
            ->with('success', "Household #{$household->household_no} created successfully.");
    }

    /**
     * Display the specified household.
     */
    public function show(Household $household): View
    {
        Gate::authorize('view', $household);

        $household->load(['purok.barangay', 'headResident', 'residents.socioEconomicProfile']);

        return view('admin.geometry.households.show', compact('household'));
    }

    /**
     * Show the form for editing the specified household.
     */
    public function edit(Household $household): View
    {
        Gate::authorize('update', $household);

        $household->load(['purok.barangay', 'headResident', 'residents']);
        $availablePuroks = Purok::where('barangay_id', $household->purok->barangay_id)
            ->active()
            ->orderBy('purok_number')
            ->get();

        return view('admin.geometry.households.edit', [
            'household' => $household,
            'barangays' => Barangay::active()->orderBy('name')->get(),
            'selectedBarangayId' => $household->purok->barangay_id,
            'availablePuroks' => $availablePuroks,
        ]);
    }

    /**
     * Update the specified household.
     */
    public function update(HouseholdUpdateRequest $request, Household $household)
    {
        Gate::authorize('update', $household);

        $oldValues = $household->toArray();
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_social_aid_beneficiary'] = $data['is_social_aid_beneficiary'] ?? false;

        $household->update($data);

        AuditLog::logMutation('updated', Auth::user(), $household, $oldValues, $household->fresh()->toArray());

        return redirect()
            ->route('admin.households.show', $household)
            ->with('success', "Household #{$household->household_no} updated successfully.");
    }

    /**
     * Remove the specified household.
     */
    public function destroy(Household $household)
    {
        Gate::authorize('delete', $household);

        $oldValues = $household->toArray();
        $householdNumber = $household->household_no;

        DB::transaction(function () use ($household): void {
            $household->residents()->get()->each->delete();
            $household->delete();
        });

        AuditLog::logMutation('deleted', Auth::user(), $household, $oldValues);

        return redirect()
            ->route('admin.households.index')
            ->with('success', "Household #{$householdNumber} and its residents were archived from active use.");
    }

    /**
     * Restore the specified household.
     */
    public function restore(int $id)
    {
        $household = Household::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $household);

        DB::transaction(function () use ($household): void {
            $household->restore();
            $household->residents()->withTrashed()->restore();
        });

        AuditLog::logMutation('restored', Auth::user(), $household);

        return redirect()
            ->route('admin.households.show', $household)
            ->with('success', "Household #{$household->household_no} restored successfully.");
    }

    /**
     * Toggle the household status.
     */
    public function toggleStatus(Household $household)
    {
        Gate::authorize('toggleStatus', $household);

        $oldStatus = $household->is_active;
        $newStatus = ! $oldStatus;

        $household->update(['is_active' => $newStatus]);

        AuditLog::logMutation('status_toggled', Auth::user(), $household, [
            'is_active' => $oldStatus,
        ], [
            'is_active' => $newStatus,
        ]);

        return back()->with(
            'success',
            "Household #{$household->household_no} has been ".($newStatus ? 'activated' : 'deactivated').'.'
        );
    }

    /**
     * Build the shared listing query.
     */
    private function filteredQuery(Request $request)
    {
        $query = Household::query();

        if ($request->filled('barangay_id')) {
            $query->whereHas('purok', function ($builder) use ($request): void {
                $builder->where('barangay_id', $request->input('barangay_id'));
            });
        }

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->input('purok_id'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('social_aid')) {
            $query->where('is_social_aid_beneficiary', $request->input('social_aid') === 'yes');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($builder) use ($search): void {
                $builder->where('household_no', 'like', "%{$search}%")
                    ->orWhere('household_address', 'like', "%{$search}%");
            });
        }

        if ($request->input('lifecycle') === 'all') {
            $query->withTrashed();
        } elseif ($request->input('lifecycle') === 'deleted') {
            $query->onlyTrashed();
        }

        return $query;
    }
}
