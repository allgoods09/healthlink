<?php

namespace App\Http\Controllers\Admin\Geometry;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NormalizesResidentLifecycle;
use App\Http\Requests\Admin\Geometry\ResidentStoreRequest;
use App\Http\Requests\Admin\Geometry\ResidentUpdateRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentSocioEconomicProfile;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResidentController extends Controller
{
    use NormalizesResidentLifecycle;

    /**
     * Display a listing of residents.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Resident::class);

        $query = $this->filteredQuery($request);

        $residents = $query->with(['household.purok.barangay', 'socioEconomicProfile'])
            ->latest('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.geometry.residents.index', [
            'residents' => $residents,
            'barangays' => Barangay::active()->orderBy('name')->get(),
            'puroks' => Purok::with('barangay')->active()->orderBy('barangay_id')->orderBy('purok_number')->get(),
            'households' => Household::with('purok.barangay')->active()->orderBy('household_no')->get(),
        ]);
    }

    /**
     * Export residents in CSV, PDF, or XLSX format.
     */
    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', Resident::class);

        $residents = $this->filteredQuery($request)
            ->with(['household.purok.barangay', 'socioEconomicProfile'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $columns = [
            'PhilSys ID' => fn (Resident $resident) => $resident->philsys_card_no ?: 'N/A',
            'Resident' => fn (Resident $resident) => $resident->formal_name,
            'Sex' => 'sex',
            'Birth Date' => fn (Resident $resident) => optional($resident->birth_date)?->format('Y-m-d'),
            'Age' => 'age',
            'Barangay' => fn (Resident $resident) => $resident->household?->purok?->barangay?->name,
            'Purok' => fn (Resident $resident) => $resident->household?->purok?->display_name,
            'Household' => fn (Resident $resident) => $resident->household?->household_no,
            'Relationship' => 'relationship_to_head',
            'Education' => fn (Resident $resident) => $resident->socioEconomicProfile?->highest_education_level ?: 'N/A',
            'Occupation' => fn (Resident $resident) => $resident->socioEconomicProfile?->occupation ?: 'N/A',
            'Availability' => fn (Resident $resident) => $resident->is_active ? 'Active' : 'Inactive',
            'Civil Status' => fn (Resident $resident) => $resident->resident_status_label,
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => Barangay::find($request->input('barangay_id'))?->name,
            'Purok' => Purok::find($request->input('purok_id'))?->display_name,
            'Household' => Household::find($request->input('household_id'))?->household_no,
            'Sex' => $request->input('sex'),
            'Status' => $request->input('status'),
            'Civil Status' => match ($request->input('resident_status')) {
                Resident::STATUS_ACTIVE => 'Active Resident',
                Resident::STATUS_DECEASED => 'Deceased',
                Resident::STATUS_RELOCATED => 'Relocated',
                default => null,
            },
        ];

        ExportAudit::log('resident registry', $format, [
            'model_type' => Resident::class,
            'record_count' => $residents->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv('residents.csv', $columns, $residents),
            'xlsx' => TabularExport::xlsx('residents.xlsx', 'Residents', $columns, $residents),
            'pdf' => TabularExport::pdf('residents.pdf', 'Resident Registry', $columns, $residents, $filters),
            default => abort(404),
        };
    }

    /**
     * Download a single resident profile as PDF.
     */
    public function pdf(Resident $resident): Response
    {
        Gate::authorize('view', $resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);

        ExportAudit::log('resident profile', 'pdf', [
            'model_type' => Resident::class,
            'record_count' => 1,
            'record_ids' => [$resident->id],
        ]);

        return Pdf::loadView('documents.residents.rbi-form', [
            'resident' => $resident,
            'attestedByName' => null,
            'attestedByRole' => 'Barangay Secretary',
            'accomplishingPartyName' => Auth::user()?->name,
            'documentDate' => now(),
            'showBrowserPrintScript' => false,
        ])->setPaper('a4')->download('resident-rbi-form-'.$resident->id.'.pdf');
    }

    public function printView(Resident $resident): View
    {
        Gate::authorize('view', $resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);

        return view('documents.residents.rbi-form', [
            'resident' => $resident,
            'attestedByName' => null,
            'attestedByRole' => 'Barangay Secretary',
            'accomplishingPartyName' => Auth::user()?->name,
            'documentDate' => now(),
            'showBrowserPrintScript' => true,
        ]);
    }

    /**
     * Show the form for creating a new resident.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Resident::class);

        $resident = new Resident([
            'citizenship' => 'Filipino',
            'is_active' => true,
        ]);

        $resident->setRelation('socioEconomicProfile', new ResidentSocioEconomicProfile([
            'employment_status' => 'N/A',
            'highest_education_level' => 'None',
            'education_status' => 'N/A',
        ]));

        return view('admin.geometry.residents.create', [
            'resident' => $resident,
            'barangays' => Barangay::active()->orderBy('name')->get(),
            'selectedBarangayId' => $request->input('barangay_id'),
            'selectedPurokId' => $request->input('purok_id'),
            'selectedHouseholdId' => $request->input('household_id'),
        ]);
    }

    /**
     * Store a newly created resident.
     */
    public function store(ResidentStoreRequest $request)
    {
        Gate::authorize('create', Resident::class);

        $data = $request->validated();
        $data = $this->normalizeResidentLifecycle($data);
        $residentData = Arr::except($data, $this->socioEconomicFields());

        $resident = Resident::create($residentData);
        $this->syncSocioEconomicProfile($resident, $data);

        AuditLog::logMutation('created', Auth::user(), $resident);

        return redirect()
            ->route('admin.residents.show', $resident)
            ->with('success', "Resident {$resident->full_name} created successfully.");
    }

    /**
     * Display the specified resident.
     */
    public function show(Resident $resident): View
    {
        Gate::authorize('view', $resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);

        return view('admin.geometry.residents.show', compact('resident'));
    }

    /**
     * Show the form for editing the specified resident.
     */
    public function edit(Resident $resident): View
    {
        Gate::authorize('update', $resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);

        return view('admin.geometry.residents.edit', [
            'resident' => $resident,
            'barangays' => Barangay::active()->orderBy('name')->get(),
            'selectedBarangayId' => $resident->household->purok->barangay_id,
            'selectedPurokId' => $resident->household->purok_id,
            'selectedHouseholdId' => $resident->household_id,
        ]);
    }

    /**
     * Update the specified resident.
     */
    public function update(ResidentUpdateRequest $request, Resident $resident)
    {
        Gate::authorize('update', $resident);

        $data = $request->validated();
        $data = $this->normalizeResidentLifecycle($data);
        $oldValues = $resident->load('socioEconomicProfile')->toArray();
        $residentData = Arr::except($data, $this->socioEconomicFields());

        $resident->update($residentData);
        $this->syncSocioEconomicProfile($resident, $data);

        AuditLog::logMutation('updated', Auth::user(), $resident, $oldValues, $resident->fresh()->load('socioEconomicProfile')->toArray());

        return redirect()
            ->route('admin.residents.show', $resident)
            ->with('success', "Resident {$resident->full_name} updated successfully.");
    }

    /**
     * Remove the specified resident.
     */
    public function destroy(Resident $resident)
    {
        Gate::authorize('delete', $resident);

        $oldValues = $resident->toArray();
        $residentName = $resident->full_name;
        $resident->delete();

        AuditLog::logMutation('deleted', Auth::user(), $resident, $oldValues);

        return redirect()
            ->route('admin.residents.index')
            ->with('success', "Resident {$residentName} deleted successfully.");
    }

    /**
     * Restore the specified resident.
     */
    public function restore(int $id)
    {
        $resident = Resident::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $resident);

        $resident->restore();

        AuditLog::logMutation('restored', Auth::user(), $resident);

        return redirect()
            ->route('admin.residents.show', $resident)
            ->with('success', "Resident {$resident->full_name} restored successfully.");
    }

    /**
     * Toggle the resident's active status.
     */
    public function toggleStatus(Resident $resident)
    {
        Gate::authorize('toggleStatus', $resident);

        $oldStatus = $resident->is_active;
        $newStatus = ! $oldStatus;
        $resident->update(['is_active' => $newStatus]);

        AuditLog::logMutation('status_toggled', Auth::user(), $resident, [
            'is_active' => $oldStatus,
        ], [
            'is_active' => $newStatus,
        ]);

        return back()->with(
            'success',
            "Resident {$resident->full_name} has been ".($newStatus ? 'activated' : 'deactivated').'.'
        );
    }

    /**
     * Get households for a selected purok.
     */
    public function householdsByPurok(Request $request)
    {
        $request->validate([
            'purok_id' => ['required', 'exists:puroks,id'],
        ]);

        return response()->json(
            Household::where('purok_id', $request->input('purok_id'))
                ->active()
                ->orderBy('household_no')
                ->get(['id', 'household_no', 'household_address'])
        );
    }

    /**
     * Build the resident listing query.
     */
    private function filteredQuery(Request $request)
    {
        $query = Resident::query();

        if ($request->filled('barangay_id')) {
            $query->whereHas('household.purok', function ($builder) use ($request): void {
                $builder->where('barangay_id', $request->input('barangay_id'));
            });
        }

        if ($request->filled('purok_id')) {
            $query->whereHas('household', function ($builder) use ($request): void {
                $builder->where('purok_id', $request->input('purok_id'));
            });
        }

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->input('household_id'));
        }

        if ($request->filled('sex')) {
            $query->where('sex', $request->input('sex'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('resident_status')) {
            $query->where('resident_status', $request->input('resident_status'));
        }

        if ($request->filled('age_group')) {
            match ($request->input('age_group')) {
                'minor' => $query->whereDate('birth_date', '>', now()->subYears(18)),
                'adult' => $query
                    ->whereDate('birth_date', '<=', now()->subYears(18))
                    ->whereDate('birth_date', '>', now()->subYears(60)),
                'senior' => $query->whereDate('birth_date', '<=', now()->subYears(60)),
                default => null,
            };
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($builder) use ($search): void {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('philsys_card_no', 'like', "%{$search}%");
            });
        }

        if ($request->input('lifecycle') === 'all') {
            $query->withTrashed();
        } elseif ($request->input('lifecycle') === 'deleted') {
            $query->onlyTrashed();
        }

        return $query;
    }

    /**
     * Persist the resident socio-economic profile.
     */
    private function syncSocioEconomicProfile(Resident $resident, array $data): void
    {
        $payload = Arr::only($data, $this->socioEconomicFields());

        $payload = array_merge([
            'employment_status' => 'N/A',
            'highest_education_level' => 'None',
            'education_status' => 'N/A',
            'is_pwd' => false,
            'is_ofw' => false,
            'is_solo_parent' => false,
            'is_osy' => false,
            'is_osc' => false,
            'is_ip' => false,
        ], $payload);

        ResidentSocioEconomicProfile::updateOrCreate(
            ['resident_id' => $resident->id],
            $payload
        );
    }

    /**
     * Get the list of socio-economic fields handled outside the resident table.
     */
    private function socioEconomicFields(): array
    {
        return [
            'occupation',
            'employment_status',
            'highest_education_level',
            'education_status',
            'is_pwd',
            'disability_type',
            'is_ofw',
            'is_solo_parent',
            'is_osy',
            'is_osc',
            'is_ip',
            'ethnicity',
        ];
    }
}
