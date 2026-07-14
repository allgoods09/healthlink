<?php

namespace App\Http\Controllers\Mho;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mho\Concerns\InteractsWithMhoScope;
use App\Http\Requests\Mho\StoreClinicalReviewRequest;
use App\Http\Requests\Mho\UpdateClinicalReviewRequest;
use App\Models\AuditLog;
use App\Models\ClinicalEncounter;
use App\Models\MhoClinicalReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClinicalReviewController extends Controller
{
    use InteractsWithMhoScope;

    public function create(ClinicalEncounter $clinicalEncounter): View
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        if (! $clinicalEncounter->is_escalated_to_mho) {
            abort(404);
        }

        if ($clinicalEncounter->mhoReview()->exists()) {
            return redirect()->route('mho.reviews.edit', $clinicalEncounter);
        }

        $clinicalEncounter->load([
            'resident.household.purok.barangay',
            'resident.latestOptMeasurement.campaignPeriod',
            'triageRecord.recordedBy.assignedPurok',
            'attendedBy',
        ]);

        return view('mho.reviews.create', [
            'clinicalEncounter' => $clinicalEncounter,
            'mhoReview' => new MhoClinicalReview([
                'reviewed_at' => now(),
            ]),
        ]);
    }

    public function store(StoreClinicalReviewRequest $request, ClinicalEncounter $clinicalEncounter): RedirectResponse
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        if (! $clinicalEncounter->is_escalated_to_mho || $clinicalEncounter->mhoReview()->exists()) {
            abort(404);
        }

        DB::transaction(function () use ($request, $clinicalEncounter): void {
            $oldEncounterValues = $clinicalEncounter->toArray();

            $review = MhoClinicalReview::query()->create($this->reviewPayload($request, $clinicalEncounter));
            $this->syncEncounterAfterReview($request, $clinicalEncounter);

            AuditLog::logMutation('created', Auth::user(), $review);
            AuditLog::logMutation('updated', Auth::user(), $clinicalEncounter, $oldEncounterValues, $clinicalEncounter->fresh()->toArray());
        });

        return redirect()
            ->route('mho.escalations.show', $clinicalEncounter)
            ->with('success', 'Municipal clinical review recorded successfully.');
    }

    public function edit(ClinicalEncounter $clinicalEncounter): View
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        $clinicalEncounter->load([
            'resident.household.purok.barangay',
            'resident.latestOptMeasurement.campaignPeriod',
            'triageRecord.recordedBy.assignedPurok',
            'attendedBy',
            'mhoReview.reviewedBy',
        ]);

        $mhoReview = $clinicalEncounter->mhoReview;

        if (! $mhoReview) {
            return redirect()->route('mho.reviews.create', $clinicalEncounter);
        }

        return view('mho.reviews.edit', [
            'clinicalEncounter' => $clinicalEncounter,
            'mhoReview' => $mhoReview,
        ]);
    }

    public function update(UpdateClinicalReviewRequest $request, ClinicalEncounter $clinicalEncounter): RedirectResponse
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        $mhoReview = $clinicalEncounter->mhoReview;

        if (! $mhoReview) {
            abort(404);
        }

        DB::transaction(function () use ($request, $clinicalEncounter, $mhoReview): void {
            $oldEncounterValues = $clinicalEncounter->toArray();
            $oldReviewValues = $mhoReview->toArray();

            $mhoReview->update($this->reviewPayload($request, $clinicalEncounter));
            $this->syncEncounterAfterReview($request, $clinicalEncounter);

            AuditLog::logMutation('updated', Auth::user(), $mhoReview, $oldReviewValues, $mhoReview->fresh()->toArray());
            AuditLog::logMutation('updated', Auth::user(), $clinicalEncounter, $oldEncounterValues, $clinicalEncounter->fresh()->toArray());
        });

        return redirect()
            ->route('mho.escalations.show', $clinicalEncounter)
            ->with('success', 'Municipal clinical review updated successfully.');
    }

    private function reviewPayload(StoreClinicalReviewRequest|UpdateClinicalReviewRequest $request, ClinicalEncounter $clinicalEncounter): array
    {
        return [
            'clinical_encounter_id' => $clinicalEncounter->id,
            'reviewed_by_user_id' => Auth::id(),
            'reviewed_at' => $request->date('reviewed_at'),
            'final_assessment' => $request->input('final_assessment'),
            'diagnostic_override' => $request->input('diagnostic_override'),
            'prescription_notes' => $request->input('prescription_notes'),
            'referral_destination' => $request->input('referral_destination'),
            'final_disposition' => $request->input('final_disposition'),
            'return_instructions' => $request->input('return_instructions'),
            'resolution_notes' => $request->input('resolution_notes'),
        ];
    }

    private function syncEncounterAfterReview(StoreClinicalReviewRequest|UpdateClinicalReviewRequest $request, ClinicalEncounter $clinicalEncounter): void
    {
        $followUpDate = $request->date('follow_up_date');
        $followUpStatus = $request->filled('follow_up_status')
            ? $request->string('follow_up_status')->toString()
            : null;

        if ($followUpDate && ! $followUpStatus) {
            $followUpStatus = ClinicalEncounter::FOLLOW_UP_DUE;
        }

        $clinicalEncounter->update([
            'follow_up_date' => $followUpDate,
            'follow_up_status' => $followUpStatus,
            'follow_up_notes' => $request->input('follow_up_notes'),
            'closed_at' => $this->resolveClosedAt($followUpStatus, $clinicalEncounter->closed_at),
        ]);
    }

    private function resolveClosedAt(?string $followUpStatus, mixed $existingClosedAt): mixed
    {
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
