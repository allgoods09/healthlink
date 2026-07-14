<?php

namespace App\Http\Controllers\Mho;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mho\Concerns\InteractsWithMhoScope;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EscalationController extends Controller
{
    use InteractsWithMhoScope;

    public function index(Request $request): View
    {
        $encounters = $this->filteredQuery($request)
            ->with([
                'resident.household.purok.barangay',
                'attendedBy',
                'mhoReview.reviewedBy',
                'triageRecord.recordedBy',
            ])
            ->latest('escalated_at')
            ->latest('encountered_at')
            ->paginate(15)
            ->withQueryString();

        return view('mho.escalations.index', [
            'encounters' => $encounters,
            'barangays' => $this->mhoBarangaysQuery()->active()->orderBy('name')->get(),
            'pendingCount' => $this->mhoPendingEscalationsQuery()->count(),
            'reviewedCount' => $this->mhoClinicalEncountersQuery()->where('is_escalated_to_mho', true)->reviewedByMho()->count(),
            'openFollowUpCount' => $this->mhoClinicalEncountersQuery()->reviewedByMho()->dueFollowUp()->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $encounters = $this->filteredQuery($request)
            ->with([
                'resident.household.purok.barangay',
                'attendedBy',
                'mhoReview.reviewedBy',
                'triageRecord.recordedBy',
            ])
            ->latest('escalated_at')
            ->latest('encountered_at')
            ->get();

        $columns = [
            'Encounter ID' => 'id',
            'Resident' => fn (ClinicalEncounter $encounter) => $encounter->resident?->formal_name ?? 'Unknown',
            'Barangay' => fn (ClinicalEncounter $encounter) => $encounter->barangay?->name ?? 'N/A',
            'Purok' => fn (ClinicalEncounter $encounter) => $encounter->purok?->display_name ?? 'N/A',
            'Escalated At' => fn (ClinicalEncounter $encounter) => $encounter->escalated_at?->format('Y-m-d H:i:s') ?: 'N/A',
            'PHN' => fn (ClinicalEncounter $encounter) => $encounter->attendedBy?->name ?? 'Unknown',
            'Clinical Status' => fn (ClinicalEncounter $encounter) => $encounter->clinical_status_label,
            'MHO Reviewed At' => fn (ClinicalEncounter $encounter) => $encounter->mhoReview?->reviewed_at?->format('Y-m-d H:i:s') ?: 'Pending',
            'MHO Reviewer' => fn (ClinicalEncounter $encounter) => $encounter->mhoReview?->reviewedBy?->name ?? 'Pending',
            'Final Disposition' => fn (ClinicalEncounter $encounter) => $encounter->mhoReview?->final_disposition ?: 'Pending',
            'Follow-Up Status' => fn (ClinicalEncounter $encounter) => $encounter->follow_up_status_label,
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => Barangay::query()->find($request->input('barangay_id'))?->name,
            'Status' => $request->input('status', 'pending'),
        ];

        ExportAudit::log('mho escalation queue', $format, [
            'model_type' => ClinicalEncounter::class,
            'record_count' => $encounters->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("mho_escalations_{$timestamp}.csv", $columns, $encounters),
            'xlsx' => TabularExport::xlsx("mho_escalations_{$timestamp}.xlsx", 'MHO Escalations', $columns, $encounters),
            'pdf' => TabularExport::pdf("mho_escalations_{$timestamp}.pdf", 'MHO Escalation Queue', $columns, $encounters, $filters),
            default => abort(404),
        };
    }

    public function show(ClinicalEncounter $clinicalEncounter): View
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        $clinicalEncounter->load([
            'resident.household.headResident',
            'resident.household.purok.barangay',
            'resident.latestOptMeasurement.campaignPeriod',
            'resident.nutritionFlags' => fn ($query) => $query->with(['flaggedBy', 'closedBy', 'resolvedMeasurement'])->latest('flagged_at')->limit(5),
            'triageRecord.recordedBy.assignedPurok',
            'attendedBy',
            'mhoReview.reviewedBy',
        ]);

        return view('mho.escalations.show', [
            'clinicalEncounter' => $clinicalEncounter,
            'recentResidentEncounters' => $this->mhoClinicalEncountersQuery()
                ->with(['attendedBy', 'mhoReview.reviewedBy'])
                ->where('resident_id', $clinicalEncounter->resident_id)
                ->whereKeyNot($clinicalEncounter->id)
                ->latest('encountered_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function pdf(ClinicalEncounter $clinicalEncounter): Response
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        $clinicalEncounter->load([
            'resident.household.headResident',
            'resident.household.purok.barangay',
            'resident.latestOptMeasurement.campaignPeriod',
            'triageRecord.recordedBy.assignedPurok',
            'attendedBy',
            'mhoReview.reviewedBy',
        ]);

        ExportAudit::log('mho consultation summary', 'pdf', [
            'model_type' => ClinicalEncounter::class,
            'record_count' => 1,
            'record_ids' => [$clinicalEncounter->id],
        ]);

        return Pdf::loadView('documents.clinical.consultation-summary', [
            'clinicalEncounter' => $clinicalEncounter,
            'printedAt' => now(),
        ])->setPaper('a4')->download('mho-consultation-summary-'.$clinicalEncounter->id.'.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->mhoClinicalEncountersQuery()->where('is_escalated_to_mho', true);
        $status = $request->string('status')->toString() ?: 'pending';

        match ($status) {
            'reviewed' => $query->reviewedByMho(),
            'follow_up' => $query->reviewedByMho()->dueFollowUp(),
            'closed' => $query->reviewedByMho()->whereNotNull('closed_at'),
            'all' => null,
            default => $query->activeEscalations(),
        };

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->integer('barangay_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('encountered_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('encountered_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('consultation_notes', 'like', "%{$search}%")
                    ->orWhere('working_impression', 'like', "%{$search}%")
                    ->orWhere('escalation_notes', 'like', "%{$search}%")
                    ->orWhereHas('resident', function (Builder $residentQuery) use ($search): void {
                        $residentQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('official_resident_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('household', function (Builder $householdQuery) use ($search): void {
                        $householdQuery->where('household_no', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
