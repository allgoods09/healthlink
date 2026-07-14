<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Barangay;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\ClinicalEncounter;
use App\Models\FeedingProgram;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\MhoClinicalReview;
use App\Models\MaternalNutritionProfile;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\SyncLog;
use App\Models\TriageRecord;
use App\Models\User;
use App\Support\RateLimitState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with live project metrics.
     */
    public function __invoke(): View
    {
        $today = now()->toDateString();
        $successfulSyncs = SyncLog::where('status', SyncLog::STATUS_SUCCESS)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $failedSyncs = SyncLog::where('status', SyncLog::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $partialSyncs = SyncLog::where('status', SyncLog::STATUS_PARTIAL)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $pendingUsers = User::pendingApproval()->count();
        $pendingOldest = User::pendingApproval()->oldest('created_at')->first();
        $blockedRateLimits = RateLimitState::blockedKeys();
        $tokenQuery = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', User::query()->where('role', 'bhw')->pluck('id'));
        $staleDeviceTokens = (clone $tokenQuery)
            ->where(function ($query): void {
                $query->where(function ($nested): void {
                    $nested->whereNull('last_used_at')
                        ->where('created_at', '<=', now()->subDays(7));
                })->orWhere('last_used_at', '<=', now()->subDays(30));
            })
            ->count();
        $backupRecords = Backup::query()->latest()->get();
        $missingBackups = $backupRecords->filter(fn (Backup $backup) => $backup->status === Backup::STATUS_COMPLETED && ! $backup->has_stored_file)->count();
        $expiredBackups = $backupRecords->filter(fn (Backup $backup) => $backup->is_expired)->count();
        $unverifiedBackups = $backupRecords->filter(fn (Backup $backup) => $backup->status === Backup::STATUS_COMPLETED && $backup->integrity_status !== 'verified')->count();

        $alerts = collect();

        if ($pendingUsers > 0) {
            $alerts->push([
                'severity' => 'warning',
                'title' => 'Pending approvals need review',
                'description' => $pendingOldest
                    ? "{$pendingUsers} registration(s) are waiting. Oldest request was submitted ".$pendingOldest->created_at->diffForHumans().'.'
                    : "{$pendingUsers} registration(s) are waiting for approval.",
                'action_label' => 'Review approvals',
                'action_url' => route('admin.users.index', ['approval_status' => User::APPROVAL_PENDING]),
            ]);
        }

        if ($failedSyncs > 0 || $partialSyncs > 0) {
            $alerts->push([
                'severity' => 'danger',
                'title' => 'Recent mobile sync issues detected',
                'description' => "{$failedSyncs} failed sync(s) and {$partialSyncs} partial sync(s) were logged in the last 7 days.",
                'action_label' => 'Inspect sync logs',
                'action_url' => route('admin.sync-logs.index', ['status' => $failedSyncs > 0 ? SyncLog::STATUS_FAILED : SyncLog::STATUS_PARTIAL]),
            ]);
        }

        if ($staleDeviceTokens > 0) {
            $alerts->push([
                'severity' => 'info',
                'title' => 'Stale device tokens need review',
                'description' => "{$staleDeviceTokens} mobile token(s) have been idle long enough to deserve a manual check.",
                'action_label' => 'Review devices',
                'action_url' => route('admin.devices.index'),
            ]);
        }

        if ($missingBackups > 0 || $expiredBackups > 0 || $unverifiedBackups > 0) {
            $alerts->push([
                'severity' => 'warning',
                'title' => 'Backup hygiene needs attention',
                'description' => "{$missingBackups} missing, {$expiredBackups} expired, {$unverifiedBackups} unverified completed backup(s).",
                'action_label' => 'Review backups',
                'action_url' => route('admin.backups.index'),
            ]);
        }

        if (count($blockedRateLimits) > 0) {
            $alerts->push([
                'severity' => 'info',
                'title' => 'Blocked rate-limit keys are active',
                'description' => count($blockedRateLimits)." tracked throttle key(s) are currently blocked.",
                'action_label' => 'Review rate limits',
                'action_url' => route('admin.rate-limits.index'),
            ]);
        }

        $municipalOperationBreakdown = Barangay::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay) use ($today): array {
                return [
                    'barangay' => $barangay,
                    'pending_draft_count' => HouseholdDraft::query()
                        ->where('barangay_id', $barangay->id)
                        ->pending()
                        ->count(),
                    'pending_update_count' => ProfileUpdateRequest::query()
                        ->where('barangay_id', $barangay->id)
                        ->pending()
                        ->count(),
                    'open_flag_count' => ChildNutritionAssessmentFlag::query()
                        ->where('barangay_id', $barangay->id)
                        ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
                        ->count(),
                    'due_follow_up_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->dueFollowUp()
                        ->count(),
                    'active_escalation_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->activeEscalations()
                        ->count(),
                    'pending_triage_count' => TriageRecord::query()
                        ->where('barangay_id', $barangay->id)
                        ->pending()
                        ->whereNull('consumed_at')
                        ->count(),
                    'reviewed_today_count' => ClinicalEncounter::query()
                        ->where('barangay_id', $barangay->id)
                        ->whereDate('encountered_at', $today)
                        ->count(),
                ];
            });

        $recentPendingDrafts = HouseholdDraft::query()
            ->with(['barangay', 'purok', 'submittedBy'])
            ->withCount('residentDrafts')
            ->pending()
            ->latest()
            ->limit(5)
            ->get();

        $recentPendingUpdateRequests = ProfileUpdateRequest::query()
            ->with(['barangay', 'submittedBy', 'resident.household.purok', 'household.purok'])
            ->pending()
            ->latest()
            ->limit(5)
            ->get();

        $recentOpenFlags = ChildNutritionAssessmentFlag::query()
            ->with(['resident.household.purok.barangay', 'flaggedBy'])
            ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
            ->latest('flagged_at')
            ->limit(5)
            ->get();

        $recentOverdueFollowUps = ClinicalEncounter::query()
            ->with(['resident.household.purok.barangay', 'attendedBy'])
            ->dueFollowUp()
            ->orderBy('follow_up_date')
            ->limit(5)
            ->get();

        $recentEscalations = ClinicalEncounter::query()
            ->with(['resident.household.purok.barangay', 'attendedBy'])
            ->activeEscalations()
            ->latest('escalated_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'pendingUsers' => $pendingUsers,
            'totalBarangays' => Barangay::count(),
            'totalPuroks' => Purok::count(),
            'totalHouseholds' => Household::count(),
            'totalResidents' => Resident::count(),
            'successfulSyncs' => $successfulSyncs,
            'failedSyncs' => $failedSyncs,
            'partialSyncs' => $partialSyncs,
            'staleDeviceTokens' => $staleDeviceTokens,
            'missingBackups' => $missingBackups,
            'expiredBackups' => $expiredBackups,
            'unverifiedBackups' => $unverifiedBackups,
            'blockedRateLimitCount' => count($blockedRateLimits),
            'opsAlerts' => $alerts,
            'pendingFieldDraftCount' => HouseholdDraft::query()->pending()->count(),
            'pendingCorrectionRequestCount' => ProfileUpdateRequest::query()->pending()->count(),
            'activeNutritionFlagCount' => ChildNutritionAssessmentFlag::query()
                ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
                ->count(),
            'municipalTargetClientCount' => $this->targetClientQuery()->count(),
            'overdueFollowUpCount' => ClinicalEncounter::query()->dueFollowUp()->count(),
            'unresolvedMhoEscalationCount' => ClinicalEncounter::query()->activeEscalations()->count(),
            'pendingTriageCount' => TriageRecord::query()->pending()->whereNull('consumed_at')->count(),
            'activeOptCampaignCount' => NutritionCampaignPeriod::query()
                ->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
                ->active()
                ->count(),
            'activeFeedingProgramCount' => FeedingProgram::query()
                ->whereIn('program_status', [FeedingProgram::STATUS_PLANNED, FeedingProgram::STATUS_ACTIVE])
                ->count(),
            'activeMaternalCaseCount' => MaternalNutritionProfile::query()
                ->where(fn ($query) => $query->where('is_currently_pregnant', true)->orWhere('is_currently_lactating', true))
                ->count(),
            'phnReviewedTodayCount' => ClinicalEncounter::query()->whereDate('encountered_at', $today)->count(),
            'mhoReviewedTodayCount' => MhoClinicalReview::query()->whereDate('reviewed_at', $today)->count(),
            'municipalOperationBreakdown' => $municipalOperationBreakdown,
            'municipalOperationPeak' => $this->resolveMunicipalOperationPeak($municipalOperationBreakdown),
            'recentPendingDrafts' => $recentPendingDrafts,
            'recentPendingUpdateRequests' => $recentPendingUpdateRequests,
            'recentOpenFlags' => $recentOpenFlags,
            'recentOverdueFollowUps' => $recentOverdueFollowUps,
            'recentEscalations' => $recentEscalations,
            'recentSyncIssues' => SyncLog::with('user')
                ->whereIn('status', [SyncLog::STATUS_FAILED, SyncLog::STATUS_PARTIAL])
                ->latest()
                ->limit(5)
                ->get(),
            'recentLogs' => AuditLog::with('user')
                ->latest()
                ->limit(8)
                ->get(),
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

    private function resolveMunicipalOperationPeak(Collection $rows): int
    {
        return (int) $rows
            ->map(fn (array $row) => max(
                $row['pending_draft_count'],
                $row['pending_update_count'],
                $row['open_flag_count'],
                $row['due_follow_up_count'],
                $row['active_escalation_count'],
                1
            ))
            ->max();
    }
}
