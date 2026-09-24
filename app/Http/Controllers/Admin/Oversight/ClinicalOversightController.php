<?php

namespace App\Http\Controllers\Admin\Oversight;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\MhoClinicalReview;
use App\Models\TriageRecord;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ClinicalOversightController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = now()->toDateString();
        $barangayId = $request->integer('barangay_id');

        $triageQuery = $this->triageQuery($barangayId);
        $encounterQuery = $this->encounterQuery($barangayId);

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
            'pendingTriages' => $this->panelQuery($barangayId, 'pending-triage')->limit(10)->get(),
            'dueFollowUps' => $this->panelQuery($barangayId, 'due-follow-ups')->limit(10)->get(),
            'activeEscalations' => $this->panelQuery($barangayId, 'active-escalations')->limit(10)->get(),
            'recentMhoReviews' => $this->panelQuery($barangayId, 'mho-reviews')->limit(8)->get(),
            'barangayBreakdown' => $barangayBreakdown,
            'breakdownPeak' => $this->resolveBreakdownPeak($barangayBreakdown),
        ]);
    }

    public function export(Request $request, string $dataset, string $format): Response
    {
        $definitions = [
            'pending-triage' => [
                'title' => 'Pending Triage Queue',
                'model' => TriageRecord::class,
                'columns' => [
                    'Resident' => fn (TriageRecord $item) => $item->resident?->formal_name,
                    'Barangay' => fn (TriageRecord $item) => $item->resident?->household?->purok?->barangay?->name,
                    'Measured At' => fn (TriageRecord $item) => $item->measured_at?->format('Y-m-d H:i:s'),
                    'Recorded By' => fn (TriageRecord $item) => $item->recordedBy?->name,
                    'Notes' => 'triage_notes',
                ],
            ],
            'due-follow-ups' => [
                'title' => 'Overdue Follow-Ups',
                'model' => ClinicalEncounter::class,
                'columns' => [
                    'Resident' => fn (ClinicalEncounter $item) => $item->resident?->formal_name,
                    'Barangay' => fn (ClinicalEncounter $item) => $item->resident?->household?->purok?->barangay?->name,
                    'Follow-Up Date' => fn (ClinicalEncounter $item) => $item->follow_up_date?->format('Y-m-d'),
                    'Status' => fn (ClinicalEncounter $item) => $item->follow_up_status_label,
                    'PHN' => fn (ClinicalEncounter $item) => $item->attendedBy?->name,
                    'Notes' => 'follow_up_notes',
                ],
            ],
            'active-escalations' => [
                'title' => 'Active Escalations',
                'model' => ClinicalEncounter::class,
                'columns' => [
                    'Resident' => fn (ClinicalEncounter $item) => $item->resident?->formal_name,
                    'Barangay' => fn (ClinicalEncounter $item) => $item->resident?->household?->purok?->barangay?->name,
                    'Escalated At' => fn (ClinicalEncounter $item) => $item->escalated_at?->format('Y-m-d H:i:s'),
                    'PHN' => fn (ClinicalEncounter $item) => $item->attendedBy?->name,
                    'Clinical Status' => fn (ClinicalEncounter $item) => $item->clinical_status_label,
                    'Escalation Notes' => 'escalation_notes',
                ],
            ],
            'mho-reviews' => [
                'title' => 'Recent MHO Reviews',
                'model' => MhoClinicalReview::class,
                'columns' => [
                    'Resident' => fn (MhoClinicalReview $item) => $item->clinicalEncounter?->resident?->formal_name,
                    'Barangay' => fn (MhoClinicalReview $item) => $item->clinicalEncounter?->resident?->household?->purok?->barangay?->name,
                    'Reviewed By' => fn (MhoClinicalReview $item) => $item->reviewedBy?->name,
                    'Reviewed At' => fn (MhoClinicalReview $item) => $item->reviewed_at?->format('Y-m-d H:i:s'),
                    'Final Assessment' => 'final_assessment',
                    'Final Disposition' => 'final_disposition',
                ],
            ],
        ];

        abort_unless(isset($definitions[$dataset]), 404);
        $definition = $definitions[$dataset];
        $barangayId = $request->integer('barangay_id');
        $barangayName = $barangayId ? Barangay::findOrFail($barangayId)->name : 'Municipality-wide';

        return ExportDownload::make($format, $definition['title'], 'Clinical Oversight', 'clinical_'.$dataset, $definition['columns'], $this->panelQuery($barangayId, $dataset)->get(), ['Barangay' => $barangayId ? $barangayName : null], $barangayName, $definition['model']);
    }

    private function triageQuery(int $barangayId): Builder
    {
        return TriageRecord::query()
            ->with(['resident.household.purok.barangay', 'recordedBy', 'consumedBy'])
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function encounterQuery(int $barangayId): Builder
    {
        return ClinicalEncounter::query()
            ->with(['resident.household.purok.barangay', 'attendedBy', 'mhoReview.reviewedBy'])
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function reviewsQuery(int $barangayId): Builder
    {
        return MhoClinicalReview::query()
            ->with(['clinicalEncounter.resident.household.purok.barangay', 'reviewedBy'])
            ->when($barangayId, function (Builder $query) use ($barangayId): void {
                $query->whereHas('clinicalEncounter', fn (Builder $encounterQuery) => $encounterQuery->where('barangay_id', $barangayId));
            });
    }

    private function panelQuery(int $barangayId, string $dataset): Builder
    {
        return match ($dataset) {
            'pending-triage' => $this->triageQuery($barangayId)->pending()->whereNull('consumed_at')->latest('measured_at')->latest('id'),
            'due-follow-ups' => $this->encounterQuery($barangayId)->dueFollowUp()->orderBy('follow_up_date')->orderBy('id'),
            'active-escalations' => $this->encounterQuery($barangayId)->activeEscalations()->latest('escalated_at')->latest('id'),
            'mho-reviews' => $this->reviewsQuery($barangayId)->latest('reviewed_at')->latest('id'),
            default => abort(404),
        };
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
