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
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NutritionOversightController extends Controller
{
    public function __invoke(Request $request): View
    {
        $barangayId = $request->integer('barangay_id');

        $campaignsQuery = $this->campaignsQuery($barangayId);
        $openFlagsQuery = $this->openFlagsQuery($barangayId);
        $feedingProgramsQuery = $this->feedingProgramsQuery($barangayId);
        $maternalProfilesQuery = $this->maternalProfilesQuery($barangayId);

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
            'recentCampaigns' => $this->panelQuery($barangayId, 'campaigns')->limit(10)->get(),
            'openFlags' => $this->panelQuery($barangayId, 'open-flags')->limit(10)->get(),
            'feedingPrograms' => $this->panelQuery($barangayId, 'feeding-programs')->limit(10)->get(),
            'maternalProfiles' => $this->panelQuery($barangayId, 'maternal-profiles')->limit(8)->get(),
            'barangayHotspots' => $barangayHotspots,
            'hotspotPeak' => $this->resolveHotspotPeak($barangayHotspots),
        ]);
    }

    public function export(Request $request, string $dataset, string $format): Response
    {
        $definitions = [
            'campaigns' => [
                'title' => 'Nutrition Campaign Periods',
                'model' => NutritionCampaignPeriod::class,
                'columns' => [
                    'Campaign' => 'name',
                    'Barangay' => fn (NutritionCampaignPeriod $item) => $item->barangay?->name,
                    'Type' => fn (NutritionCampaignPeriod $item) => $item->campaign_type_label,
                    'Starts On' => fn (NutritionCampaignPeriod $item) => $item->starts_on?->format('Y-m-d'),
                    'Ends On' => fn (NutritionCampaignPeriod $item) => $item->ends_on?->format('Y-m-d'),
                    'OPT+ Measurements' => 'opt_measurements_count',
                    'Feeding Programs' => 'feeding_programs_count',
                ],
            ],
            'open-flags' => [
                'title' => 'Open Nutrition Flags',
                'model' => ChildNutritionAssessmentFlag::class,
                'columns' => [
                    'Resident' => fn (ChildNutritionAssessmentFlag $item) => $item->resident?->formal_name,
                    'Barangay' => fn (ChildNutritionAssessmentFlag $item) => $item->barangay?->name,
                    'Purok' => fn (ChildNutritionAssessmentFlag $item) => $item->purok?->display_name,
                    'Reason' => 'flag_reason',
                    'Flagged By' => fn (ChildNutritionAssessmentFlag $item) => $item->flaggedBy?->name,
                    'Flagged At' => fn (ChildNutritionAssessmentFlag $item) => $item->flagged_at?->format('Y-m-d H:i:s'),
                ],
            ],
            'feeding-programs' => [
                'title' => 'Nutrition Feeding Programs',
                'model' => FeedingProgram::class,
                'columns' => [
                    'Program' => 'name',
                    'Barangay' => fn (FeedingProgram $item) => $item->barangay?->name,
                    'Status' => fn (FeedingProgram $item) => $item->program_status_label,
                    'Starts On' => fn (FeedingProgram $item) => $item->starts_on?->format('Y-m-d'),
                    'Ends On' => fn (FeedingProgram $item) => $item->ends_on?->format('Y-m-d'),
                    'Active Enrollments' => 'active_enrollments_count',
                    'Total Enrollments' => 'enrollments_count',
                ],
            ],
            'maternal-profiles' => [
                'title' => 'Maternal Surveillance Snapshot',
                'model' => MaternalNutritionProfile::class,
                'columns' => [
                    'Resident' => fn (MaternalNutritionProfile $item) => $item->resident?->formal_name,
                    'Barangay' => fn (MaternalNutritionProfile $item) => $item->barangay?->name,
                    'Purok' => fn (MaternalNutritionProfile $item) => $item->resident?->household?->purok?->display_name,
                    'Status' => fn (MaternalNutritionProfile $item) => $item->status_summary,
                    'Updated At' => fn (MaternalNutritionProfile $item) => $item->last_status_updated_at?->format('Y-m-d H:i:s'),
                    'Updated By' => fn (MaternalNutritionProfile $item) => $item->updatedBy?->name,
                ],
            ],
        ];

        abort_unless(isset($definitions[$dataset]), 404);
        $definition = $definitions[$dataset];
        $barangayId = $request->integer('barangay_id');
        $barangayName = $barangayId ? Barangay::findOrFail($barangayId)->name : 'Municipality-wide';

        return ExportDownload::make($format, $definition['title'], 'Nutrition Oversight', 'nutrition_'.$dataset, $definition['columns'], $this->panelQuery($barangayId, $dataset)->get(), ['Barangay' => $barangayId ? $barangayName : null], $barangayName, $definition['model']);
    }

    private function campaignsQuery(int $barangayId): Builder
    {
        return NutritionCampaignPeriod::query()
            ->with(['barangay', 'createdBy'])
            ->withCount(['optMeasurements', 'feedingPrograms'])
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function openFlagsQuery(int $barangayId): Builder
    {
        return ChildNutritionAssessmentFlag::query()
            ->with(['barangay', 'purok', 'resident.household.purok', 'flaggedBy'])
            ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function feedingProgramsQuery(int $barangayId): Builder
    {
        return FeedingProgram::query()
            ->with(['barangay', 'campaignPeriod', 'createdBy'])
            ->withCount(['enrollments', 'activeEnrollments'])
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function maternalProfilesQuery(int $barangayId): Builder
    {
        return MaternalNutritionProfile::query()
            ->with(['barangay', 'resident.household.purok', 'updatedBy'])
            ->where(function (Builder $query): void {
                $query->where('is_currently_pregnant', true)
                    ->orWhere('is_currently_lactating', true);
            })
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function panelQuery(int $barangayId, string $dataset): Builder
    {
        return match ($dataset) {
            'campaigns' => $this->campaignsQuery($barangayId)->latest('starts_on')->latest('id'),
            'open-flags' => $this->openFlagsQuery($barangayId)->latest('flagged_at')->latest('id'),
            'feeding-programs' => $this->feedingProgramsQuery($barangayId)->latest('starts_on')->latest('id'),
            'maternal-profiles' => $this->maternalProfilesQuery($barangayId)->latest('last_status_updated_at')->latest('id'),
            default => abort(404),
        };
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
