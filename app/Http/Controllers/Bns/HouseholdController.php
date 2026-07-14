<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geometry\HouseholdStoreRequest;
use App\Http\Requests\Admin\Geometry\HouseholdUpdateRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HouseholdController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Household::class);

        $households = $this->filteredQuery($request)
            ->with(['purok.barangay'])
            ->withCount('residents')
            ->orderBy('purok_id')
            ->orderBy('household_no')
            ->paginate(15)
            ->withQueryString();

        return view('admin.geometry.households.index', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'bns',
            'pageTitle' => 'Households - HealthLink BNS',
            'pageHeader' => 'Barangay Households',
            'canDelete' => false,
            'canRestore' => false,
            'households' => $households,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'puroks' => $this->bnsPuroksQuery()
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
            'Barangay' => $this->bnsUser()->assignedBarangay?->name,
            'Purok' => $this->bnsPuroksQuery()->find($request->input('purok_id'))?->display_name,
            'Status' => $request->input('status'),
            'Social Aid' => $request->input('social_aid'),
        ];

        ExportAudit::log('bns household registry', $format, [
            'model_type' => Household::class,
            'record_count' => $households->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("bns_households_{$timestamp}.csv", $columns, $households),
            'xlsx' => TabularExport::xlsx("bns_households_{$timestamp}.xlsx", 'BNS Households', $columns, $households),
            'pdf' => TabularExport::pdf("bns_households_{$timestamp}.pdf", 'Barangay Household Registry', $columns, $households, $filters),
            default => abort(404),
        };
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Household::class);

        return view('admin.geometry.households.create', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'bns',
            'pageTitle' => 'Create Household - HealthLink BNS',
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

    public function store(HouseholdStoreRequest $request)
    {
        Gate::authorize('create', Household::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_social_aid_beneficiary'] = $data['is_social_aid_beneficiary'] ?? false;

        $household = Household::create($data);

        AuditLog::logMutation('created', Auth::user(), $household);

        return redirect()
            ->route('bns.households.show', $household)
            ->with('success', "Household #{$household->household_no} created successfully.");
    }

    public function show(Household $household): View
    {
        Gate::authorize('view', $household);

        $household->load(['purok.barangay', 'headResident', 'residents.socioEconomicProfile']);

        return view('admin.geometry.households.show', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'bns',
            'pageTitle' => 'Household Details - HealthLink BNS',
            'pageHeader' => 'Household Details',
            'household' => $household,
        ]);
    }

    public function edit(Household $household): View
    {
        Gate::authorize('update', $household);

        $household->load(['purok.barangay', 'headResident', 'residents']);

        return view('admin.geometry.households.edit', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'bns',
            'pageTitle' => 'Edit Household - HealthLink BNS',
            'pageHeader' => 'Edit Household',
            'household' => $household,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'selectedBarangayId' => $household->purok->barangay_id,
        ]);
    }

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
            ->route('bns.households.show', $household)
            ->with('success', "Household #{$household->household_no} updated successfully.");
    }

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
            "Household #{$household->household_no} has been ".($newStatus ? 'activated' : 'marked inactive').'.'
        );
    }

    private function filteredQuery(Request $request)
    {
        $query = $this->bnsHouseholdsQuery();

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
