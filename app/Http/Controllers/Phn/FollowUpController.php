<?php

namespace App\Http\Controllers\Phn;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Phn\Concerns\InteractsWithPhnScope;
use App\Http\Requests\Phn\UpdateFollowUpStatusRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    use InteractsWithPhnScope;

    public function index(Request $request): View
    {
        $followUps = $this->filteredQuery($request)
            ->with(['resident.household.purok.barangay', 'attendedBy'])
            ->orderBy('follow_up_date')
            ->latest('encountered_at')
            ->paginate(15)
            ->withQueryString();

        return view('phn.follow-ups.index', [
            'followUps' => $followUps,
            'barangays' => $this->phnBarangaysQuery()->active()->orderBy('name')->get(),
            'dueCount' => $this->phnClinicalEncountersQuery()->dueFollowUp()->count(),
            'missedCount' => $this->phnClinicalEncountersQuery()->where('follow_up_status', ClinicalEncounter::FOLLOW_UP_MISSED)->count(),
            'completedCount' => $this->phnClinicalEncountersQuery()->where('follow_up_status', ClinicalEncounter::FOLLOW_UP_COMPLETED)->count(),
        ]);
    }

    public function update(UpdateFollowUpStatusRequest $request, ClinicalEncounter $clinicalEncounter): RedirectResponse
    {
        $this->ensureClinicalEncounterExists($clinicalEncounter);

        $oldValues = $clinicalEncounter->toArray();
        $status = $request->string('follow_up_status')->toString();

        $clinicalEncounter->update([
            'follow_up_date' => $request->date('follow_up_date') ?? $clinicalEncounter->follow_up_date,
            'follow_up_status' => $status,
            'follow_up_notes' => $request->input('follow_up_notes'),
            'closed_at' => $this->resolveClosedAt($status, (bool) $clinicalEncounter->is_escalated_to_mho, $clinicalEncounter->closed_at),
        ]);

        AuditLog::logMutation('updated', Auth::user(), $clinicalEncounter, $oldValues, $clinicalEncounter->fresh()->toArray());

        return back()->with('success', 'Follow-up status updated successfully.');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->phnFollowUpEncountersQuery();
        $status = $request->string('status')->toString() ?: 'active';

        match ($status) {
            'completed' => $query->where('follow_up_status', ClinicalEncounter::FOLLOW_UP_COMPLETED),
            'missed' => $query->where('follow_up_status', ClinicalEncounter::FOLLOW_UP_MISSED),
            'rescheduled' => $query->where('follow_up_status', ClinicalEncounter::FOLLOW_UP_RESCHEDULED),
            'all' => null,
            default => $query->whereIn('follow_up_status', [
                ClinicalEncounter::FOLLOW_UP_DUE,
                ClinicalEncounter::FOLLOW_UP_RESCHEDULED,
                ClinicalEncounter::FOLLOW_UP_MISSED,
            ]),
        };

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->integer('barangay_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('follow_up_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('follow_up_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('follow_up_notes', 'like', "%{$search}%")
                    ->orWhereHas('resident', function (Builder $residentQuery) use ($search): void {
                        $residentQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('official_resident_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function resolveClosedAt(string $followUpStatus, bool $isEscalated, mixed $existingClosedAt): mixed
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
