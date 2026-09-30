<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bns\StoreFeedingProgramAttendanceRequest;
use App\Http\Requests\Bns\StoreFeedingProgramEnrollmentRequest;
use App\Http\Requests\Bns\StoreFeedingProgramProgressRequest;
use App\Http\Requests\Bns\StoreFeedingProgramRequest;
use App\Http\Requests\Bns\UpdateFeedingProgramEnrollmentRequest;
use App\Http\Requests\Bns\UpdateFeedingProgramRequest;
use App\Models\AuditLog;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramAttendance;
use App\Models\FeedingProgramEnrollment;
use App\Models\FeedingProgramProgressLog;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FeedingProgramController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $query = $this->listingQuery($request);

        return view('bns.feeding-programs.index', [
            'feedingPrograms' => $query->paginate(12)->withQueryString(),
            'programStatuses' => FeedingProgram::STATUSES,
            'activeProgramCount' => $this->bnsFeedingProgramsQuery()->where('program_status', FeedingProgram::STATUS_ACTIVE)->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Program' => 'name',
            'Campaign' => fn (FeedingProgram $program) => $program->campaignPeriod?->name ?? 'None',
            'Description' => 'description',
            'Starts On' => fn (FeedingProgram $program) => $program->starts_on?->format('Y-m-d'),
            'Ends On' => fn (FeedingProgram $program) => $program->ends_on?->format('Y-m-d') ?? 'Open-ended',
            'Active Enrollments' => 'active_enrollments_count',
            'Total Enrollments' => 'enrollments_count',
            'Status' => fn (FeedingProgram $program) => $program->program_status_label,
            'Created By' => fn (FeedingProgram $program) => $program->createdBy?->name ?? 'Unknown',
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Status' => FeedingProgram::STATUSES[$request->input('program_status')] ?? null,
        ];

        return ExportDownload::make($format, 'Feeding Programs', 'Nutrition', 'bns_feeding_programs', $columns, $this->listingQuery($request)->get(), $filters, $this->bnsUser()->assignedBarangay?->name, FeedingProgram::class, array_intersect_key($columns, array_flip(['Program', 'Campaign', 'Starts On', 'Ends On', 'Active Enrollments', 'Total Enrollments', 'Status'])));
    }

    private function listingQuery(Request $request): Builder
    {
        $query = $this->bnsFeedingProgramsQuery()
            ->with(['campaignPeriod', 'createdBy'])
            ->withCount(['enrollments', 'activeEnrollments'])
            ->latest('starts_on')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('program_status')) {
            $query->where('program_status', $request->string('program_status')->toString());
        }

        return $query;
    }

    public function create(): View
    {
        return view('bns.feeding-programs.create', [
            'programStatuses' => FeedingProgram::STATUSES,
            'campaignPeriods' => $this->bnsCampaignPeriodsQuery()->latest('starts_on')->get(),
        ]);
    }

    public function store(StoreFeedingProgramRequest $request): RedirectResponse
    {
        $feedingProgram = FeedingProgram::query()->create([
            ...$request->validated(),
            'barangay_id' => $this->assignedBarangayId(),
            'created_by_user_id' => Auth::id(),
        ]);

        AuditLog::logMutation('created', Auth::user(), $feedingProgram);

        return redirect()
            ->route('bns.feeding-programs.show', $feedingProgram)
            ->with('success', 'Feeding program created successfully.');
    }

    public function show(Request $request, FeedingProgram $feedingProgram): View
    {
        $this->ensureFeedingProgramBelongsToBarangay($feedingProgram);

        $feedingProgram->load(['campaignPeriod', 'createdBy']);
        $feedingProgram->loadCount(['enrollments', 'activeEnrollments']);

        $enrollments = $feedingProgram->enrollments()
            ->with(['resident.household.purok', 'enrolledBy', 'latestProgressLog'])
            ->withCount(['attendances', 'progressLogs'])
            ->orderByDesc('is_active')
            ->orderBy('enrolled_on')
            ->get();

        $selectedEnrollment = $request->filled('enrollment')
            ? $enrollments->firstWhere('id', (int) $request->input('enrollment'))
            : $enrollments->first();

        if ($selectedEnrollment) {
            $selectedEnrollment->load([
                'resident.household.purok',
                'attendances' => fn ($query) => $query->latest('attendance_date'),
                'progressLogs' => fn ($query) => $query->latest('logged_on'),
            ]);
        }

        return view('bns.feeding-programs.show', [
            'feedingProgram' => $feedingProgram,
            'enrollments' => $enrollments,
            'selectedEnrollment' => $selectedEnrollment,
            'programStatuses' => FeedingProgram::STATUSES,
            'eligibleChildren' => $this->bnsFeedingEligibleChildrenQuery()
                ->with(['household.purok', 'latestOptMeasurement'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function edit(FeedingProgram $feedingProgram): View
    {
        $this->ensureFeedingProgramBelongsToBarangay($feedingProgram);

        return view('bns.feeding-programs.edit', [
            'feedingProgram' => $feedingProgram,
            'programStatuses' => FeedingProgram::STATUSES,
            'campaignPeriods' => $this->bnsCampaignPeriodsQuery()->latest('starts_on')->get(),
        ]);
    }

    public function update(UpdateFeedingProgramRequest $request, FeedingProgram $feedingProgram): RedirectResponse
    {
        $this->ensureFeedingProgramBelongsToBarangay($feedingProgram);

        $oldValues = $feedingProgram->toArray();
        $feedingProgram->update($request->validated());

        AuditLog::logMutation('updated', Auth::user(), $feedingProgram, $oldValues, $feedingProgram->fresh()->toArray());

        return redirect()
            ->route('bns.feeding-programs.show', $feedingProgram)
            ->with('success', 'Feeding program updated successfully.');
    }

    public function storeEnrollment(StoreFeedingProgramEnrollmentRequest $request, FeedingProgram $feedingProgram): RedirectResponse
    {
        $this->ensureFeedingProgramBelongsToBarangay($feedingProgram);

        $resident = $this->bnsFeedingEligibleChildrenQuery()
            ->with('latestOptMeasurement')
            ->findOrFail($request->integer('resident_id'));

        $latestMeasurement = $request->boolean('use_latest_opt_baseline')
            ? $this->bnsOptMeasurementsQuery()->where('resident_id', $resident->id)->latest('measurement_date')->latest('id')->first()
            : null;
        if ($request->boolean('use_latest_opt_baseline') && ! $latestMeasurement) {
            throw ValidationException::withMessages(['use_latest_opt_baseline' => 'No accessible OPT reference exists for this child. Enter a baseline manually or leave it blank.']);
        }
        if ($latestMeasurement && ($request->filled('baseline_weight_kg') || $request->filled('baseline_nutritional_status'))) {
            throw ValidationException::withMessages(['use_latest_opt_baseline' => 'Choose either manual baseline values or explicit OPT reference copying, not both.']);
        }

        $enrollment = FeedingProgramEnrollment::query()->create([
            'feeding_program_id' => $feedingProgram->id,
            'resident_id' => $resident->id,
            'enrolled_by_user_id' => Auth::id(),
            'enrolled_on' => $request->date('enrolled_on'),
            'baseline_weight_kg' => $request->filled('baseline_weight_kg')
                ? $request->input('baseline_weight_kg')
                : $latestMeasurement?->weight_kg,
            'baseline_nutritional_status' => $request->filled('baseline_nutritional_status')
                ? $request->input('baseline_nutritional_status')
                : ($latestMeasurement ? implode(', ', $latestMeasurement->target_client_reasons) ?: $latestMeasurement->weight_for_length_height_status : null),
            'baseline_opt_measurement_id' => $latestMeasurement?->id,
            'baseline_provenance' => $latestMeasurement ? [
                'source' => 'explicit_opt_reference_copy', 'measurement_id' => $latestMeasurement->id,
                'measurement_date' => $latestMeasurement->measurement_date->toDateString(),
                'weight_kg' => $latestMeasurement->weight_kg, 'internal_status' => $latestMeasurement->weight_for_length_height_status,
                'target_client_reasons' => $latestMeasurement->target_client_reasons,
                'copied_at' => now()->toIso8601String(), 'copied_by_user_id' => Auth::id(),
            ] : ['source' => 'manual_or_unspecified', 'recorded_at' => now()->toIso8601String()],
            'is_active' => true,
            'completion_notes' => $request->input('completion_notes'),
        ]);

        AuditLog::logMutation('created', Auth::user(), $enrollment);

        return redirect()
            ->route('bns.feeding-programs.show', ['feedingProgram' => $feedingProgram, 'enrollment' => $enrollment->id])
            ->with('success', 'Child enrolled in feeding program successfully.');
    }

    public function updateEnrollment(UpdateFeedingProgramEnrollmentRequest $request, FeedingProgram $feedingProgram, FeedingProgramEnrollment $enrollment): RedirectResponse
    {
        $this->ensureFeedingProgramBelongsToBarangay($feedingProgram);
        $this->ensureFeedingProgramEnrollmentBelongsToBarangay($enrollment);

        if ((int) $enrollment->feeding_program_id !== (int) $feedingProgram->id) {
            abort(404);
        }

        $oldValues = $enrollment->toArray();
        $enrollment->update($request->validated());

        AuditLog::logMutation('updated', Auth::user(), $enrollment, $oldValues, $enrollment->fresh()->toArray());

        return redirect()
            ->route('bns.feeding-programs.show', ['feedingProgram' => $feedingProgram, 'enrollment' => $enrollment->id])
            ->with('success', 'Feeding program enrollment updated successfully.');
    }

    public function storeAttendance(StoreFeedingProgramAttendanceRequest $request, FeedingProgram $feedingProgram, FeedingProgramEnrollment $enrollment): RedirectResponse
    {
        $this->ensureFeedingProgramBelongsToBarangay($feedingProgram);
        $this->ensureFeedingProgramEnrollmentBelongsToBarangay($enrollment);

        if ((int) $enrollment->feeding_program_id !== (int) $feedingProgram->id) {
            abort(404);
        }

        $attendance = FeedingProgramAttendance::query()->create([
            ...$request->validated(),
            'enrollment_id' => $enrollment->id,
        ]);

        AuditLog::logMutation('created', Auth::user(), $attendance);

        return redirect()
            ->route('bns.feeding-programs.show', ['feedingProgram' => $feedingProgram, 'enrollment' => $enrollment->id])
            ->with('success', 'Attendance entry recorded successfully.');
    }

    public function storeProgress(StoreFeedingProgramProgressRequest $request, FeedingProgram $feedingProgram, FeedingProgramEnrollment $enrollment): RedirectResponse
    {
        $this->ensureFeedingProgramBelongsToBarangay($feedingProgram);
        $this->ensureFeedingProgramEnrollmentBelongsToBarangay($enrollment);

        if ((int) $enrollment->feeding_program_id !== (int) $feedingProgram->id) {
            abort(404);
        }

        $progressLog = FeedingProgramProgressLog::query()->create([
            ...$request->validated(),
            'enrollment_id' => $enrollment->id,
            'logged_by_user_id' => Auth::id(),
        ]);

        AuditLog::logMutation('created', Auth::user(), $progressLog);

        return redirect()
            ->route('bns.feeding-programs.show', ['feedingProgram' => $feedingProgram, 'enrollment' => $enrollment->id])
            ->with('success', 'Weekly progress entry recorded successfully.');
    }
}
