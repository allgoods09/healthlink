<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Admin\Geometry\HouseholdStoreRequest;
use App\Http\Requests\Admin\Geometry\HouseholdUpdateRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BarangayOfficial;
use App\Models\Household;
use App\Support\BarangayOfficialsRegistry;
use App\Support\CurrentRbiEligibility;
use App\Support\ExportAudit;
use App\Support\ExportDownload;
use App\Support\HouseholdHeadReview;
use App\Support\RbiTemplatePdfGenerator;
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

        $households = $this->listingQuery($request)
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

        $households = $this->listingQuery($request)->get();

        $columns = [
            'Household No.' => 'household_no',
            'Barangay' => fn (Household $household) => $household->purok?->barangay?->name,
            'Purok' => fn (Household $household) => $household->purok?->display_name,
            'Address' => 'household_address',
            'Head of Household' => fn (Household $household) => $household->currentHeadResident()?->formal_name ?: 'Unassigned',
            'Residents' => 'current_members_count',
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
            'Lifecycle' => $request->input('lifecycle') ?: 'Current',
        ];

        return ExportDownload::make($format, 'Barangay Household Registry', 'Civil Registry', 'secretary_households', $columns, $households, $filters, $this->secretaryUser()->assignedBarangay?->name, Household::class, array_intersect_key($columns, array_flip(['Household No.', 'Purok', 'Address', 'Head of Household', 'Residents', 'Social Aid', 'Status'])));
    }

    public function pdf(
        Household $household,
        RbiTemplatePdfGenerator $generator,
        BarangayOfficialsRegistry $officialsRegistry
    ): Response
    {
        Gate::authorize('view', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

        CurrentRbiEligibility::ensureHousehold($household);
        $household->load(['purok.barangay', 'headResident', 'currentMembers.socioEconomicProfile']);
        $barangay = $household->purok->barangay;
        $officials = $officialsRegistry->keyed($barangay);

        ExportAudit::log('secretary household profile', 'pdf', [
            'model_type' => Household::class,
            'record_count' => 1,
            'record_ids' => [$household->id],
            'document_type' => 'RBI Form A',
            'barangay_id' => $barangay->id,
            'barangay_name' => $barangay->name,
        ]);

        $content = $generator->generateHouseholds([$household], [
            'officials' => [
                'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
                'punong_barangay_name' => $officials->get(BarangayOfficial::ROLE_PUNONG_BARANGAY)?->official_name,
            ],
        ]);

        return $this->pdfResponse($content, 'household-rbi-form-'.$household->id.'.pdf');
    }

    public function printView(
        Household $household,
        RbiTemplatePdfGenerator $generator,
        BarangayOfficialsRegistry $officialsRegistry
    ): Response
    {
        Gate::authorize('view', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

        CurrentRbiEligibility::ensureHousehold($household);
        $household->load(['purok.barangay', 'headResident', 'currentMembers.socioEconomicProfile']);
        $barangay = $household->purok->barangay;
        $officials = $officialsRegistry->keyed($barangay);

        $content = $generator->generateHouseholds([$household], [
            'officials' => [
                'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
                'punong_barangay_name' => $officials->get(BarangayOfficial::ROLE_PUNONG_BARANGAY)?->official_name,
            ],
        ]);

        return $this->pdfResponse($content, 'household-rbi-form-'.$household->id.'.pdf', true);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Household::class);

        $availablePuroks = $this->secretaryPuroksQuery()
            ->active()
            ->orderBy('purok_number')
            ->get();

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
            'availablePuroks' => $availablePuroks,
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

        $household->load(['purok.barangay', 'headResident', 'residents.socioEconomicProfile', 'currentMembers.socioEconomicProfile']);

        return view('admin.geometry.households.show', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Household Details - HealthLink Secretary',
            'pageHeader' => 'Household Details',
            'household' => $household,
            'visitHistory' => $household->fieldVisits()->with('recordedBy')
                ->orderByDesc('visited_at')->orderByDesc('id')
                ->paginate(10, ['*'], 'visits_page'),
        ]);
    }

    public function edit(Household $household): View
    {
        Gate::authorize('update', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

        $household->load(['purok.barangay', 'headResident', 'currentMembers']);
        $availablePuroks = $this->secretaryPuroksQuery()
            ->active()
            ->orderBy('purok_number')
            ->get();

        return view('admin.geometry.households.edit', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Edit Household - HealthLink Secretary',
            'pageHeader' => 'Edit Household',
            'household' => $household,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'selectedBarangayId' => $household->purok->barangay_id,
            'availablePuroks' => $availablePuroks,
        ]);
    }

    public function update(HouseholdUpdateRequest $request, Household $household): RedirectResponse|View
    {
        Gate::authorize('update', $household);
        $this->ensureHouseholdBelongsToBarangay($household);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_social_aid_beneficiary'] = $data['is_social_aid_beneficiary'] ?? false;

        $household = app(HouseholdHeadReview::class)->household($request, $data, $household, function ($data, $household) {
            $oldValues = $household->toArray();
            $household->update($data);
            AuditLog::logMutation('updated', Auth::user(), $household, $oldValues, $household->fresh()->toArray());

            return $household;
        });
        if ($household instanceof View) {
            return $household;
        }

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

    private function listingQuery(Request $request)
    {
        return $this->filteredQuery($request)
            ->with(['purok.barangay', 'headResident'])
            ->withCount('currentMembers')
            ->orderBy('purok_id')
            ->orderBy('household_no')
            ->orderBy('id');
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

    private function pdfResponse(string $content, string $filename, bool $inline = false): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ]);
    }
}
