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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FeedingProgramController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
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

        return view('bns.feeding-programs.index', [
            'feedingPrograms' => $query->paginate(12)->withQueryString(),
            'programStatuses' => FeedingProgram::STATUSES,
            'activeProgramCount' => $this->bnsFeedingProgramsQuery()->where('program_status', FeedingProgram::STATUS_ACTIVE)->count(),
        ]);
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

        $enrolledResidentIds = $enrollments->pluck('resident_id')->all();

        $watchlistSuggestions = $this->bnsOptMeasurementsQuery()
            ->with(['resident.household.purok', 'campaignPeriod'])
            ->whereIn('id', $this->latestOptMeasurementIdsSubquery())
            ->where(function ($query): void {
                $query->whereIn('weight_for_age_status', ['Severely Underweight', 'Underweight'])
                    ->orWhereIn('height_for_age_status', ['Severely Stunted', 'Stunted'])
                    ->orWhereIn('weight_for_length_height_status', ['Severely Wasted', 'Wasted']);
            })
            ->whereNotIn('resident_id', $enrolledResidentIds)
            ->latest('measurement_date')
            ->limit(8)
            ->get();

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
            'watchlistSuggestions' => $watchlistSuggestions,
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

        $latestMeasurement = $resident->latestOptMeasurement;

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
