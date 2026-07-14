<?php

namespace App\Http\Controllers\Admin\Oversight;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\MhoClinicalReview;
use App\Models\TriageRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ClinicalOversightController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = now()->toDateString();
        $barangayId = $request->integer('barangay_id');

        $triageQuery = TriageRecord::query()
            ->with(['resident.household.purok.barangay', 'recordedBy', 'consumedBy'])
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $encounterQuery = ClinicalEncounter::query()
            ->with(['resident.household.purok.barangay', 'attendedBy', 'mhoReview.reviewedBy'])
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $barangayBreakdown = Barangay::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay) use ($today): array {
                return [
                    'barangay' => $barangay,
                    'pending_triage_count' => TriageRecord::query()
                        ->where('barangay_id', $barangay->id)
                        ->pending()
                        ->whereNull('consumed_at')
                        ->count(),
                    'consumed_today_count' => TriageRecord::query()
                        ->where('barangay_id', $barangay->id)
                        ->whereDate('consumed_at', $today)
                        ->count(),
                    'phn_reviewed_today_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->whereDate('encountered_at', $today)
                        ->count(),
                    'due_follow_up_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->dueFollowUp()
                        ->count(),
                    'active_escalation_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->activeEscalations()
                        ->count(),
                ];
            });

        return view('admin.oversight.clinical', [
            'barangays' => Barangay::query()->active()->orderBy('name')->get(),
            'selectedBarangay' => $barangayId ? Barangay::find($barangayId) : null,
            'pendingTriageCount' => (clone $triageQuery)->pending()->whereNull('consumed_at')->count(),
            'triageConsumedTodayCount' => (clone $triageQuery)->whereDate('consumed_at', $today)->count(),
            'phnReviewedTodayCount' => (clone $encounterQuery)->whereDate('encountered_at', $today)->count(),
            'overdueFollowUpCount' => (clone $encounterQuery)->dueFollowUp()->count(),
            'activeEscalationCount' => (clone $encounterQuery)->activeEscalations()->count(),
            'mhoReviewedTodayCount' => MhoClinicalReview::query()
                ->when($barangayId, function ($query) use ($barangayId): void {
                    $query->whereHas('clinicalEncounter', fn ($encounterQuery) => $encounterQuery->where('barangay_id', $barangayId));
                })
                ->whereDate('reviewed_at', $today)
                ->count(),
            'closedTodayCount' => (clone $encounterQuery)->reviewedByMho()->whereDate('closed_at', $today)->count(),
            'pendingTriages' => (clone $triageQuery)
                ->pending()
                ->whereNull('consumed_at')
                ->latest('measured_at')
                ->limit(10)
                ->get(),
            'dueFollowUps' => (clone $encounterQuery)
                ->dueFollowUp()
                ->orderBy('follow_up_date')
                ->limit(10)
                ->get(),
            'activeEscalations' => (clone $encounterQuery)
                ->activeEscalations()
                ->latest('escalated_at')
                ->limit(10)
                ->get(),
            'recentMhoReviews' => MhoClinicalReview::query()
                ->with(['clinicalEncounter.resident.household.purok.barangay', 'reviewedBy'])
                ->when($barangayId, function ($query) use ($barangayId): void {
                    $query->whereHas('clinicalEncounter', fn ($encounterQuery) => $encounterQuery->where('barangay_id', $barangayId));
                })
                ->latest('reviewed_at')
                ->limit(8)
                ->get(),
            'barangayBreakdown' => $barangayBreakdown,
            'breakdownPeak' => $this->resolveBreakdownPeak($barangayBreakdown),
        ]);
    }

    private function resolveBreakdownPeak(Collection $rows): int
    {
        return (int) $rows
            ->map(fn (array $row) => max(
                $row['pending_triage_count'],
                $row['consumed_today_count'],
                $row['phn_reviewed_today_count'],
                $row['due_follow_up_count'],
                $row['active_escalation_count'],
                1
            ))
            ->max();
    }
}
