<?php

namespace App\Http\Controllers\Bhw\Concerns;

use App\Models\ChildNutritionAssessmentFlag;
use App\Models\CommunityCampaignAssignment;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithBhwScope
{
    protected function bhwUser(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    protected function assignedBarangayId(): int
    {
        return (int) $this->bhwUser()->assigned_barangay_id;
    }

    protected function assignedPurokId(): ?int
    {
        return $this->bhwUser()->assigned_purok_id ? (int) $this->bhwUser()->assigned_purok_id : null;
    }

    protected function bhwPuroksQuery(): Builder
    {
        return Purok::query()
            ->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bhwHouseholdsQuery(): Builder
    {
        return Household::query()->whereHas('purok', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function bhwResidentsQuery(): Builder
    {
        return Resident::query()->whereHas('household.purok', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function bhwEligibleChildrenQuery(): Builder
    {
        return $this->bhwResidentsQuery()
            ->where('resident_status', Resident::STATUS_ACTIVE)
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereIn('sex', ['Male', 'Female'])
            ->whereDate('birth_date', '>', now()->subMonths(72)->startOfDay());
    }

    protected function bhwOwnHouseholdDraftsQuery(): Builder
    {
        return HouseholdDraft::query()
            ->where('submitted_by_user_id', $this->bhwUser()->id)
            ->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bhwOwnProfileUpdateRequestsQuery(): Builder
    {
        return ProfileUpdateRequest::query()
            ->where('submitted_by_user_id', $this->bhwUser()->id)
            ->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bhwTriageRecordsQuery(): Builder
    {
        return TriageRecord::query()
            ->where('recorded_by_user_id', $this->bhwUser()->id)
            ->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bhwOpenNutritionFlagsQuery(): Builder
    {
        return ChildNutritionAssessmentFlag::query()
            ->where('flagged_by_user_id', $this->bhwUser()->id)
            ->where('barangay_id', $this->assignedBarangayId())
            ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN);
    }

    protected function bhwCampaignAssignmentsQuery(): Builder
    {
        return CommunityCampaignAssignment::query()
            ->where('assigned_bhw_user_id', $this->bhwUser()->id)
            ->whereHas('campaign', function (Builder $query): void {
                $query->where('barangay_id', $this->assignedBarangayId());
            });
    }

    protected function ensurePurokBelongsToBarangay(Purok $purok): void
    {
        if ((int) $purok->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureResidentBelongsToBarangay(Resident $resident): void
    {
        $resident->loadMissing('household.purok');

        if ((int) $resident->household?->purok?->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureHouseholdBelongsToBarangay(Household $household): void
    {
        $household->loadMissing('purok');

        if ((int) $household->purok?->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureDraftBelongsToBhw(HouseholdDraft $householdDraft): void
    {
        if (
            (int) $householdDraft->barangay_id !== $this->assignedBarangayId()
            || (int) $householdDraft->submitted_by_user_id !== (int) $this->bhwUser()->id
        ) {
            abort(404);
        }
    }

    protected function ensureProfileUpdateRequestBelongsToBhw(ProfileUpdateRequest $profileUpdateRequest): void
    {
        if (
            (int) $profileUpdateRequest->barangay_id !== $this->assignedBarangayId()
            || (int) $profileUpdateRequest->submitted_by_user_id !== (int) $this->bhwUser()->id
        ) {
            abort(404);
        }
    }

    protected function ensureTriageRecordBelongsToBhw(TriageRecord $triageRecord): void
    {
        if (
            (int) $triageRecord->barangay_id !== $this->assignedBarangayId()
            || (int) $triageRecord->recorded_by_user_id !== (int) $this->bhwUser()->id
        ) {
            abort(404);
        }
    }

    protected function ensureCampaignAssignmentBelongsToBhw(CommunityCampaignAssignment $assignment): void
    {
        $assignment->loadMissing('campaign');

        if (
            (int) $assignment->campaign?->barangay_id !== $this->assignedBarangayId()
            || (int) $assignment->assigned_bhw_user_id !== (int) $this->bhwUser()->id
        ) {
            abort(404);
        }
    }

    protected function triageIsEditable(TriageRecord $triageRecord): bool
    {
        return is_null($triageRecord->consumed_at) && is_null($triageRecord->consumed_by_user_id);
    }
}
