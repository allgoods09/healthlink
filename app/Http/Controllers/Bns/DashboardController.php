<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\OptCycle;
use App\Support\Nutrition\OptCycleReporting;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithBnsScope;

    public function __invoke(): View
    {
        $openFlags = $this->bnsOpenAssessmentFlagsQuery()
            ->with(['resident.household.purok', 'flaggedBy'])
            ->latest('flagged_at')
            ->limit(6)
            ->get();

        $recentCampaigns = OptCycle::query()->where('barangay_id', $this->assignedBarangayId())->withProgress()
            ->latest('reference_date')
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
            'openAssessmentFlagCount' => $this->bnsOpenAssessmentFlagsQuery()->count(),
            'activeCampaignCount' => OptCycle::where('barangay_id', $this->assignedBarangayId())->active()->count(),
            'cycleSummary' => OptCycleReporting::latestSummary($this->assignedBarangayId()),
            'activeFeedingProgramCount' => $this->bnsFeedingProgramsQuery()
                ->whereIn('program_status', ['planned', 'active'])
                ->count(),
            'pregnantResidentCount' => $this->bnsMaternalProfilesQuery()->where('is_currently_pregnant', true)->count(),
            'lactatingResidentCount' => $this->bnsMaternalProfilesQuery()->where('is_currently_lactating', true)->count(),
            'targetClientCount' => $targetClientCount,
            'openFlags' => $openFlags,
            'recentCampaigns' => $recentCampaigns,
        ]);
    }
}
