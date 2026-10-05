<?php

namespace App\Http\Controllers\Phn;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Phn\Concerns\InteractsWithPhnScope;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\Purok;
use App\Models\Resident;
use App\Support\ExportDownload;
use App\Support\WebResidentPopulation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResidentController extends Controller
{
    use InteractsWithPhnScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Resident::class);

        $residents = $this->listingQuery($request)
            ->paginate(15)
            ->withQueryString();

        return view('phn.residents.index', [
            'residents' => $residents,
            'barangays' => $this->phnBarangaysQuery()->active()->orderBy('name')->get(),
            'puroks' => $this->phnPuroksQuery()->with('barangay')->active()->orderBy('barangay_id')->orderBy('purok_number')->get(),
        ]);
    }

    public function show(Resident $resident): View
    {
        Gate::authorize('view', $resident);
        $this->ensureResidentExists($resident);

        $resident->load([
            'household.headResident',
            'household.purok.barangay',
            'socioEconomicProfile',
            'latestOptMeasurement.campaignPeriod',
            'latestPhilpenRiskAssessment.recordedBy',
            'nutritionFlags' => fn ($query) => $query->with(['flaggedBy', 'closedBy', 'resolvedMeasurement'])->latest('flagged_at')->limit(8),
            'triageRecords' => fn ($query) => $query->with(['recordedBy.assignedPurok', 'consumedBy', 'clinicalEncounter'])->latest('measured_at')->limit(8),
            'clinicalEncounters' => fn ($query) => $query->with(['attendedBy', 'triageRecord.recordedBy'])->latest('encountered_at')->limit(8),
            'profileUpdateRequests' => fn ($query) => $query->with(['submittedBy', 'reviewedBy'])->latest()->limit(8),
            'philpenRiskAssessments' => fn ($query) => $query->with('recordedBy')->latest('assessment_date')->latest('id')->limit(8),
        ]);

        return view('phn.residents.show', [
            'resident' => $resident,
            'openNutritionFlagCount' => $resident->nutritionFlags->where('flag_status', 'open')->count(),
            'latestEncounter' => $resident->clinicalEncounters->first(),
            'latestTriage' => $resident->triageRecords->first(),
            'latestPhilpenAssessment' => $resident->latestPhilpenRiskAssessment,
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', Resident::class);

        $columns = [
            'Resident Code' => 'official_resident_code',
            'Resident' => fn (Resident $resident) => $resident->formal_name,
            'Sex' => 'sex',
            'Age' => 'age',
            'Barangay' => fn (Resident $resident) => $resident->household?->purok?->barangay?->name,
            'Purok' => fn (Resident $resident) => $resident->household?->purok?->display_name,
            'Household' => fn (Resident $resident) => $resident->household?->household_no,
            'Residency Status' => fn (Resident $resident) => WebResidentPopulation::label($resident),
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => $this->phnBarangaysQuery()->find($request->integer('barangay_id'))?->name,
            'Purok' => $this->phnPuroksQuery()->find($request->integer('purok_id'))?->display_name,
            'Sex' => $request->input('sex'),
            'Residency Status' => WebResidentPopulation::description($request),
            'Availability' => $request->input('is_active'),
        ];

        return ExportDownload::make($format, 'PHN Residents Directory', 'Clinical', 'phn_residents', $columns, $this->listingQuery($request)->get(), $filters, 'Municipality-wide', Resident::class);
    }

    private function listingQuery(Request $request): Builder
    {
        return $this->filteredQuery($request)
            ->with(['household.purok.barangay', 'socioEconomicProfile', 'latestOptMeasurement.campaignPeriod'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = WebResidentPopulation::directory($this->phnResidentsQuery(), $request);

        if ($request->filled('barangay_id')) {
            $query->whereHas('household.purok', fn (Builder $builder) => $builder->where('barangay_id', $request->integer('barangay_id')));
        }

        if ($request->filled('purok_id')) {
            $query->whereHas('household', fn (Builder $builder) => $builder->where('purok_id', $request->integer('purok_id')));
        }

        if ($request->filled('sex')) {
            $query->where('sex', $request->input('sex'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('official_resident_code', 'like', "%{$search}%")
                    ->orWhere('philsys_card_no', 'like', "%{$search}%")
                    ->orWhereHas('household', function (Builder $householdQuery) use ($search): void {
                        $householdQuery->where('household_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('household.purok.barangay', function (Builder $barangayQuery) use ($search): void {
                        $barangayQuery->where('barangays.name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
