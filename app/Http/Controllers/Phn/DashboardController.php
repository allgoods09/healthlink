<?php

namespace App\Http\Controllers\Phn;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Phn\Concerns\InteractsWithPhnScope;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\Resident;
use App\Models\TriageRecord;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithPhnScope;

    public function __invoke(): View
    {
        $today = now()->toDateString();

        $pendingTriages = $this->phnPendingTriageRecordsQuery()
            ->with(['resident.household.purok.barangay', 'recordedBy.assignedPurok'])
            ->latest('measured_at')
            ->limit(6)
            ->get();

        $reviewedToday = $this->phnClinicalEncountersQuery()
            ->with(['resident.household.purok.barangay', 'attendedBy'])
            ->whereDate('encountered_at', $today)
            ->latest('encountered_at')
            ->limit(6)
            ->get();

        $followUpsDue = $this->phnClinicalEncountersQuery()
            ->with(['resident.household.purok.barangay', 'attendedBy'])
            ->dueFollowUp()
            ->orderBy('follow_up_date')
            ->limit(6)
            ->get();

        $workloadBreakdown = $this->phnBarangaysQuery()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay) use ($today): array {
                return [
                    'barangay' => $barangay,
                    'active_residents_count' => Resident::query()
                        ->whereHas('household.purok', fn ($query) => $query->where('barangay_id', $barangay->id))
                        ->where('is_active', true)
                        ->count(),
                    'pending_triage_count' => TriageRecord::query()
                        ->where('barangay_id', $barangay->id)
                        ->where('triage_status', TriageRecord::STATUS_PENDING)
                        ->whereNull('consumed_at')
                        ->count(),
                    'reviewed_today_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->whereDate('encountered_at', $today)
                        ->count(),
                    'follow_up_due_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->whereNotNull('follow_up_date')
                        ->whereIn('follow_up_status', [
                            ClinicalEncounter::FOLLOW_UP_DUE,
                            ClinicalEncounter::FOLLOW_UP_RESCHEDULED,
                        ])
                        ->whereDate('follow_up_date', '<=', $today)
                        ->count(),
                    'active_escalation_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->where('is_escalated_to_mho', true)
                        ->whereNull('closed_at')
                        ->count(),
                ];
            });

        return view('phn.dashboard', [
            'pendingTriageCount' => $this->phnPendingTriageRecordsQuery()->count(),
            'reviewedTodayCount' => $this->phnClinicalEncountersQuery()->whereDate('encountered_at', $today)->count(),
            'followUpsDueCount' => $this->phnClinicalEncountersQuery()->dueFollowUp()->count(),
            'activeEscalationsCount' => $this->phnClinicalEncountersQuery()->activeEscalations()->count(),
            'walkInTodayCount' => $this->phnClinicalEncountersQuery()
                ->where('encounter_source', ClinicalEncounter::SOURCE_WALK_IN)
                ->whereDate('encountered_at', $today)
                ->count(),
            'pendingTriages' => $pendingTriages,
            'reviewedToday' => $reviewedToday,
            'followUpsDue' => $followUpsDue,
            'workloadBreakdown' => $workloadBreakdown,
            'workloadPeak' => $this->resolveWorkloadPeak($workloadBreakdown),
        ]);
    }

    private function resolveWorkloadPeak(Collection $workloadBreakdown): int
    {
        return (int) $workloadBreakdown
            ->map(fn (array $row) => max(
                $row['pending_triage_count'],
                $row['reviewed_today_count'],
                $row['follow_up_due_count'],
                $row['active_escalation_count'],
                1
            ))
            ->max();
    }
}
