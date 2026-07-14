<?php

namespace App\Http\Controllers\Phn;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Phn\Concerns\InteractsWithPhnScope;
use App\Http\Requests\Phn\StoreClinicalEncounterRequest;
use App\Http\Requests\Phn\UpdateClinicalEncounterRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ClinicalEncounterController extends Controller
{
    use InteractsWithPhnScope;

    public function index(Request $request): View
    {
        $encounters = $this->filteredQuery($request)
            ->with([
                'resident.household.purok.barangay',
                'attendedBy',
                'triageRecord.recordedBy',
            ])
            ->latest('encountered_at')
            ->paginate(15)
            ->withQueryString();

        return view('phn.encounters.index', [
            'encounters' => $encounters,
            'barangays' => $this->phnBarangaysQuery()->active()->orderBy('name')->get(),
            'activeCount' => $this->phnClinicalEncountersQuery()->whereNull('closed_at')->count(),
            'closedTodayCount' => $this->phnClinicalEncountersQuery()->whereDate('closed_at', now()->toDateString())->count(),
            'escalatedCount' => $this->phnClinicalEncountersQuery()->activeEscalations()->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $encounters = $this->filteredQuery($request)
            ->with([
                'resident.household.purok.barangay',
                'attendedBy',
                'triageRecord.recordedBy',
            ])
            ->latest('encountered_at')
            ->get();

        $columns = [
            'Encounter ID' => 'id',
            'Encountered At' => fn (ClinicalEncounter $encounter) => $encounter->encountered_at?->format('Y-m-d H:i:s'),
            'Resident' => fn (ClinicalEncounter $encounter) => $encounter->resident?->formal_name ?? 'Unknown',
            'Barangay' => fn (ClinicalEncounter $encounter) => $encounter->barangay?->name ?? 'N/A',
            'Purok' => fn (ClinicalEncounter $encounter) => $encounter->purok?->display_name ?? 'N/A',
            'Source' => fn (ClinicalEncounter $encounter) => $encounter->encounter_source_label,
            'PHN' => fn (ClinicalEncounter $encounter) => $encounter->attendedBy?->name ?? 'Unknown',
            'Working Impression' => fn (ClinicalEncounter $encounter) => $encounter->working_impression ?: 'N/A',
            'Disposition' => fn (ClinicalEncounter $encounter) => $encounter->disposition ?: 'N/A',
            'Follow-Up Date' => fn (ClinicalEncounter $encounter) => $encounter->follow_up_date?->format('Y-m-d') ?: 'N/A',
            'Follow-Up Status' => fn (ClinicalEncounter $encounter) => $encounter->follow_up_status_label,
            'Escalated to MHO' => fn (ClinicalEncounter $encounter) => $encounter->is_escalated_to_mho ? 'Yes' : 'No',
            'Clinical Status' => fn (ClinicalEncounter $encounter) => $encounter->clinical_status_label,
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => Barangay::query()->find($request->input('barangay_id'))?->name,
            'Status' => $request->input('status'),
            'Source' => $request->input('source'),
        ];

        ExportAudit::log('phn clinical encounters', $format, [
            'model_type' => ClinicalEncounter::class,
            'record_count' => $encounters->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("phn_clinical_encounters_{$timestamp}.csv", $columns, $encounters),
            'xlsx' => TabularExport::xlsx("phn_clinical_encounters_{$timestamp}.xlsx", 'PHN Encounters', $columns, $encounters),
            'pdf' => TabularExport::pdf("phn_clinical_encounters_{$timestamp}.pdf", 'PHN Clinical Encounter Log', $columns, $encounters, $filters),
            default => abort(404),
        };
    }

    public function create(Request $request): View
    {
        $selectedTriage = null;
        $selectedResident = null;

        if ($request->filled('triage_record_id')) {
            $selectedTriage = $this->phnPendingTriageRecordsQuery()
                ->whereDoesntHave('clinicalEncounter')
                ->with(['resident.household.purok.barangay', 'recordedBy.assignedPurok'])
                ->find($request->integer('triage_record_id'));
        }

        if ($selectedTriage) {
            $selectedResident = $selectedTriage->resident?->loadMissing([
                'household.purok.barangay',
                'latestOptMeasurement.campaignPeriod',
            ]);
        } elseif ($request->filled('resident_id')) {
            $selectedResident = $this->phnResidentsQuery()
                ->with(['household.purok.barangay', 'latestOptMeasurement.campaignPeriod'])
                ->find($request->integer('resident_id'));
        }

        return view('phn.encounters.create', [
            'clinicalEncounter' => new ClinicalEncounter([
                'encountered_at' => now(),
            ]),
            'selectedResident' => $selectedResident,
            'selectedTriage' => $selectedTriage,
            'residentOptions' => $this->phnResidentsQuery()
                ->with('household.purok.barangay')
                ->where('resident_status', Resident::STATUS_ACTIVE)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'triageOptions' => $this->phnPendingTriageRecordsQuery()
                ->whereDoesntHave('clinicalEncounter')
                ->with('resident.household.purok.barangay')
                ->latest('measured_at')
                ->get(),
        ]);
    }

    public function store(StoreClinicalEncounterRequest $request): RedirectResponse
    {
        $resident = $this->phnResidentsQuery()
            ->with('household.purok.barangay')
            ->findOrFail($request->integer('resident_id'));

        $triageRecord = $request->filled('triage_record_id')
            ? $this->phnPendingTriageRecordsQuery()->whereDoesntHave('clinicalEncounter')->findOrFail($request->integer('triage_record_id'))
            : null;

        $clinicalEncounter = ClinicalEncounter::query()->create(
            $this->encounterPayload($request, $resident, $triageRecord)
        );

        AuditLog::logMutation('created', Auth::user(), $clinicalEncounter);

        return redirect()
            ->route('phn.encounters.show', $clinicalEncounter)
            ->with('success', $triageRecord
                ? 'Triage record consumed into a PHN clinical encounter.'
                : 'Walk-in clinical encounter logged successfully.');
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

        return view('phn.encounters.show', [
            'clinicalEncounter' => $clinicalEncounter,
            'recentResidentEncounters' => $this->phnClinicalEncountersQuery()
                ->with(['attendedBy', 'mhoReview.reviewedBy'])
                ->where('resident_id', $clinicalEncounter->resident_id)
                ->whereKeyNot($clinicalEncounter->id)
                ->latest('encountered_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function edit(ClinicalEncounter $clinicalEncounter): View
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        $clinicalEncounter->load([
            'resident.household.purok.barangay',
            'resident.latestOptMeasurement.campaignPeriod',
            'triageRecord.recordedBy.assignedPurok',
        ]);

        return view('phn.encounters.edit', [
            'clinicalEncounter' => $clinicalEncounter,
            'selectedResident' => $clinicalEncounter->resident,
            'selectedTriage' => $clinicalEncounter->triageRecord,
        ]);
    }

    public function update(UpdateClinicalEncounterRequest $request, ClinicalEncounter $clinicalEncounter): RedirectResponse
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        $clinicalEncounter->loadMissing('resident.household.purok', 'triageRecord');
        $oldValues = $clinicalEncounter->toArray();

        $clinicalEncounter->update(
            $this->encounterPayload($request, $clinicalEncounter->resident, $clinicalEncounter->triageRecord, $clinicalEncounter)
        );

        AuditLog::logMutation('updated', Auth::user(), $clinicalEncounter, $oldValues, $clinicalEncounter->fresh()->toArray());

        return redirect()
            ->route('phn.encounters.show', $clinicalEncounter)
            ->with('success', 'Clinical encounter updated successfully.');
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

        ExportAudit::log('phn consultation summary', 'pdf', [
            'model_type' => ClinicalEncounter::class,
            'record_count' => 1,
            'record_ids' => [$clinicalEncounter->id],
        ]);

        return Pdf::loadView('documents.clinical.consultation-summary', [
            'clinicalEncounter' => $clinicalEncounter,
            'printedAt' => now(),
        ])->setPaper('a4')->download('phn-consultation-summary-'.$clinicalEncounter->id.'.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->phnClinicalEncountersQuery();

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->integer('barangay_id'));
        }

        if ($request->filled('resident_id')) {
            $query->where('resident_id', $request->integer('resident_id'));
        }

        if ($request->filled('source')) {
            $query->where('encounter_source', $request->input('source'));
        }

        if ($request->filled('status')) {
            match ($request->input('status')) {
                'active' => $query->whereNull('closed_at'),
                'closed' => $query->whereNotNull('closed_at'),
                'escalated' => $query->where('is_escalated_to_mho', true)->whereNull('closed_at'),
                'follow_up_due' => $query->dueFollowUp(),
                'follow_up_missed' => $query->where('follow_up_status', ClinicalEncounter::FOLLOW_UP_MISSED),
                default => null,
            };
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
                    ->orWhere('action_taken', 'like', "%{$search}%")
                    ->orWhere('disposition', 'like', "%{$search}%")
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

    private function encounterPayload(
        Request $request,
        Resident $resident,
        ?TriageRecord $triageRecord,
        ?ClinicalEncounter $existingEncounter = null
    ): array {
        $followUpDate = $request->date('follow_up_date');
        $followUpStatus = $request->filled('follow_up_status')
            ? $request->string('follow_up_status')->toString()
            : null;

        if ($followUpDate && ! $followUpStatus) {
            $followUpStatus = ClinicalEncounter::FOLLOW_UP_DUE;
        }

        $isEscalated = $request->boolean('is_escalated_to_mho');

        return [
            'triage_record_id' => $triageRecord?->id,
            'resident_id' => $resident->id,
            'household_id' => $resident->household_id,
            'barangay_id' => $resident->household->purok->barangay_id,
            'purok_id' => $resident->household->purok_id,
            'attended_by_user_id' => Auth::id(),
            'encounter_source' => $triageRecord ? ClinicalEncounter::SOURCE_TRIAGE : ClinicalEncounter::SOURCE_WALK_IN,
            'encountered_at' => $request->date('encountered_at'),
            'consultation_notes' => $request->input('consultation_notes'),
            'working_impression' => $request->input('working_impression'),
            'action_taken' => $request->input('action_taken'),
            'disposition' => $request->input('disposition'),
            'follow_up_date' => $followUpDate,
            'follow_up_status' => $followUpStatus,
            'follow_up_notes' => $request->input('follow_up_notes'),
            'medicines_administered' => $request->input('medicines_administered'),
            'lifestyle_advice' => $request->input('lifestyle_advice'),
            'referral_notes' => $request->input('referral_notes'),
            'return_instructions' => $request->input('return_instructions'),
            'is_escalated_to_mho' => $isEscalated,
            'escalation_notes' => $request->input('escalation_notes'),
            'escalated_at' => $isEscalated ? ($existingEncounter?->escalated_at ?? now()) : null,
            'closed_at' => $this->resolveClosedAt($followUpStatus, $isEscalated, $existingEncounter?->closed_at),
        ];
    }

    private function resolveClosedAt(?string $followUpStatus, bool $isEscalated, mixed $existingClosedAt): mixed
    {
        if ($isEscalated) {
            return null;
        }

        if (in_array($followUpStatus, [
            ClinicalEncounter::FOLLOW_UP_DUE,
            ClinicalEncounter::FOLLOW_UP_RESCHEDULED,
            ClinicalEncounter::FOLLOW_UP_MISSED,
        ], true)) {
            return null;
        }

        return $existingClosedAt ?? now();
    }
}
