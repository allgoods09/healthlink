<?php

namespace App\Http\Controllers\Mho;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mho\Concerns\InteractsWithMhoScope;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\MhoClinicalReview;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithMhoScope;

    public function __invoke(): View
    {
        $today = now()->toDateString();

        $pendingEscalations = $this->mhoPendingEscalationsQuery()
            ->with(['resident.household.purok.barangay', 'attendedBy'])
            ->latest('escalated_at')
            ->limit(6)
            ->get();

        $reviewedToday = $this->mhoClinicalReviewsQuery()
            ->with(['clinicalEncounter.resident.household.purok.barangay', 'reviewedBy'])
            ->whereDate('reviewed_at', $today)
            ->latest('reviewed_at')
            ->limit(6)
            ->get();

        $workloadBreakdown = $this->mhoBarangaysQuery()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay) use ($today): array {
                return [
                    'barangay' => $barangay,
                    'pending_escalation_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->activeEscalations()
                        ->count(),
                    'reviewed_today_count' => MhoClinicalReview::query()
                        ->whereDate('reviewed_at', $today)
                        ->whereHas('clinicalEncounter', fn ($query) => $query->where('barangay_id', $barangay->id))
                        ->count(),
                    'open_follow_up_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->reviewedByMho()
                        ->dueFollowUp()
                        ->count(),
                    'closed_today_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->reviewedByMho()
                        ->whereDate('closed_at', $today)
                        ->count(),
                ];
            });

        return view('mho.dashboard', [
            'pendingEscalationCount' => $this->mhoPendingEscalationsQuery()->count(),
            'reviewedTodayCount' => $this->mhoClinicalReviewsQuery()->whereDate('reviewed_at', $today)->count(),
            'closedTodayCount' => $this->mhoClinicalEncountersQuery()->reviewedByMho()->whereDate('closed_at', $today)->count(),
            'openFollowUpCount' => $this->mhoClinicalEncountersQuery()->reviewedByMho()->dueFollowUp()->count(),
            'referralCount' => $this->mhoClinicalReviewsQuery()->whereNotNull('referral_destination')->count(),
            'pendingEscalations' => $pendingEscalations,
            'reviewedToday' => $reviewedToday,
            'workloadBreakdown' => $workloadBreakdown,
            'workloadPeak' => $this->resolveWorkloadPeak($workloadBreakdown),
        ]);
    }

    private function resolveWorkloadPeak(Collection $workloadBreakdown): int
    {
        return (int) $workloadBreakdown
            ->map(fn (array $row) => max(
                $row['pending_escalation_count'],
                $row['reviewed_today_count'],
                $row['open_follow_up_count'],
                $row['closed_today_count'],
                1
            ))
            ->max();
    }
}
