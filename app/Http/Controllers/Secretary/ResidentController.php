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
use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentSocioEconomicProfile;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResidentController extends Controller
{
    use InteractsWithSecretaryScope;
    use NormalizesResidentLifecycle;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Resident::class);

        $residents = $this->filteredQuery($request)
            ->with(['household.purok.barangay', 'socioEconomicProfile'])
            ->latest('last_name')
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
            'Barangay' => $this->secretaryUser()->assignedBarangay?->name,
            'Purok' => $this->secretaryPuroksQuery()->find($request->input('purok_id'))?->display_name,
            'Household' => $this->secretaryHouseholdsQuery()->find($request->input('household_id'))?->household_no,
            'Sex' => $request->input('sex'),
            'Status' => $request->input('status'),
            'Civil Status' => match ($request->input('resident_status')) {
                Resident::STATUS_ACTIVE => 'Active Resident',
                Resident::STATUS_DECEASED => 'Deceased',
                Resident::STATUS_RELOCATED => 'Relocated',
                default => null,
            },
        ];

        ExportAudit::log('secretary resident registry', $format, [
            'model_type' => Resident::class,
            'record_count' => $residents->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_residents_{$timestamp}.csv", $columns, $residents),
            'xlsx' => TabularExport::xlsx("secretary_residents_{$timestamp}.xlsx", 'Secretary Residents', $columns, $residents),
            'pdf' => TabularExport::pdf("secretary_residents_{$timestamp}.pdf", 'Barangay Resident Registry', $columns, $residents, $filters),
            default => abort(404),
        };
    }

    public function pdf(Resident $resident): Response
    {
        Gate::authorize('view', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);

        ExportAudit::log('secretary resident profile', 'pdf', [
            'model_type' => Resident::class,
            'record_count' => 1,
            'record_ids' => [$resident->id],
        ]);

        return Pdf::loadView('documents.residents.rbi-form', [
            'resident' => $resident,
            'attestedByName' => Auth::user()?->name,
            'attestedByRole' => 'Barangay Secretary',
            'accomplishingPartyName' => Auth::user()?->name,
            'documentDate' => now(),
            'showBrowserPrintScript' => false,
        ])->setPaper('a4')->download('resident-rbi-form-'.$resident->id.'.pdf');
    }

    public function printView(Resident $resident): View
    {
        Gate::authorize('view', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);

        return view('documents.residents.rbi-form', [
            'resident' => $resident,
            'attestedByName' => Auth::user()?->name,
            'attestedByRole' => 'Barangay Secretary',
            'accomplishingPartyName' => Auth::user()?->name,
            'documentDate' => now(),
            'showBrowserPrintScript' => true,
        ]);
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

    public function store(ResidentStoreRequest $request): RedirectResponse
    {
        Gate::authorize('create', Resident::class);

        $data = $request->validated();
        $data = $this->normalizeResidentLifecycle($data);
        $residentData = Arr::except($data, $this->socioEconomicFields());

        $resident = Resident::create($residentData);
        $this->syncSocioEconomicProfile($resident, $data);

        AuditLog::logMutation('created', Auth::user(), $resident);

        return redirect()
            ->route('secretary.residents.show', $resident)
            ->with('success', "Resident {$resident->full_name} created successfully.");
    }

    public function show(Resident $resident): View
    {
        Gate::authorize('view', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $resident->load(['household.purok.barangay', 'socioEconomicProfile']);

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

    public function update(ResidentUpdateRequest $request, Resident $resident): RedirectResponse
    {
        Gate::authorize('update', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $data = $request->validated();
        $data = $this->normalizeResidentLifecycle($data);
        $oldValues = $resident->load('socioEconomicProfile')->toArray();
        $residentData = Arr::except($data, $this->socioEconomicFields());

        $resident->update($residentData);
        $this->syncSocioEconomicProfile($resident, $data);

        AuditLog::logMutation('updated', Auth::user(), $resident, $oldValues, $resident->fresh()->load('socioEconomicProfile')->toArray());

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

    public function relocate(RelocateResidentRequest $request, Resident $resident): RedirectResponse
    {
        Gate::authorize('update', $resident);
        $this->ensureResidentBelongsToBarangay($resident);

        $payload = $request->validated();

        DB::transaction(function () use ($resident, $payload): void {
            $resident->load('household.purok');
            $oldHousehold = $resident->household;
            $oldHouseholdValues = $oldHousehold->toArray();
            $wasOldHead = (int) $oldHousehold->head_resident_id === (int) $resident->id;

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
                $targetHousehold = $this->secretaryHouseholdsQuery()
                    ->active()
                    ->findOrFail((int) $payload['target_household_id']);
            }

            $targetHousehold->loadMissing('headResident');
            $targetHouseholdValues = $targetHousehold->toArray();
            $oldResidentValues = $resident->toArray();

            $relationship = $payload['set_as_household_head'] ?? false
                ? 'Head of Household'
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

            if ($wasOldHead) {
                $oldHousehold->update(['head_resident_id' => null]);

                AuditLog::logMutation('updated', Auth::user(), $oldHousehold, $oldHouseholdValues, $oldHousehold->fresh()->toArray());
            }

            if ($payload['set_as_household_head'] ?? false) {
                $targetHousehold->update(['head_resident_id' => $resident->id]);

                AuditLog::logMutation('updated', Auth::user(), $targetHousehold, $targetHouseholdValues, $targetHousehold->fresh()->toArray());
            }

            AuditLog::logMutation(
                'updated',
                Auth::user(),
                $resident,
                $oldResidentValues,
                $resident->fresh()->load('household.purok')->toArray()
            );
        });

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
}
