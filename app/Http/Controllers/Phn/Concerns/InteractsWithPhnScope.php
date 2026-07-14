<?php

namespace App\Http\Controllers\Phn\Concerns;

use App\Models\ClinicalEncounter;
use App\Models\FieldVisit;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithPhnScope
{
    protected function phnUser(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    protected function phnResidentsQuery(): Builder
    {
        return Resident::query();
    }

    protected function phnBarangaysQuery(): Builder
    {
        return Barangay::query();
    }

    protected function phnHouseholdsQuery(): Builder
    {
        return Household::query();
    }

    protected function phnPuroksQuery(): Builder
    {
        return Purok::query();
    }

    protected function phnTriageRecordsQuery(): Builder
    {
        return TriageRecord::query();
    }

    protected function phnPendingTriageRecordsQuery(): Builder
    {
        return $this->phnTriageRecordsQuery()
            ->where('triage_status', TriageRecord::STATUS_PENDING)
            ->whereNull('consumed_at');
    }

    protected function phnClinicalEncountersQuery(): Builder
    {
        return ClinicalEncounter::query();
    }

    protected function phnFollowUpEncountersQuery(): Builder
    {
        return $this->phnClinicalEncountersQuery()->whereNotNull('follow_up_date');
    }

    protected function phnFieldVisitsQuery(): Builder
    {
        return FieldVisit::query();
    }

    protected function phnOwnProfileUpdateRequestsQuery(): Builder
    {
        return ProfileUpdateRequest::query()->where('submitted_by_user_id', $this->phnUser()->id);
    }

    protected function ensureResidentExists(Resident $resident): void
    {
        if (! $resident->exists) {
            abort(404);
        }
    }

    protected function ensureHouseholdExists(Household $household): void
    {
        if (! $household->exists) {
            abort(404);
        }
    }

    protected function ensureTriageRecordExists(TriageRecord $triageRecord): void
    {
        if (! $triageRecord->exists) {
            abort(404);
        }
    }

    protected function ensureClinicalEncounterExists(ClinicalEncounter $clinicalEncounter): void
    {
        if (! $clinicalEncounter->exists) {
            abort(404);
        }
    }

    protected function ensureProfileUpdateRequestBelongsToPhn(ProfileUpdateRequest $profileUpdateRequest): void
    {
        if ((int) $profileUpdateRequest->submitted_by_user_id !== (int) $this->phnUser()->id) {
            abort(404);
        }
    }
}
