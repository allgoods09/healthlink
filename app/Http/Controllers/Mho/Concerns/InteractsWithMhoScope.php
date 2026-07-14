<?php

namespace App\Http\Controllers\Mho\Concerns;

use App\Models\Barangay;
use App\Models\ClinicalEncounter;
use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\MhoClinicalReview;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithMhoScope
{
    protected function mhoUser(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    protected function mhoBarangaysQuery(): Builder
    {
        return Barangay::query();
    }

    protected function mhoResidentsQuery(): Builder
    {
        return Resident::query();
    }

    protected function mhoHouseholdsQuery(): Builder
    {
        return Household::query();
    }

    protected function mhoPuroksQuery(): Builder
    {
        return Purok::query();
    }

    protected function mhoTriageRecordsQuery(): Builder
    {
        return TriageRecord::query();
    }

    protected function mhoClinicalEncountersQuery(): Builder
    {
        return ClinicalEncounter::query();
    }

    protected function mhoPendingEscalationsQuery(): Builder
    {
        return $this->mhoClinicalEncountersQuery()->activeEscalations();
    }

    protected function mhoReviewedEncountersQuery(): Builder
    {
        return $this->mhoClinicalEncountersQuery()->reviewedByMho();
    }

    protected function mhoClinicalReviewsQuery(): Builder
    {
        return MhoClinicalReview::query();
    }

    protected function mhoFieldVisitsQuery(): Builder
    {
        return FieldVisit::query();
    }

    protected function mhoProfileUpdateRequestsQuery(): Builder
    {
        return ProfileUpdateRequest::query();
    }

    protected function ensureClinicalEncounterExists(ClinicalEncounter $clinicalEncounter): void
    {
        if (! $clinicalEncounter->exists) {
            abort(404);
        }
    }

    protected function ensureMhoReviewExists(MhoClinicalReview $mhoClinicalReview): void
    {
        if (! $mhoClinicalReview->exists) {
            abort(404);
        }
    }

    protected function ensureResidentExists(Resident $resident): void
    {
        if (! $resident->exists) {
            abort(404);
        }
    }
}
