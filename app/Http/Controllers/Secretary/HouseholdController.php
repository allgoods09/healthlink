<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Admin\Geometry\HouseholdStoreRequest;
use App\Http\Requests\Admin\Geometry\HouseholdUpdateRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HouseholdController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Household::class);

        $households = $this->filteredQuery($request)
            ->with(['purok.barangay', 'headResident'])
            ->withCount('residents')
            ->orderBy('purok_id')
            ->orderBy('household_no')
            ->paginate(15)
            ->withQueryString();

        return view('admin.geometry.households.index', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Households - HealthLink Secretary',
            'pageHeader' => 'Household Clustering',
            'canDelete' => false,
            'canRestore' => false,
            'households' => $households,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'puroks' => $this->secretaryPuroksQuery()
                ->with('barangay')
                ->active()
                ->orderBy('purok_number')
                ->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', Household::class);

        $households = $this->filteredQuery($request)
            ->with(['purok.barangay', 'headResident'])
            ->withCount('residents')
            ->orderBy('purok_id')
            ->orderBy('household_no')
            ->get();

        $columns = [
            'Household No.' => 'household_no',
            'Barangay' => fn (Household $household) => $household->purok?->barangay?->name,
            'Purok' => fn (Household $household) => $household->purok?->display_name,
            'Address' => 'household_address',
            'Head of Household' => fn (Household $household) => $household->headResident?->formal_name ?: 'Unassigned',
            'Residents' => 'residents_count',
            'Social Aid' => fn (Household $household) => $household->is_social_aid_beneficiary ? 'Yes' : 'No',
            'Status' => fn (Household $household) => $household->is_active ? 'Active' : 'Inactive',
            'Created At' => fn (Household $household) => optional($household->created_at)?->format('Y-m-d H:i:s'),
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => $this->secretaryUser()->assignedBarangay?->name,
            'Purok' => $this->secretaryPuroksQuery()->find($request->input('purok_id'))?->display_name,
            'Status' => $request->input('status'),
            'Social Aid' => $request->input('social_aid'),
        ];

        ExportAudit::log('secretary household registry', $format, [
            'model_type' => Household::class,
            'record_count' => $households->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_households_{$timestamp}.csv", $columns, $households),
            'xlsx' => TabularExport::xlsx("secretary_households_{$timestamp}.xlsx", 'Secretary Households', $columns, $households),
            'pdf' => TabularExport::pdf("secretary_households_{$timestamp}.pdf", 'Barangay Household Registry', $columns, $households, $filters),
            default => abort(404),
        };
    }

    public function pdf(Household $household): Response
    {
        Gate::authorize('view', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

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

        ExportAudit::log('secretary household profile', 'pdf', [
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

    public function create(Request $request): View
    {
        Gate::authorize('create', Household::class);

        return view('admin.geometry.households.create', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Create Household - HealthLink Secretary',
            'pageHeader' => 'Create Household',
            'household' => new Household([
                'is_active' => true,
                'is_social_aid_beneficiary' => false,
            ]),
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'selectedBarangayId' => $request->input('barangay_id', $this->assignedBarangayId()),
            'selectedPurokId' => $request->input('purok_id'),
        ]);
    }

    public function store(HouseholdStoreRequest $request): RedirectResponse
    {
        Gate::authorize('create', Household::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_social_aid_beneficiary'] = $data['is_social_aid_beneficiary'] ?? false;

        $household = Household::create($data);

        AuditLog::logMutation('created', Auth::user(), $household);

        return redirect()
            ->route('secretary.households.show', $household)
            ->with('success', "Household #{$household->household_no} created successfully.");
    }

    public function show(Household $household): View
    {
        Gate::authorize('view', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

        $household->load(['purok.barangay', 'headResident', 'residents.socioEconomicProfile']);

        return view('admin.geometry.households.show', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Household Details - HealthLink Secretary',
            'pageHeader' => 'Household Details',
            'household' => $household,
        ]);
    }

    public function edit(Household $household): View
    {
        Gate::authorize('update', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

        $household->load(['purok.barangay', 'headResident', 'residents']);

        return view('admin.geometry.households.edit', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Edit Household - HealthLink Secretary',
            'pageHeader' => 'Edit Household',
            'household' => $household,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'selectedBarangayId' => $household->purok->barangay_id,
        ]);
    }

    public function update(HouseholdUpdateRequest $request, Household $household): RedirectResponse
    {
        Gate::authorize('update', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

        $oldValues = $household->toArray();
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_social_aid_beneficiary'] = $data['is_social_aid_beneficiary'] ?? false;

        $household->update($data);

        AuditLog::logMutation('updated', Auth::user(), $household, $oldValues, $household->fresh()->toArray());

        return redirect()
            ->route('secretary.households.show', $household)
            ->with('success', "Household #{$household->household_no} updated successfully.");
    }

    public function toggleStatus(Household $household): RedirectResponse
    {
        Gate::authorize('toggleStatus', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

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
            "Household #{$household->household_no} has been ".($newStatus ? 'activated' : 'marked inactive').'.'
        );
    }

    private function filteredQuery(Request $request)
    {
        $query = $this->secretaryHouseholdsQuery();

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
