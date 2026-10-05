<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Concerns\NormalizesResidentLifecycle;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Admin\Geometry\ResidentStoreRequest;
use App\Http\Requests\Admin\Geometry\ResidentUpdateRequest;
use App\Http\Requests\Secretary\RelocateResidentRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BarangayOfficial;
use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentSocioEconomicProfile;
use App\Support\BarangayOfficialsRegistry;
use App\Support\CurrentRbiEligibility;
use App\Support\ExportAudit;
use App\Support\ExportDownload;
use App\Support\HouseholdHeadReview;
use App\Support\HouseholdRelationships;
use App\Support\RbiTemplatePdfGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResidentController extends Controller
{
    use InteractsWithSecretaryScope;
    use NormalizesResidentLifecycle;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Resident::class);

        $residents = $this->listingQuery($request)
            ->paginate(15)
            ->withQueryString();

        return view('admin.geometry.residents.index', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Residents - HealthLink Secretary',
            'pageHeader' => 'Resident Profiling & Directory',
            'canDelete' => false,
            'canRestore' => false,
            'canRelocate' => true,
            'residents' => $residents,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'puroks' => $this->secretaryPuroksQuery()
                ->with('barangay')
                ->active()
                ->orderBy('purok_number')
                ->get(),
            'households' => $this->secretaryHouseholdsQuery()
                ->with('purok.barangay')
                ->active()
                ->orderBy('household_no')
                ->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', Resident::class);

        $residents = $this->listingQuery($request)->get();

        $columns = [
            'Resident' => fn (Resident $resident) => $resident->formal_name,
            'Sex' => 'sex',
            'Birth Date' => fn (Resident $resident) => optional($resident->birth_date)?->format('Y-m-d'),
            'Age' => 'age',
            'Barangay' => fn (Resident $resident) => $resident->household?->purok?->barangay?->name,
            'Purok' => fn (Resident $resident) => $resident->household?->purok?->display_name,
            'Household' => fn (Resident $resident) => $resident->household?->household_no,
            'Relationship to Household Head' => 'relationship_to_head',
            'Education' => fn (Resident $resident) => $resident->socioEconomicProfile?->highest_education_level ?: 'N/A',
            'Occupation' => fn (Resident $resident) => $resident->socioEconomicProfile?->occupation ?: 'N/A',
            'Availability' => fn (Resident $resident) => $resident->is_active ? 'Active' : 'Inactive',
            'Civil Status' => fn (Resident $resident) => $resident->resident_status_label,
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => $this->secretaryUser()->assignedBarangay?->name,
            'Purok' => $this->secretaryPuroksQuery()->find($request->input('purok_id'))?->display_name,
            'Household' => $this->secretaryHouseholdsQuery()->find($request->input('household_id'))?->household_no,
            'Sex' => $request->input('sex'),
            'Status' => $request->input('status'),
            'Age Group' => $request->input('age_group'),
            'Lifecycle' => $request->input('lifecycle') ?: 'Current',
            'Civil Status' => match ($request->input('resident_status')) {
                Resident::STATUS_ACTIVE => 'Active Resident',
                Resident::STATUS_DECEASED => 'Deceased',
                Resident::STATUS_RELOCATED => 'Relocated',
                default => null,
            },
        ];

        return ExportDownload::make(
            $format,
            'Barangay Resident Registry',
            'Resident Profiling & Directory',
            'secretary_residents',
            $columns,
            $residents,
            $filters,
            $this->secretaryUser()->assignedBarangay?->name,
            Resident::class,
            array_intersect_key($columns, array_flip(['Resident', 'Sex', 'Age', 'Purok', 'Household', 'Availability', 'Civil Status']))
        );
    }

    public function pdf(
        Resident $resident,
        RbiTemplatePdfGenerator $generator,
        BarangayOfficialsRegistry $officialsRegistry
    ): Response
    {
        Gate::authorize('view', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        CurrentRbiEligibility::ensureResident($resident);
        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);
        $barangay = $resident->household->purok->barangay;
        $officials = $officialsRegistry->keyed($barangay);

        ExportAudit::log('secretary resident profile', 'pdf', [
            'model_type' => Resident::class,
            'record_count' => 1,
            'record_ids' => [$resident->id],
            'document_type' => 'RBI Form B',
            'barangay_id' => $barangay->id,
            'barangay_name' => $barangay->name,
        ]);

        $content = $generator->generateResidents([$resident], [
            'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
        ]);

        return $this->pdfResponse($content, 'resident-rbi-form-'.$resident->id.'.pdf');
    }

    public function printView(
        Resident $resident,
        RbiTemplatePdfGenerator $generator,
        BarangayOfficialsRegistry $officialsRegistry
    ): Response
    {
        Gate::authorize('view', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        CurrentRbiEligibility::ensureResident($resident);
        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);
        $barangay = $resident->household->purok->barangay;
        $officials = $officialsRegistry->keyed($barangay);

        $content = $generator->generateResidents([$resident], [
            'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
        ]);

        return $this->pdfResponse($content, 'resident-rbi-form-'.$resident->id.'.pdf', true);
    }

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

        $selectedHouseholdId = $request->input('household_id');
        $selectedHousehold = $selectedHouseholdId
            ? $this->secretaryHouseholdsQuery()->with('purok.barangay')->find($selectedHouseholdId)
            : null;
        $selectedBarangayId = $request->input('barangay_id', $selectedHousehold?->purok?->barangay_id ?? $this->assignedBarangayId());
        $selectedPurokId = $request->input('purok_id', $selectedHousehold?->purok_id);
        $availablePuroks = $this->secretaryPuroksQuery()
            ->with('barangay')
            ->active()
            ->orderBy('purok_number')
            ->get();
        $availableHouseholds = $selectedPurokId
            ? $this->secretaryHouseholdsQuery()
                ->where('purok_id', $selectedPurokId)
                ->active()
                ->orderBy('household_no')
                ->get(['id', 'household_no', 'household_address'])
            : collect();

        return view('admin.geometry.residents.create', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Create Resident - HealthLink Secretary',
            'pageHeader' => 'Create Resident',
            'resident' => $resident,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'selectedBarangayId' => $selectedBarangayId,
            'selectedPurokId' => $selectedPurokId,
            'selectedHouseholdId' => $selectedHouseholdId,
            'availablePuroks' => $availablePuroks,
            'availableHouseholds' => $availableHouseholds,
        ]);
    }

    public function store(ResidentStoreRequest $request): RedirectResponse|View
    {
        Gate::authorize('create', Resident::class);

        $data = $request->validated();
        $data = $this->normalizeResidentLifecycle($data);
        $resident = app(HouseholdHeadReview::class)->resident($request, $data, null, function ($data) {
            $resident = Resident::create(Arr::except($data, [...$this->socioEconomicFields(), 'set_as_household_head']));
            $this->syncSocioEconomicProfile($resident, $data);
            AuditLog::logMutation('created', Auth::user(), $resident);

            return $resident;
        });
        if ($resident instanceof View) {
            return $resident;
        }

        return redirect()
            ->route('secretary.residents.show', $resident)
            ->with('success', "Resident {$resident->full_name} created successfully.");
    }

    public function show(Resident $resident): View
    {
        Gate::authorize('view', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $resident->load(['household.headResident', 'household.purok.barangay', 'socioEconomicProfile']);

        return view('admin.geometry.residents.show', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Resident Details - HealthLink Secretary',
            'pageHeader' => 'Resident Details',
            'canRelocate' => true,
            'resident' => $resident,
        ]);
    }

    public function edit(Resident $resident): View
    {
        Gate::authorize('update', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);
        $selectedBarangayId = $resident->household->purok->barangay_id;
        $selectedPurokId = $resident->household->purok_id;
        $selectedHouseholdId = $resident->household_id;
        $availablePuroks = $this->secretaryPuroksQuery()
            ->with('barangay')
            ->active()
            ->orderBy('purok_number')
            ->get();
        $availableHouseholds = $this->secretaryHouseholdsQuery()
            ->where('purok_id', $selectedPurokId)
            ->active()
            ->orderBy('household_no')
            ->get(['id', 'household_no', 'household_address']);

        return view('admin.geometry.residents.edit', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Edit Resident - HealthLink Secretary',
            'pageHeader' => 'Edit Resident',
            'resident' => $resident,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
            'selectedBarangayId' => $selectedBarangayId,
            'selectedPurokId' => $selectedPurokId,
            'selectedHouseholdId' => $selectedHouseholdId,
            'availablePuroks' => $availablePuroks,
            'availableHouseholds' => $availableHouseholds,
        ]);
    }

    public function update(ResidentUpdateRequest $request, Resident $resident): RedirectResponse|View
    {
        Gate::authorize('update', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $data = $request->validated();
        $data = $this->normalizeResidentLifecycle($data);
        $resident = app(HouseholdHeadReview::class)->resident($request, $data, $resident, function ($data, $resident) {
            $oldValues = $resident->load('socioEconomicProfile')->toArray();
            $resident->update(Arr::except($data, [...$this->socioEconomicFields(), 'set_as_household_head']));
            $this->syncSocioEconomicProfile($resident, $data);
            AuditLog::logMutation('updated', Auth::user(), $resident, $oldValues, $resident->fresh()->load('socioEconomicProfile')->toArray());

            return $resident;
        });
        if ($resident instanceof View) {
            return $resident;
        }

        return redirect()
            ->route('secretary.residents.show', $resident)
            ->with('success', "Resident {$resident->full_name} updated successfully.");
    }

    public function toggleStatus(Resident $resident): RedirectResponse
    {
        Gate::authorize('toggleStatus', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

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
            "Resident {$resident->full_name} has been ".($newStatus ? 'activated' : 'marked inactive').'.'
        );
    }

    public function editRelocation(Resident $resident): View
    {
        Gate::authorize('update', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $resident->load(['household.purok.barangay']);

        return view('secretary.residents.relocate', [
            'resident' => $resident,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
            'selectedTargetPurokId' => old('target_purok_id', $resident->household->purok_id),
            'selectedTargetHouseholdId' => old('target_household_id'),
            'existingHouseholds' => old('target_purok_id')
                ? $this->secretaryHouseholdsQuery()
                    ->where('purok_id', (int) old('target_purok_id'))
                    ->active()
                    ->orderBy('household_no')
                    ->get()
                : collect(),
        ]);
    }

    public function relocate(RelocateResidentRequest $request, Resident $resident): RedirectResponse|View
    {
        Gate::authorize('update', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $payload = $request->validated();
        $resident->load('household.purok');
        $sourceId = $resident->household_id;
        $targetId = $payload['destination'] === 'existing_household' ? (int) $payload['target_household_id'] : null;
        $plans = [];
        if ($resident->is_household_head && $resident->household->currentMembers()->whereKeyNot($resident->id)->exists()) {
            $plans[$sourceId] = ['choose_candidate' => true, 'exclude_id' => $resident->id];
        }
        if ($targetId && ($payload['set_as_household_head'] ?? false)) {
            $plans[$targetId] = ['candidate_id' => $resident->id, 'candidate_name' => $resident->full_name];
        }
        if (! ($payload['set_as_household_head'] ?? false)) {
            HouseholdRelationships::validate($payload['relationship_to_head'], $resident->is_household_head ? null : $resident->relationship_to_head);
        }
        $result = app(HouseholdHeadReview::class)->run($request, array_filter([$sourceId, $targetId]), $plans,
            function ($households, $plans, $heads) use ($resident, $payload, $sourceId, $targetId): void {
                $resident = $households[$sourceId]->residents->firstWhere('id', $resident->id);
                if (! $resident) {
                    throw ValidationException::withMessages(['head_reviews' => 'This resident moved. Reload the relocation form.']);
                }
                $oldHousehold = $households[$sourceId];
                $wasOldHead = (int) $oldHousehold->head_resident_id === (int) $resident->id;
                if ($wasOldHead && ! ($payload['set_as_household_head'] ?? false)
                    && HouseholdRelationships::isHead($payload['relationship_to_head'])) {
                    throw ValidationException::withMessages(['relationship_to_head' => 'Choose an ordinary relationship in the destination household, or explicitly designate this resident as its head.']);
                }

                if ($payload['destination'] === 'new_household') {
                    $targetHousehold = Household::create([
                        'purok_id' => (int) $payload['target_purok_id'],
                        'household_no' => $payload['new_household_no'],
                        'household_address' => $payload['new_household_address'],
                        'is_social_aid_beneficiary' => $payload['new_household_social_aid'] ?? false,
                        'is_active' => true,
                    ]);

                    AuditLog::logMutation('created', Auth::user(), $targetHousehold);
                } else {
                    $targetHousehold = $households[$targetId];
                }

                $targetHousehold->loadMissing('headResident');
                $oldResidentValues = $resident->toArray();

                $relationship = $payload['set_as_household_head'] ?? false
                    ? HouseholdRelationships::HEAD
                    : $payload['relationship_to_head'];

                $resident->update([
                    'household_id' => $targetHousehold->id,
                    'relationship_to_head' => $relationship,
                    'resident_status' => Resident::STATUS_ACTIVE,
                    'moved_in_at' => $payload['moved_in_at'] ?? now()->toDateString(),
                    'moved_out_at' => null,
                    'date_of_death' => null,
                    'status_notes' => $payload['status_notes'] ?? $resident->status_notes,
                    'is_active' => true,
                ]);

                foreach ($plans as $id => $plan) {
                    $candidate = ($plan['choose_candidate'] ?? false) ? Resident::findOrFail($plan['candidate_id']) : $resident;
                    $heads->designate($households[$id], $candidate, $plan['relationships']);
                }
                if ($wasOldHead && ! isset($plans[$sourceId])) {
                    $heads->clearEmpty($oldHousehold);
                }
                if (! $targetId && ($payload['set_as_household_head'] ?? false)) {
                    $heads->designate($targetHousehold, $resident, []);
                }

                AuditLog::logMutation(
                    'updated',
                    Auth::user(),
                    $resident,
                    $oldResidentValues,
                    $resident->fresh()->load('household.purok')->toArray()
                );
            });
        if ($result instanceof View) {
            return $result;
        }

        return redirect()
            ->route('secretary.residents.show', $resident->fresh())
            ->with('success', "Resident {$resident->full_name} relocated successfully.");
    }

    public function householdsByPurok(Request $request)
    {
        $request->validate([
            'purok_id' => ['required', 'exists:puroks,id'],
        ]);

        if (! $this->secretaryUser()->canAccessPurok((int) $request->input('purok_id'))) {
            abort(403);
        }

        return response()->json(
            $this->secretaryHouseholdsQuery()
                ->where('purok_id', $request->input('purok_id'))
                ->active()
                ->orderBy('household_no')
                ->get(['id', 'household_no', 'household_address'])
        );
    }

    private function listingQuery(Request $request)
    {
        return $this->filteredQuery($request)
            ->with(['household.purok.barangay', 'socioEconomicProfile'])
            ->latest('last_name')
            ->orderByDesc('id');
    }

    private function filteredQuery(Request $request)
    {
        $query = $this->secretaryResidentsQuery();

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

        $resident->socioEconomicProfile()->updateOrCreate(
            ['resident_id' => $resident->id],
            $payload
        );
    }

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

    private function pdfResponse(string $content, string $filename, bool $inline = false): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ]);
    }
}
