<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\CommunityCampaignAssignment;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithBhwScope;

    public function __invoke(): View
    {
        $today = now()->toDateString();

        $dueTodayAssignments = $this->bhwCampaignAssignmentsQuery()
            ->with(['campaign.assignedPurok', 'resident.household.purok', 'household.purok'])
            ->whereHas('campaign', function ($query) use ($today): void {
                $query->whereDate('scheduled_for', $today)
                    ->where('campaign_status', 'active');
            })
            ->orderBy('assignment_status')
            ->latest('id')
            ->limit(6)
            ->get();

        $openFlags = $this->bhwOpenNutritionFlagsQuery()
            ->with(['resident.household.purok'])
            ->latest('flagged_at')
            ->limit(6)
            ->get();

        $recentTriage = $this->bhwTriageRecordsQuery()
            ->with(['resident.household.purok', 'consumedBy'])
            ->latest('measured_at')
            ->limit(6)
            ->get();

        $recentDrafts = $this->bhwOwnHouseholdDraftsQuery()
            ->with(['purok', 'reviewedBy'])
            ->latest()
            ->limit(6)
            ->get();

        $recentUpdateRequests = $this->bhwOwnProfileUpdateRequestsQuery()
            ->with(['resident.household.purok', 'household.purok', 'reviewedBy'])
            ->latest()
            ->limit(6)
            ->get();

        return view('bhw.dashboard', [
            'dueTodayAssignments' => $dueTodayAssignments,
            'todayTaskCount' => $this->bhwCampaignAssignmentsQuery()
                ->whereHas('campaign', fn ($query) => $query->whereDate('scheduled_for', $today)->where('campaign_status', 'active'))
                ->count(),
            'pendingTaskCount' => $this->bhwCampaignAssignmentsQuery()
                ->where('assignment_status', CommunityCampaignAssignment::STATUS_PENDING)
                ->count(),
            'openFlagCount' => $this->bhwOpenNutritionFlagsQuery()->count(),
            'openFlags' => $openFlags,
            'triageTodayCount' => $this->bhwTriageRecordsQuery()->whereDate('measured_at', $today)->count(),
            'triagePendingCount' => $this->bhwTriageRecordsQuery()->whereDate('measured_at', $today)->whereNull('consumed_at')->count(),
            'triageConsumedCount' => $this->bhwTriageRecordsQuery()->whereDate('measured_at', $today)->whereNotNull('consumed_at')->count(),
            'recentTriage' => $recentTriage,
            'pendingDraftCount' => $this->bhwOwnHouseholdDraftsQuery()->where('draft_status', HouseholdDraft::STATUS_PENDING)->count(),
            'approvedDraftCount' => $this->bhwOwnHouseholdDraftsQuery()->where('draft_status', HouseholdDraft::STATUS_APPROVED)->count(),
            'rejectedDraftCount' => $this->bhwOwnHouseholdDraftsQuery()->where('draft_status', HouseholdDraft::STATUS_REJECTED)->count(),
            'recentDrafts' => $recentDrafts,
            'pendingUpdateCount' => $this->bhwOwnProfileUpdateRequestsQuery()->where('request_status', ProfileUpdateRequest::STATUS_PENDING)->count(),
            'approvedUpdateCount' => $this->bhwOwnProfileUpdateRequestsQuery()->where('request_status', ProfileUpdateRequest::STATUS_APPROVED)->count(),
            'rejectedUpdateCount' => $this->bhwOwnProfileUpdateRequestsQuery()->where('request_status', ProfileUpdateRequest::STATUS_REJECTED)->count(),
            'recentUpdateRequests' => $recentUpdateRequests,
        ]);
    }
}
