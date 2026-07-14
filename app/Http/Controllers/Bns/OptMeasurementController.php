<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bns\StoreOptMeasurementRequest;
use App\Models\AuditLog;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use App\Support\ExportAudit;
use App\Support\Nutrition\GrowthAssessmentService;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OptMeasurementController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $query = $this->bnsOptMeasurementsQuery()
            ->with(['resident.household.purok', 'campaignPeriod', 'measuredBy'])
            ->latest('measurement_date')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($measurementQuery) use ($search): void {
                $measurementQuery->where('remarks', 'like', "%{$search}%")
                    ->orWhereHas('resident', function ($residentQuery) use ($search): void {
                        $residentQuery->where('official_resident_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('campaign_period_id')) {
            $query->where('campaign_period_id', $request->integer('campaign_period_id'));
        }

        if ($request->filled('purok_id')) {
            $purokId = $request->integer('purok_id');
            $query->whereHas('resident.household', function ($householdQuery) use ($purokId): void {
                $householdQuery->where('purok_id', $purokId);
            });
        }

        if ($request->filled('target_client')) {
            $query->where(function ($measurementQuery): void {
                $measurementQuery
                    ->whereIn('weight_for_age_status', ['Severely Underweight', 'Underweight'])
                    ->orWhereIn('height_for_age_status', ['Severely Stunted', 'Stunted'])
                    ->orWhereIn('weight_for_length_height_status', ['Severely Wasted', 'Wasted']);
            });
        }

        return view('bns.opt-measurements.index', [
            'measurements' => $query->paginate(15)->withQueryString(),
            'campaignPeriods' => $this->bnsCampaignPeriodsQuery()
                ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
                ->orderByDesc('is_active')
                ->orderByDesc('starts_on')
                ->get(),
            'puroks' => $this->bnsPuroksQuery()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $query = $this->bnsOptMeasurementsQuery()
            ->with(['resident.household.purok', 'campaignPeriod', 'measuredBy'])
            ->latest('measurement_date')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($measurementQuery) use ($search): void {
                $measurementQuery->where('remarks', 'like', "%{$search}%")
                    ->orWhereHas('resident', function ($residentQuery) use ($search): void {
                        $residentQuery->where('official_resident_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('campaign_period_id')) {
            $query->where('campaign_period_id', $request->integer('campaign_period_id'));
        }

        if ($request->filled('purok_id')) {
            $purokId = $request->integer('purok_id');
            $query->whereHas('resident.household', function ($householdQuery) use ($purokId): void {
                $householdQuery->where('purok_id', $purokId);
            });
        }

        if ($request->filled('target_client')) {
            $query->where(function ($measurementQuery): void {
                $measurementQuery
                    ->whereIn('weight_for_age_status', ['Severely Underweight', 'Underweight'])
                    ->orWhereIn('height_for_age_status', ['Severely Stunted', 'Stunted'])
                    ->orWhereIn('weight_for_length_height_status', ['Severely Wasted', 'Wasted']);
            });
        }

        $measurements = $query->get();
        $timestamp = now()->format('Ymd_His');
        $columns = [
            'Resident Code' => fn (OptMeasurement $measurement) => $measurement->resident?->official_resident_code,
            'Resident' => fn (OptMeasurement $measurement) => $measurement->resident?->formal_name,
            'Purok' => fn (OptMeasurement $measurement) => $measurement->resident?->household?->purok?->display_name,
            'Campaign' => fn (OptMeasurement $measurement) => $measurement->campaignPeriod?->name,
            'Measurement Date' => fn (OptMeasurement $measurement) => optional($measurement->measurement_date)->format('Y-m-d'),
            'Age (Months)' => 'age_in_months',
            'Weight (kg)' => 'weight_kg',
            'Height/Length (cm)' => 'height_cm',
            'Posture' => fn (OptMeasurement $measurement) => $measurement->measurement_posture_label,
            'WFA Z' => 'weight_for_age_z_score',
            'WFA Status' => 'weight_for_age_status',
            'HFA Z' => 'height_for_age_z_score',
            'HFA Status' => 'height_for_age_status',
            'WFH/L Z' => 'weight_for_length_height_z_score',
            'WFH/L Status' => 'weight_for_length_height_status',
            'Target Client' => fn (OptMeasurement $measurement) => $measurement->is_target_client ? 'Yes' : 'No',
            'Remarks' => 'remarks',
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Campaign' => optional($this->bnsCampaignPeriodsQuery()->find($request->integer('campaign_period_id')))->name,
            'Purok' => optional($this->bnsPuroksQuery()->find($request->integer('purok_id')))->display_name,
            'Target Client Only' => $request->filled('target_client') ? 'Yes' : null,
        ];

        ExportAudit::log('bns opt plus masterlist', $format, [
            'barangay_id' => $this->assignedBarangayId(),
            'record_count' => $measurements->count(),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("bns_opt_measurements_{$timestamp}.csv", $columns, $measurements),
            'xlsx' => TabularExport::xlsx("bns_opt_measurements_{$timestamp}.xlsx", 'OPT Measurements', $columns, $measurements),
            'pdf' => TabularExport::pdf("bns_opt_measurements_{$timestamp}.pdf", 'OPT+ Masterlist', $columns, $measurements, $filters),
            default => abort(404),
        };
    }

    public function create(Request $request): View
    {
        $selectedResident = null;

        if ($request->filled('resident_id')) {
            $selectedResident = $this->bnsOptEligibleChildrenQuery()
                ->with('household.purok')
                ->find($request->integer('resident_id'));
        }

        return view('bns.opt-measurements.create', [
            'campaignPeriods' => $this->bnsCampaignPeriodsQuery()
                ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
                ->orderByDesc('is_active')
                ->orderByDesc('starts_on')
                ->get(),
            'residentOptions' => $this->bnsOptEligibleChildrenQuery()
                ->with('household.purok')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'selectedResident' => $selectedResident,
            'activeCampaign' => $this->bnsCampaignPeriodsQuery()
                ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
                ->active()
                ->latest('starts_on')
                ->first(),
        ]);
    }

    public function store(StoreOptMeasurementRequest $request, GrowthAssessmentService $growthAssessment): RedirectResponse
    {
        $resident = $this->bnsOptEligibleChildrenQuery()
            ->with('household.purok')
            ->findOrFail($request->integer('resident_id'));

        $campaignPeriod = $this->bnsCampaignPeriodsQuery()
            ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
            ->findOrFail($request->integer('campaign_period_id'));

        $measurement = DB::transaction(function () use ($request, $resident, $campaignPeriod, $growthAssessment): OptMeasurement {
            $assessment = $growthAssessment->assess(
                $resident,
                Carbon::parse($request->input('measurement_date')),
                (float) $request->input('weight_kg'),
                (float) $request->input('height_cm'),
                $request->string('measurement_posture')->toString(),
            );

            return OptMeasurement::query()->create([
                'resident_id' => $resident->id,
                'barangay_id' => $this->assignedBarangayId(),
                'campaign_period_id' => $campaignPeriod->id,
                'measured_by_user_id' => Auth::id(),
                'measurement_date' => $request->date('measurement_date'),
                'age_in_months' => $assessment['age_in_months'],
                'sex_snapshot' => $assessment['sex_snapshot'],
                'weight_kg' => $request->input('weight_kg'),
                'height_cm' => $request->input('height_cm'),
                'measurement_posture' => $request->string('measurement_posture')->toString(),
                'weight_for_age_z_score' => $assessment['weight_for_age_z_score'],
                'weight_for_age_status' => $assessment['weight_for_age_status'],
                'height_for_age_z_score' => $assessment['height_for_age_z_score'],
                'height_for_age_status' => $assessment['height_for_age_status'],
                'weight_for_length_height_z_score' => $assessment['weight_for_length_height_z_score'],
                'weight_for_length_height_status' => $assessment['weight_for_length_height_status'],
                'remarks' => $request->input('remarks'),
            ]);
        });

        AuditLog::logMutation('created', Auth::user(), $measurement);

        return redirect()
            ->route('bns.opt-measurements.show', $measurement)
            ->with('success', 'OPT+ measurement logged successfully.');
    }

    public function show(OptMeasurement $optMeasurement): View
    {
        $this->ensureOptMeasurementBelongsToBarangay($optMeasurement);

        $optMeasurement->load(['resident.household.purok', 'campaignPeriod', 'measuredBy']);

        return view('bns.opt-measurements.show', [
            'measurement' => $optMeasurement,
        ]);
    }
}
