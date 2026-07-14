<?php

namespace App\Http\Controllers\Bns\Concerns;

use App\Models\ChildNutritionAssessmentFlag;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramEnrollment;
use App\Models\Household;
use App\Models\MaternalNutritionProfile;
use App\Models\MicronutrientSupplementationLog;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithBnsScope
{
    protected function bnsUser(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    protected function assignedBarangayId(): int
    {
        return (int) $this->bnsUser()->assigned_barangay_id;
    }

    protected function bnsPuroksQuery(): Builder
    {
        return Purok::query()
            ->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bnsHouseholdsQuery(): Builder
    {
        return Household::query()->whereHas('purok', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function bnsResidentsQuery(): Builder
    {
        return Resident::query()->whereHas('household.purok', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function bnsEligibleChildrenQuery(): Builder
    {
        return $this->bnsResidentsQuery()
            ->where('resident_status', Resident::STATUS_ACTIVE)
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereIn('sex', ['Male', 'Female'])
            ->whereDate('birth_date', '>=', now()->subMonths(72)->startOfDay());
    }

    protected function bnsOptEligibleChildrenQuery(): Builder
    {
        return $this->bnsResidentsQuery()
            ->where('resident_status', Resident::STATUS_ACTIVE)
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereIn('sex', ['Male', 'Female'])
            ->whereDate('birth_date', '>', now()->subMonths(60)->startOfDay());
    }

    protected function bnsCampaignPeriodsQuery(): Builder
    {
        return NutritionCampaignPeriod::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bnsOptMeasurementsQuery(): Builder
    {
        return OptMeasurement::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bnsOpenAssessmentFlagsQuery(): Builder
    {
        return ChildNutritionAssessmentFlag::query()
            ->where('barangay_id', $this->assignedBarangayId())
            ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN);
    }

    protected function bnsMaternalProfilesQuery(): Builder
    {
        return MaternalNutritionProfile::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bnsFeedingProgramsQuery(): Builder
    {
        return FeedingProgram::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function bnsFeedingProgramEnrollmentsQuery(): Builder
    {
        return FeedingProgramEnrollment::query()->whereHas('feedingProgram', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function bnsFeedingEligibleChildrenQuery(): Builder
    {
        return $this->bnsResidentsQuery()
            ->where('resident_status', Resident::STATUS_ACTIVE)
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereIn('sex', ['Male', 'Female'])
            ->whereDate('birth_date', '>', now()->subMonths(72)->startOfDay());
    }

    protected function bnsMaternalEligibleResidentsQuery(): Builder
    {
        return $this->bnsResidentsQuery()
            ->where('resident_status', Resident::STATUS_ACTIVE)
            ->where('is_active', true)
            ->where('sex', 'Female');
    }

    protected function bnsInfantEligibleResidentsQuery(): Builder
    {
        return $this->bnsResidentsQuery()
            ->where('resident_status', Resident::STATUS_ACTIVE)
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereDate('birth_date', '>', now()->subMonths(24)->startOfDay());
    }

    protected function bnsMicronutrientLogsQuery(): Builder
    {
        return MicronutrientSupplementationLog::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function latestOptMeasurementIdsSubquery(?int $campaignPeriodId = null): Builder
    {
        $query = $this->bnsOptMeasurementsQuery()
            ->selectRaw('MAX(id) as id')
            ->when($campaignPeriodId, function ($measurementQuery) use ($campaignPeriodId): void {
                $measurementQuery->where('campaign_period_id', $campaignPeriodId);
            })
            ->whereRaw(
                'measurement_date = (
                    select max(m2.measurement_date)
                    from opt_measurements as m2
                    where m2.resident_id = opt_measurements.resident_id
                      and m2.barangay_id = opt_measurements.barangay_id'
                . ($campaignPeriodId ? ' and m2.campaign_period_id = ?' : '')
                . '
                )',
                $campaignPeriodId ? [$campaignPeriodId] : []
            )
            ->groupBy('resident_id');

        return $query;
    }

    protected function ensureCampaignPeriodBelongsToBarangay(NutritionCampaignPeriod $campaignPeriod): void
    {
        if ((int) $campaignPeriod->barangay_id !== $this->assignedBarangayId()) {
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

    protected function ensureOptMeasurementBelongsToBarangay(OptMeasurement $optMeasurement): void
    {
        if ((int) $optMeasurement->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureAssessmentFlagBelongsToBarangay(ChildNutritionAssessmentFlag $flag): void
    {
        if ((int) $flag->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureFeedingProgramBelongsToBarangay(FeedingProgram $feedingProgram): void
    {
        if ((int) $feedingProgram->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureFeedingProgramEnrollmentBelongsToBarangay(FeedingProgramEnrollment $enrollment): void
    {
        $enrollment->loadMissing('feedingProgram');

        if ((int) $enrollment->feedingProgram?->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }
}
