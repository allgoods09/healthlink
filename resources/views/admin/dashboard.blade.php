@extends('layouts.admin')

@section('title', 'Dashboard - HealthLink Admin')
@section('header', 'Municipal Command Center')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.oversight.field') }}" class="inline-flex items-center rounded-md bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Field Operations Monitor
        </a>
        <a href="{{ route('admin.oversight.clinical') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-tubigon hover:text-tubigon">
            Clinical Oversight
        </a>
    </div>
@endsection

@section('content')
    <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-tubigon p-6 text-white shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/60">Municipality-Wide Oversight</p>
        <h2 class="mt-3 text-3xl font-semibold tracking-tight">Live Operations Across Tubigon</h2>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-white/80">
            This command center is the municipal view across Secretary verification, BHW field intake, BNS nutrition action, PHN follow-up pressure, and unresolved MHO escalations.
        </p>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-amber-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Unresolved Field Drafts</p>
            <p class="mt-2 text-3xl font-semibold text-amber-600">{{ number_format($pendingFieldDraftCount) }}</p>
            <p class="mt-2 text-sm text-gray-500">Household packages waiting for Secretary review</p>
        </div>

        <div class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Pending Correction Requests</p>
            <p class="mt-2 text-3xl font-semibold text-blue-700">{{ number_format($pendingCorrectionRequestCount) }}</p>
            <p class="mt-2 text-sm text-gray-500">Resident and household corrections still in queue</p>
        </div>

        <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Active Nutrition Flags</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ number_format($activeNutritionFlagCount) }}</p>
            <p class="mt-2 text-sm text-gray-500">{{ number_format($municipalTargetClientCount) }} latest target clients across all barangays</p>
        </div>

        <div class="rounded-xl border border-rose-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Overdue Follow-Ups</p>
            <p class="mt-2 text-3xl font-semibold text-rose-700">{{ number_format($overdueFollowUpCount) }}</p>
            <p class="mt-2 text-sm text-gray-500">PHN cases past or due for the next contact cycle</p>
        </div>

        <div class="rounded-xl border border-violet-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Unresolved MHO Escalations</p>
            <p class="mt-2 text-3xl font-semibold text-violet-700">{{ number_format($unresolvedMhoEscalationCount) }}</p>
            <p class="mt-2 text-sm text-gray-500">Escalated PHN cases still waiting for municipal resolution</p>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Field Operations</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-900">Barangay Intake and Verification</h3>
                </div>
                <a href="{{ route('admin.oversight.field') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open</a>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-4">
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">Pending Drafts</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($pendingFieldDraftCount) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">Correction Requests</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($pendingCorrectionRequestCount) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Nutrition Oversight</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-900">OPT+, Feeding, Maternal Tracking</h3>
                </div>
                <a href="{{ route('admin.oversight.nutrition') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open</a>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-4">
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">OPT+ Campaigns</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($activeOptCampaignCount) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">Feeding Programs</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($activeFeedingProgramCount) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">Open Flags</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($activeNutritionFlagCount) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">Maternal Active Cases</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($activeMaternalCaseCount) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Clinical Oversight</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-900">Triage, PHN, and MHO Flow</h3>
                </div>
                <a href="{{ route('admin.oversight.clinical') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open</a>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-4">
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">Pending Triage</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($pendingTriageCount) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">PHN Reviewed Today</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($phnReviewedTodayCount) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">MHO Reviewed Today</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($mhoReviewedTodayCount) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-4">
                    <p class="text-sm text-gray-500">Overdue Follow-Ups</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($overdueFollowUpCount) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Barangay Operations Load</h3>
                <p class="mt-1 text-sm text-gray-500">Cross-barangay pressure points across field intake, nutrition risk, and clinical follow-up.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                @php
                    $municipalOperationPeak = max($municipalOperationPeak, 1);
                @endphp
                @forelse($municipalOperationBreakdown as $row)
                    @php
                        $peakLoad = max($row['pending_draft_count'], $row['pending_update_count'], $row['open_flag_count'], $row['due_follow_up_count'], $row['active_escalation_count']);
                        $barWidth = max(($peakLoad / $municipalOperationPeak) * 100, 6);
                    @endphp
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $row['barangay']->name }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-gray-400">Peak {{ number_format($peakLoad) }}</p>
                            </div>
                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">{{ number_format($row['pending_triage_count']) }} pending triage</span>
                        </div>
                        <div class="mt-4 h-2 rounded-full bg-gray-100">
                            <div class="h-2 rounded-full bg-tubigon" style="width: {{ $barWidth }}%"></div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600 md:grid-cols-5">
                            <div>Drafts: <span class="font-semibold text-gray-900">{{ number_format($row['pending_draft_count']) }}</span></div>
                            <div>Requests: <span class="font-semibold text-gray-900">{{ number_format($row['pending_update_count']) }}</span></div>
                            <div>Flags: <span class="font-semibold text-gray-900">{{ number_format($row['open_flag_count']) }}</span></div>
                            <div>Follow-Ups: <span class="font-semibold text-gray-900">{{ number_format($row['due_follow_up_count']) }}</span></div>
                            <div>Escalations: <span class="font-semibold text-gray-900">{{ number_format($row['active_escalation_count']) }}</span></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                        No barangay operations data is available yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Immediate Attention</h3>
                <div class="mt-5 space-y-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Field Intake</p>
                        <p class="mt-2 text-sm text-amber-900">{{ number_format($pendingFieldDraftCount) }} draft package(s) and {{ number_format($pendingCorrectionRequestCount) }} correction request(s) are still unresolved.</p>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Nutrition</p>
                        <p class="mt-2 text-sm text-emerald-900">{{ number_format($activeNutritionFlagCount) }} open flag(s), {{ number_format($municipalTargetClientCount) }} current target client(s), and {{ number_format($activeMaternalCaseCount) }} active maternal surveillance case(s).</p>
                    </div>
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-800">Clinical</p>
                        <p class="mt-2 text-sm text-rose-900">{{ number_format($overdueFollowUpCount) }} follow-up case(s) are due and {{ number_format($unresolvedMhoEscalationCount) }} escalation(s) are still unresolved at the municipal level.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Platform Health</h3>
                <div class="mt-5 grid grid-cols-2 gap-4">
                    <div class="rounded-lg bg-slate-50 px-4 py-4">
                        <p class="text-sm text-gray-500">Pending Approvals</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($pendingUsers ?? 0) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-4 py-4">
                        <p class="text-sm text-gray-500">Sync Issues</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format(($failedSyncs ?? 0) + ($partialSyncs ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-4 py-4">
                        <p class="text-sm text-gray-500">Blocked Rate Limits</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($blockedRateLimitCount ?? 0) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-4 py-4">
                        <p class="text-sm text-gray-500">Stale Device Tokens</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($staleDeviceTokens ?? 0) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Field Queue Watch</h3>
                    <p class="text-sm text-gray-500">Latest unresolved field submissions.</p>
                </div>
                <a href="{{ route('admin.oversight.field') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">View Monitor</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentPendingDrafts as $draft)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $draft->draft_reference_code }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $draft->barangay?->name ?? 'Unknown barangay' }} · {{ $draft->purok?->display_name ?? 'Unknown purok' }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $draft->resident_drafts_count }} resident draft(s) · {{ $draft->submittedBy?->name ?? 'Unknown submitter' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No pending draft packages right now.</div>
                @endforelse
                @foreach($recentPendingUpdateRequests as $requestItem)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $requestItem->subject_name }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $requestItem->barangay?->name ?? 'Unknown barangay' }} · {{ $requestItem->subject_label }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $requestItem->request_reason ?: 'No reason recorded.' }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Nutrition Watch</h3>
                    <p class="text-sm text-gray-500">Open flags and households needing close follow-through.</p>
                </div>
                <a href="{{ route('admin.oversight.nutrition') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">View Monitor</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentOpenFlags as $flag)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $flag->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $flag->barangay?->name ?? 'Unknown barangay' }} · {{ $flag->purok?->display_name ?? 'Unknown purok' }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $flag->flag_reason ?: 'No reason recorded.' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No open nutrition flags right now.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Clinical Watch</h3>
                    <p class="text-sm text-gray-500">Follow-up breaches and unresolved municipal escalations.</p>
                </div>
                <a href="{{ route('admin.oversight.clinical') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">View Monitor</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentOverdueFollowUps as $encounter)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · Follow-up {{ $encounter->follow_up_date?->format('M d, Y') ?? 'No date' }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $encounter->working_impression ?: ($encounter->follow_up_notes ?: 'No note recorded.') }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No overdue follow-ups right now.</div>
                @endforelse

                @foreach($recentEscalations as $encounter)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · {{ $encounter->clinical_status_label }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $encounter->escalation_notes ?: ($encounter->working_impression ?: 'No escalation note recorded.') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 overflow-hidden rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Operational Alerts</h3>
                <a href="{{ route('admin.metrics.index') }}" class="text-sm text-tubigon hover:text-tubigon-hover">System Metrics</a>
            </div>
            <div class="p-6">
                @if(($opsAlerts ?? collect())->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($opsAlerts as $alert)
                            <div class="rounded-lg border p-4
                                @if($alert['severity'] === 'danger') border-rose-200 bg-rose-50
                                @elseif($alert['severity'] === 'warning') border-amber-200 bg-amber-50
                                @else border-blue-200 bg-blue-50
                                @endif">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $alert['title'] }}</p>
                                        <p class="mt-1 text-sm text-gray-700">{{ $alert['description'] }}</p>
                                    </div>
                                    <a href="{{ $alert['action_url'] }}" class="inline-flex rounded-md px-4 py-2 text-sm font-medium
                                        @if($alert['severity'] === 'danger') bg-rose-600 text-white hover:bg-rose-700
                                        @elseif($alert['severity'] === 'warning') bg-amber-600 text-white hover:bg-amber-700
                                        @else bg-blue-600 text-white hover:bg-blue-700
                                        @endif">
                                        {{ $alert['action_label'] }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No active operational alerts right now.</p>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Sync Issues</h3>
            </div>
            <div class="p-6">
                @if(($recentSyncIssues ?? collect())->isNotEmpty())
                    <ul class="space-y-3">
                        @foreach($recentSyncIssues as $issue)
                            <li class="rounded-lg border border-gray-200 px-4 py-3">
                                <p class="text-sm font-medium text-gray-900">{{ $issue->user?->name ?? 'Unknown BHW' }} · {{ ucfirst($issue->status) }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $issue->error_message ?: 'See sync metadata for rejected records.' }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $issue->created_at?->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-500">No failed or partial syncs in the recent activity window.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-8 overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
        </div>
        <div class="grid grid-cols-1 gap-3 p-6 md:grid-cols-2 xl:grid-cols-3">
            <a href="{{ route('admin.oversight.field') }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 hover:bg-amber-100">
                Open Field Operations Monitor
            </a>
            <a href="{{ route('admin.oversight.nutrition') }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 hover:bg-emerald-100">
                Open Nutrition Oversight
            </a>
            <a href="{{ route('admin.oversight.clinical') }}" class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-medium text-violet-900 hover:bg-violet-100">
                Open Clinical Oversight
            </a>
            <a href="{{ route('admin.users.index', ['approval_status' => \App\Models\User::APPROVAL_PENDING]) }}" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-900 hover:bg-blue-100">
                Review Pending Registrations
            </a>
            <a href="{{ route('admin.backups.index') }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 hover:bg-emerald-100">
                Review Backup Health
            </a>
            <a href="{{ route('admin.rate-limits.index') }}" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-900 hover:bg-gray-100">
                Review Rate Limits
            </a>
        </div>
    </div>
@endsection
