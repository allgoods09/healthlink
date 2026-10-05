<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\ClinicalEncounter;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramEnrollment;
use App\Models\Household;
use App\Models\MaternalNutritionProfile;
use App\Models\MhoClinicalReview;
use App\Models\OptCycle;
use App\Models\OptMeasurement;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use App\Support\ExportDownload;
use App\Support\Nutrition\OptCycleReporting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MunicipalReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $selectedBarangay = $request->filled('barangay_id')
            ? Barangay::find($request->integer('barangay_id'))
            : null;

        $dateFrom = $request->date('date_from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $dateTo = $request->date('date_to')?->endOfDay() ?? now()->endOfDay();

        $reports = collect([
            $this->staffingReport($selectedBarangay),
            $this->demographicReport($selectedBarangay),
            $this->nutritionReport($selectedBarangay),
            $this->clinicalReport($selectedBarangay, $dateFrom, $dateTo),
        ]);

        return view('admin.reports.index', [
            'barangays' => Barangay::query()->active()->orderBy('name')->get(),
            'selectedBarangay' => $selectedBarangay,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reports' => $reports,
            'activeBarangayCount' => Barangay::query()->active()->count(),
            'coveredBarangayCount' => $reports->first()['rows']->count(),
            'municipalClinicianCount' => User::query()
                ->whereIn('role', ['phn', 'mho'])
                ->where('approval_status', User::APPROVAL_APPROVED)
                ->where('is_active', true)
                ->count(),
        ]);
    }

    public function export(Request $request, string $report, string $format)
    {
        Gate::authorize('viewAny', User::class);

        $selectedBarangay = $request->filled('barangay_id')
            ? Barangay::find($request->integer('barangay_id'))
            : null;

        $dateFrom = $request->date('date_from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $dateTo = $request->date('date_to')?->endOfDay() ?? now()->endOfDay();

        $definition = match ($report) {
            'staffing' => $this->staffingReport($selectedBarangay),
            'demographics' => $this->demographicReport($selectedBarangay),
            'nutrition' => $this->nutritionReport($selectedBarangay),
            'clinical' => $this->clinicalReport($selectedBarangay, $dateFrom, $dateTo),
            default => abort(404),
        };

        $pdfLabels = match ($report) {
            'staffing' => ['Barangay', 'Active Puroks', 'Secretaries', 'BNS', 'BHW', 'Total Frontline Staff'],
            'demographics' => ['Barangay', 'Occupied Households', 'Active Residents', 'Male', 'Female', 'Minors', 'Seniors'],
            'nutrition' => ['Barangay', 'OPT+ Cycles In Progress', 'Eligible (Latest Cycle)', 'Measured (Latest Cycle)', 'Unmeasured (Latest Cycle)', 'Coverage (%)', 'Active Feeding Programs'],
            'clinical' => ['Barangay', 'Pending Triage', 'Triage Logged', 'PHN Encounters', 'Due Follow-Ups', 'Active Escalations'],
        };

        return ExportDownload::make(
            $format,
            $definition['title'],
            'Municipal Reports',
            "municipal_{$report}",
            $definition['columns'],
            $definition['rows'],
            $definition['filters'],
            $selectedBarangay?->name ?? 'Municipality-wide',
            Barangay::class,
            array_intersect_key($definition['columns'], array_flip($pdfLabels))
        );
    }

    private function staffingReport(?Barangay $selectedBarangay): array
    {
        $rows = $this->reportBarangays($selectedBarangay)->map(function (Barangay $barangay): array {
            $activeSecretaryCount = $this->approvedUsersForBarangay($barangay, ['secretary'])->count();
            $activeBnsCount = $this->approvedUsersForBarangay($barangay, ['bns'])->count();
            $activeBhwCount = $this->approvedUsersForBarangay($barangay, ['bhw'])->count();

            return [
                'barangay_name' => $barangay->name,
                'active_purok_count' => Purok::query()->where('barangay_id', $barangay->id)->where('is_active', true)->count(),
                'active_secretary_count' => $activeSecretaryCount,
                'active_bns_count' => $activeBnsCount,
                'active_bhw_count' => $activeBhwCount,
                'pending_local_registration_count' => User::query()
                    ->pendingApproval()
                    ->where('requested_barangay_id', $barangay->id)
                    ->where(function (Builder $builder): void {
                        $builder->whereIn('requested_role', User::SECRETARY_APPROVAL_ROLES)
                            ->orWhere(function (Builder $fallbackBuilder): void {
                                $fallbackBuilder->whereNull('requested_role')
                                    ->whereIn('role', User::SECRETARY_APPROVAL_ROLES);
                            });
                    })
                    ->count(),
                'total_frontline_count' => $activeSecretaryCount + $activeBnsCount + $activeBhwCount,
            ];
        });

        return [
            'key' => 'staffing',
            'title' => 'Municipal Staffing Footprint',
            'description' => 'Cross-barangay staffing coverage for local frontline roles and secretary-managed registrations.',
            'columns' => [
                'Barangay' => 'barangay_name',
                'Active Puroks' => 'active_purok_count',
                'Secretaries' => 'active_secretary_count',
                'BNS' => 'active_bns_count',
                'BHW' => 'active_bhw_count',
                'Pending Local Registrations' => 'pending_local_registration_count',
                'Total Frontline Staff' => 'total_frontline_count',
            ],
            'rows' => $rows,
            'filters' => [
                'Barangay Filter' => $selectedBarangay?->name,
            ],
        ];
    }

    private function demographicReport(?Barangay $selectedBarangay): array
    {
        $rows = $this->reportBarangays($selectedBarangay)->map(function (Barangay $barangay): array {
            return [
                'barangay_name' => $barangay->name,
                'active_household_count' => $this->householdsForBarangay($barangay)->where('is_active', true)->count(),
                'inactive_household_count' => $this->householdsForBarangay($barangay)->where('is_active', false)->count(),
                'occupied_household_count' => $this->householdsForBarangay($barangay)->whereHas('currentMembers')->count(),
                'vacant_household_count' => $this->householdsForBarangay($barangay)->whereDoesntHave('currentMembers')->count(),
                'active_resident_count' => $this->residentsForBarangay($barangay)
                    ->currentPopulation()
                    ->count(),
                'male_resident_count' => $this->residentsForBarangay($barangay)
                    ->currentPopulation()
                    ->where('sex', 'Male')
                    ->count(),
                'female_resident_count' => $this->residentsForBarangay($barangay)
                    ->currentPopulation()
                    ->where('sex', 'Female')
                    ->count(),
                'minor_count' => $this->residentsForBarangay($barangay)
                    ->currentPopulation()
                    ->whereDate('birth_date', '>', now()->subYears(18)->toDateString())
                    ->count(),
                'senior_count' => $this->residentsForBarangay($barangay)
                    ->currentPopulation()
                    ->whereDate('birth_date', '<=', now()->subYears(60)->toDateString())
                    ->count(),
                'relocated_count' => $this->residentsForBarangay($barangay)
                    ->where('resident_status', Resident::STATUS_RELOCATED)
                    ->count(),
                'deceased_count' => $this->residentsForBarangay($barangay)
                    ->where('resident_status', Resident::STATUS_DECEASED)
                    ->count(),
                'moved_out_count' => $this->residentsForBarangay($barangay)
                    ->where('resident_status', Resident::STATUS_MOVED_OUT)->count(),
                'social_aid_household_count' => $this->householdsForBarangay($barangay)
                    ->whereHas('currentMembers')
                    ->where('is_social_aid_beneficiary', true)
                    ->count(),
            ];
        });

        return [
            'key' => 'demographics',
            'title' => 'Municipal Demographic Distribution',
            'description' => 'Civil-population snapshot across households, active residents, age bands, and priority support markers.',
            'columns' => [
                'Barangay' => 'barangay_name',
                'Legacy Active Households' => 'active_household_count',
                'Legacy Inactive Households' => 'inactive_household_count',
                'Occupied Households' => 'occupied_household_count',
                'Vacant Households' => 'vacant_household_count',
                'Active Residents' => 'active_resident_count',
                'Male' => 'male_resident_count',
                'Female' => 'female_resident_count',
                'Minors' => 'minor_count',
                'Seniors' => 'senior_count',
                'Relocated (Legacy)' => 'relocated_count',
                'Moved Out' => 'moved_out_count',
                'Deceased' => 'deceased_count',
                'Social Aid Households' => 'social_aid_household_count',
            ],
            'rows' => $rows,
            'filters' => [
                'Barangay Filter' => $selectedBarangay?->name,
            ],
        ];
    }

    private function nutritionReport(?Barangay $selectedBarangay): array
    {
        $rows = $this->reportBarangays($selectedBarangay)->map(function (Barangay $barangay): array {
            $cycleSummary = OptCycleReporting::latestSummary($barangay->id);

            return [
                'barangay_name' => $barangay->name,
                'active_opt_campaign_count' => OptCycle::query()
                    ->where('barangay_id', $barangay->id)
                    ->active()
                    ->count(),
                'opt_eligible_count' => $cycleSummary['eligible'], 'opt_measured_count' => $cycleSummary['measured'],
                'opt_unmeasured_count' => $cycleSummary['unmeasured'], 'opt_coverage' => $cycleSummary['coverage'],
                'open_flag_count' => ChildNutritionAssessmentFlag::query()
                    ->where('barangay_id', $barangay->id)
                    ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
                    ->count(),
                'target_client_count' => $this->targetClientQuery($barangay->id)->count(),
                'active_feeding_program_count' => FeedingProgram::query()
                    ->where('barangay_id', $barangay->id)
                    ->whereIn('program_status', [FeedingProgram::STATUS_PLANNED, FeedingProgram::STATUS_ACTIVE])
                    ->count(),
                'active_feeding_enrollment_count' => FeedingProgramEnrollment::query()
                    ->where('is_active', true)
                    ->whereHas('feedingProgram', function (Builder $query) use ($barangay): void {
                        $query->where('barangay_id', $barangay->id);
                    })
                    ->count(),
                'pregnant_case_count' => MaternalNutritionProfile::query()
                    ->where('barangay_id', $barangay->id)
                    ->where('is_currently_pregnant', true)
                    ->count(),
                'lactating_case_count' => MaternalNutritionProfile::query()
                    ->where('barangay_id', $barangay->id)
                    ->where('is_currently_lactating', true)
                    ->count(),
                'active_maternal_case_count' => MaternalNutritionProfile::query()
                    ->where('barangay_id', $barangay->id)
                    ->where(function (Builder $query): void {
                        $query->where('is_currently_pregnant', true)
                            ->orWhere('is_currently_lactating', true);
                    })
                    ->count(),
            ];
        });

        return [
            'key' => 'nutrition',
            'title' => 'Municipal Nutrition Statistics',
            'description' => 'Live nutrition pressure points covering OPT+, watchlists, feeding programs, and maternal surveillance.',
            'columns' => [
                'Barangay' => 'barangay_name',
                'OPT+ Cycles In Progress' => 'active_opt_campaign_count',
                'Eligible (Latest Cycle)' => 'opt_eligible_count', 'Measured (Latest Cycle)' => 'opt_measured_count',
                'Unmeasured (Latest Cycle)' => 'opt_unmeasured_count', 'Coverage (%)' => 'opt_coverage',
                'Open Flags' => 'open_flag_count',
                'Internal Reference Cases' => 'target_client_count',
                'Active Feeding Programs' => 'active_feeding_program_count',
                'Active Feeding Enrollments' => 'active_feeding_enrollment_count',
                'Pregnant Cases' => 'pregnant_case_count',
                'Lactating Cases' => 'lactating_case_count',
                'Active Maternal Cases' => 'active_maternal_case_count',
            ],
            'rows' => $rows,
            'filters' => [
                'Barangay Filter' => $selectedBarangay?->name,
            ],
        ];
    }

    private function clinicalReport(?Barangay $selectedBarangay, $dateFrom, $dateTo): array
    {
        $rows = $this->reportBarangays($selectedBarangay)->map(function (Barangay $barangay) use ($dateFrom, $dateTo): array {
            return [
                'barangay_name' => $barangay->name,
                'pending_triage_count' => TriageRecord::query()
                    ->where('barangay_id', $barangay->id)
                    ->pending()
                    ->whereNull('consumed_at')
                    ->count(),
                'triage_logged_count' => TriageRecord::query()
                    ->where('barangay_id', $barangay->id)
                    ->whereBetween('measured_at', [$dateFrom, $dateTo])
                    ->count(),
                'triage_consumed_count' => TriageRecord::query()
                    ->where('barangay_id', $barangay->id)
                    ->whereBetween('consumed_at', [$dateFrom, $dateTo])
                    ->count(),
                'phn_encounter_count' => ClinicalEncounter::query()
                    ->where('barangay_id', $barangay->id)
                    ->whereBetween('encountered_at', [$dateFrom, $dateTo])
                    ->count(),
                'walk_in_count' => ClinicalEncounter::query()
                    ->where('barangay_id', $barangay->id)
                    ->where('encounter_source', ClinicalEncounter::SOURCE_WALK_IN)
                    ->whereBetween('encountered_at', [$dateFrom, $dateTo])
                    ->count(),
                'due_follow_up_count' => ClinicalEncounter::query()
                    ->where('barangay_id', $barangay->id)
                    ->dueFollowUp()
                    ->count(),
                'active_escalation_count' => ClinicalEncounter::query()
                    ->where('barangay_id', $barangay->id)
                    ->activeEscalations()
                    ->count(),
                'mho_review_count' => MhoClinicalReview::query()
                    ->whereBetween('reviewed_at', [$dateFrom, $dateTo])
                    ->whereHas('clinicalEncounter', function (Builder $query) use ($barangay): void {
                        $query->where('barangay_id', $barangay->id);
                    })
                    ->count(),
            ];
        });

        return [
            'key' => 'clinical',
            'title' => 'Municipal Clinical Throughput',
            'description' => 'Clinical volume and queue movement across barangays for the selected reporting window.',
            'columns' => [
                'Barangay' => 'barangay_name',
                'Pending Triage' => 'pending_triage_count',
                'Triage Logged' => 'triage_logged_count',
                'Triage Consumed' => 'triage_consumed_count',
                'PHN Encounters' => 'phn_encounter_count',
                'Walk-Ins' => 'walk_in_count',
                'Due Follow-Ups' => 'due_follow_up_count',
                'Active Escalations' => 'active_escalation_count',
                'MHO Reviews' => 'mho_review_count',
            ],
            'rows' => $rows,
            'filters' => [
                'Barangay Filter' => $selectedBarangay?->name,
                'Window From' => $dateFrom->toDateString(),
                'Window To' => $dateTo->toDateString(),
            ],
        ];
    }

    private function reportBarangays(?Barangay $selectedBarangay): Collection
    {
        if ($selectedBarangay) {
            return collect([$selectedBarangay]);
        }

        return Barangay::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    private function approvedUsersForBarangay(Barangay $barangay, array $roles): Builder
    {
        return User::query()
            ->where('assigned_barangay_id', $barangay->id)
            ->whereIn('role', $roles)
            ->where('approval_status', User::APPROVAL_APPROVED)
            ->where('is_active', true);
    }

    private function householdsForBarangay(Barangay $barangay): Builder
    {
        return Household::query()->whereHas('purok', function (Builder $query) use ($barangay): void {
            $query->where('barangay_id', $barangay->id);
        });
    }

    private function residentsForBarangay(Barangay $barangay): Builder
    {
        return Resident::query()->whereHas('household.purok', function (Builder $query) use ($barangay): void {
            $query->where('barangay_id', $barangay->id);
        });
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
                .($barangayId ? ' and m2.barangay_id = ?' : '')
                .'
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
}
