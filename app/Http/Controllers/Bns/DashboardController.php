<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\NutritionCampaignPeriod;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithBnsScope;

    public function __invoke(): View
    {
        $recentMeasurements = $this->bnsOptMeasurementsQuery()
            ->with(['resident.household.purok', 'campaignPeriod'])
            ->latest('measurement_date')
            ->latest('created_at')
            ->limit(6)
            ->get();

        $openFlags = $this->bnsOpenAssessmentFlagsQuery()
            ->with(['resident.household.purok', 'flaggedBy'])
            ->latest('flagged_at')
            ->limit(6)
            ->get();

        $maternalProfiles = $this->bnsMaternalProfilesQuery()
            ->with(['resident.household.purok'])
            ->where(function ($query): void {
                $query->where('is_currently_pregnant', true)
                    ->orWhere('is_currently_lactating', true);
            })
            ->orderByDesc('last_status_updated_at')
            ->latest()
            ->limit(6)
            ->get();

        $recentCampaigns = $this->bnsCampaignPeriodsQuery()
            ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
            ->latest('starts_on')
            ->limit(5)
            ->get();

        $targetClientCount = $this->bnsOptMeasurementsQuery()
            ->whereIn('id', $this->latestOptMeasurementIdsSubquery())
            ->where(function ($query): void {
                $query->whereIn('weight_for_age_status', ['Severely Underweight', 'Underweight'])
                    ->orWhereIn('height_for_age_status', ['Severely Stunted', 'Stunted'])
                    ->orWhereIn('weight_for_length_height_status', ['Severely Wasted', 'Wasted']);
            })
            ->count();

        return view('bns.dashboard', [
            'eligibleChildCount' => $this->bnsOptEligibleChildrenQuery()->count(),
            'measuredThisMonthCount' => $this->bnsOptMeasurementsQuery()
                ->whereMonth('measurement_date', now()->month)
                ->whereYear('measurement_date', now()->year)
                ->count(),
            'openAssessmentFlagCount' => $this->bnsOpenAssessmentFlagsQuery()->count(),
            'activeCampaignCount' => $this->bnsCampaignPeriodsQuery()
                ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
                ->where('is_active', true)
                ->count(),
            'activeFeedingProgramCount' => $this->bnsFeedingProgramsQuery()
                ->whereIn('program_status', ['planned', 'active'])
                ->count(),
            'pregnantResidentCount' => $this->bnsMaternalProfilesQuery()->where('is_currently_pregnant', true)->count(),
            'lactatingResidentCount' => $this->bnsMaternalProfilesQuery()->where('is_currently_lactating', true)->count(),
            'targetClientCount' => $targetClientCount,
            'recentMeasurements' => $recentMeasurements,
            'openFlags' => $openFlags,
            'maternalProfiles' => $maternalProfiles,
            'recentCampaigns' => $recentCampaigns,
        ]);
    }
}
