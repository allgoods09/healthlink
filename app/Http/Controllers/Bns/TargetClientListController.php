<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TargetClientListController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $watchlistQuery = $this->watchlistQuery($request);

        return view('bns.watchlist.index', [
            'watchlist' => $watchlistQuery->paginate(15)->withQueryString(),
            'watchlistCount' => (clone $watchlistQuery)->count(),
            'severelyUnderweightCount' => (clone $watchlistQuery)->where('weight_for_age_status', 'Severely Underweight')->count(),
            'stuntedCount' => (clone $watchlistQuery)->whereIn('height_for_age_status', ['Severely Stunted', 'Stunted'])->count(),
            'wastedCount' => (clone $watchlistQuery)->whereIn('weight_for_length_height_status', ['Severely Wasted', 'Wasted'])->count(),
            'campaignPeriods' => $this->bnsCampaignPeriodsQuery()
                ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
                ->orderByDesc('is_active')
                ->orderByDesc('starts_on')
                ->get(),
            'puroks' => $this->bnsPuroksQuery()->orderBy('purok_number')->get(),
            'openFlags' => $this->bnsOpenAssessmentFlagsQuery()
                ->with(['resident.household.purok', 'flaggedBy'])
                ->latest('flagged_at')
                ->limit(6)
                ->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $watchlist = $this->watchlistQuery($request)->get();
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
            'WFA Status' => 'weight_for_age_status',
            'HFA Status' => 'height_for_age_status',
            'WFH/L Status' => 'weight_for_length_height_status',
            'Target Client Reasons' => fn (OptMeasurement $measurement) => implode(', ', $measurement->target_client_reasons),
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Campaign' => optional($this->bnsCampaignPeriodsQuery()->find($request->integer('campaign_period_id')))->name,
            'Purok' => optional($this->bnsPuroksQuery()->find($request->integer('purok_id')))->display_name,
        ];

        ExportAudit::log('bns target client list', $format, [
            'barangay_id' => $this->assignedBarangayId(),
            'record_count' => $watchlist->count(),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("bns_target_client_list_{$timestamp}.csv", $columns, $watchlist),
            'xlsx' => TabularExport::xlsx("bns_target_client_list_{$timestamp}.xlsx", 'Target Client List', $columns, $watchlist),
            'pdf' => TabularExport::pdf("bns_target_client_list_{$timestamp}.pdf", 'Target Client List / Malnutrition Watchlist', $columns, $watchlist, $filters),
            default => abort(404),
        };
    }

    private function watchlistQuery(Request $request): Builder
    {
        $campaignPeriodId = $request->filled('campaign_period_id')
            ? $request->integer('campaign_period_id')
            : null;

        $query = $this->bnsOptMeasurementsQuery()
            ->with(['resident.household.purok', 'campaignPeriod', 'measuredBy'])
            ->whereIn('id', $this->latestOptMeasurementIdsSubquery($campaignPeriodId))
            ->where(function ($measurementQuery): void {
                $measurementQuery
                    ->whereIn('weight_for_age_status', ['Severely Underweight', 'Underweight'])
                    ->orWhereIn('height_for_age_status', ['Severely Stunted', 'Stunted'])
                    ->orWhereIn('weight_for_length_height_status', ['Severely Wasted', 'Wasted']);
            })
            ->latest('measurement_date')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('resident', function ($residentQuery) use ($search): void {
                $residentQuery->where('official_resident_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('purok_id')) {
            $purokId = $request->integer('purok_id');
            $query->whereHas('resident.household', function ($householdQuery) use ($purokId): void {
                $householdQuery->where('purok_id', $purokId);
            });
        }

        return $query;
    }
}
