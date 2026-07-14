<?php

namespace App\Http\Controllers\Admin\Oversight;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramEnrollment;
use App\Models\MaternalNutritionProfile;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NutritionOversightController extends Controller
{
    public function __invoke(Request $request): View
    {
        $barangayId = $request->integer('barangay_id');

        $campaignsQuery = NutritionCampaignPeriod::query()
            ->with(['barangay', 'createdBy'])
            ->withCount(['optMeasurements', 'feedingPrograms'])
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $openFlagsQuery = ChildNutritionAssessmentFlag::query()
            ->with(['barangay', 'purok', 'resident.household.purok', 'flaggedBy'])
            ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $feedingProgramsQuery = FeedingProgram::query()
            ->with(['barangay', 'campaignPeriod', 'createdBy'])
            ->withCount(['enrollments', 'activeEnrollments'])
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $maternalProfilesQuery = MaternalNutritionProfile::query()
            ->with(['barangay', 'resident.household.purok', 'updatedBy'])
            ->where(function ($query): void {
                $query->where('is_currently_pregnant', true)
                    ->orWhere('is_currently_lactating', true);
            })
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $barangayHotspots = Barangay::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay): array {
                return [
                    'barangay' => $barangay,
                    'open_flag_count' => ChildNutritionAssessmentFlag::query()
                        ->where('barangay_id', $barangay->id)
                        ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
                        ->count(),
                    'target_client_count' => $this->targetClientQuery($barangay->id)->count(),
                    'active_feeding_enrollment_count' => FeedingProgramEnrollment::query()
                        ->where('is_active', true)
                        ->whereHas('feedingProgram', function (Builder $query) use ($barangay): void {
                            $query->where('barangay_id', $barangay->id);
                        })
                        ->count(),
                    'active_maternal_case_count' => MaternalNutritionProfile::query()
                        ->where('barangay_id', $barangay->id)
                        ->where(function ($query): void {
                            $query->where('is_currently_pregnant', true)
                                ->orWhere('is_currently_lactating', true);
                        })
                        ->count(),
                ];
            });

        return view('admin.oversight.nutrition', [
            'barangays' => Barangay::query()->active()->orderBy('name')->get(),
            'selectedBarangay' => $barangayId ? Barangay::find($barangayId) : null,
            'activeCampaignCount' => (clone $campaignsQuery)->active()->count(),
            'activeOptCampaignCount' => (clone $campaignsQuery)
                ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
                ->active()
                ->count(),
            'openNutritionFlagCount' => (clone $openFlagsQuery)->count(),
            'targetClientCount' => $this->targetClientQuery($barangayId ?: null)->count(),
            'activeFeedingProgramCount' => (clone $feedingProgramsQuery)
                ->whereIn('program_status', [FeedingProgram::STATUS_PLANNED, FeedingProgram::STATUS_ACTIVE])
                ->count(),
            'activeFeedingEnrollmentCount' => FeedingProgramEnrollment::query()
                ->where('is_active', true)
                ->when($barangayId, function ($query) use ($barangayId): void {
                    $query->whereHas('feedingProgram', fn (Builder $feedingQuery) => $feedingQuery->where('barangay_id', $barangayId));
                })
                ->count(),
            'activeMaternalCaseCount' => (clone $maternalProfilesQuery)->count(),
            'recentCampaigns' => (clone $campaignsQuery)
                ->latest('starts_on')
                ->limit(10)
                ->get(),
            'openFlags' => (clone $openFlagsQuery)
                ->latest('flagged_at')
                ->limit(10)
                ->get(),
            'feedingPrograms' => (clone $feedingProgramsQuery)
                ->latest('starts_on')
                ->limit(10)
                ->get(),
            'maternalProfiles' => (clone $maternalProfilesQuery)
                ->latest('last_status_updated_at')
                ->limit(8)
                ->get(),
            'barangayHotspots' => $barangayHotspots,
            'hotspotPeak' => $this->resolveHotspotPeak($barangayHotspots),
        ]);
    }

    private function latestOptMeasurementIdsSubquery(?int $barangayId = null): Builder
    {
        return OptMeasurement::query()
            ->selectRaw('MAX(id) as id')
            ->when($barangayId, function (Builder $query) use ($barangayId): void {
                $query->where('barangay_id', $barangayId);
            })
            ->whereRaw(
                'measurement_date = (
                    select max(m2.measurement_date)
                    from opt_measurements as m2
                    where m2.resident_id = opt_measurements.resident_id'
                . ($barangayId ? ' and m2.barangay_id = ?' : '')
                . '
                )',
                $barangayId ? [$barangayId] : []
            )
            ->groupBy('resident_id');
    }

    private function targetClientQuery(?int $barangayId = null): Builder
    {
        return OptMeasurement::query()
            ->when($barangayId, function (Builder $query) use ($barangayId): void {
                $query->where('barangay_id', $barangayId);
            })
            ->whereIn('id', $this->latestOptMeasurementIdsSubquery($barangayId))
            ->where(function (Builder $query): void {
                $query->whereIn('weight_for_age_status', ['Severely Underweight', 'Underweight'])
                    ->orWhereIn('height_for_age_status', ['Severely Stunted', 'Stunted'])
                    ->orWhereIn('weight_for_length_height_status', ['Severely Wasted', 'Wasted']);
            });
    }

    private function resolveHotspotPeak(Collection $rows): int
    {
        return (int) $rows
            ->map(fn (array $row) => max(
                $row['open_flag_count'],
                $row['target_client_count'],
                $row['active_feeding_enrollment_count'],
                $row['active_maternal_case_count'],
                1
            ))
            ->max();
    }
}
