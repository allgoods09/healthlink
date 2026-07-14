@extends('layouts.portal')

@section('title', 'BHW Dashboard - HealthLink')
@section('header', 'BHW Dashboard')
@section('subheader', 'Field intake, clinic triage, and nutrition handoff workspaces for barangay operations.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('bhw.drafts.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            New Draft Package
        </a>
        <a href="{{ route('bhw.triage.create') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            New Triage Entry
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover px-6 py-8 text-white shadow-xl shadow-tubigon/20">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/65">Today&apos;s Priorities</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ auth()->user()->assignment_label }}</h2>
            <p class="mt-4 max-w-2xl text-sm leading-7 text-white/85">
                This workspace now centers BHW work around campaign rosters, clinic triage, verified-directory lookup, and draft submissions that flow into the Secretary and BNS queues.
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Tasks Due Today</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($todayTaskCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Open Nutrition Flags</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($openFlagCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Triage Sent Today</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($triageTodayCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Pending Drafts</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($pendingDraftCount) }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Triage Queue</p>
                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Pending</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($triagePendingCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Consumed</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($triageConsumedCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Editable</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($editableTriageCount ?? $triagePendingCount) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Verification Tracking</p>
                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Draft Approved</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($approvedDraftCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Draft Rejected</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($rejectedDraftCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Update Pending</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($pendingUpdateCount) }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Campaign Tasks Due Today</h3>
                    <p class="text-sm text-slate-500">Assigned roster entries that still need action or notes.</p>
                </div>
                <a href="{{ route('bhw.campaigns.index', ['due_today' => 1]) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open All</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($dueTodayAssignments as $assignment)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $assignment->campaign?->title ?? 'Untitled campaign' }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $assignment->target_label }}
                            @if($assignment->campaign?->assignedPurok)
                                · {{ $assignment->campaign->assignedPurok->display_name }}
                            @endif
                        </p>
                        <p class="mt-2 text-sm text-slate-600">{{ $assignment->assignment_status_label }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No active campaign roster entries are due today yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">BHW to BNS Pending Flags</h3>
                    <p class="text-sm text-slate-500">Children you flagged that still need official nutrition assessment.</p>
                </div>
                <a href="{{ route('bhw.nutrition-flags.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">View Flags</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($openFlags as $flag)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $flag->resident?->formal_name ?? 'Unknown child' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $flag->resident?->household?->purok?->display_name ?? 'Unknown purok' }} · flagged {{ $flag->flagged_at?->diffForHumans() }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $flag->flag_reason }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No open nutrition flags are waiting right now.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Recent Triage Entries</h3>
                    <p class="text-sm text-slate-500">Today&apos;s and recent clinic submissions awaiting PHN/MHO action.</p>
                </div>
                <a href="{{ route('bhw.triage.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Queue</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($recentTriage as $triage)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $triage->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $triage->resident?->household?->purok?->display_name ?? 'Unknown purok' }} · {{ $triage->measured_at?->format('M d, Y h:i A') }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $triage->triage_status_label }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No triage entries have been recorded yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Drafts and Correction Tracking</h3>
                    <p class="text-sm text-slate-500">Latest field packages and correction requests with Secretary review status.</p>
                </div>
                <a href="{{ route('bhw.update-requests.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Tracking</a>
            </div>
            <div class="grid divide-y divide-slate-200 md:grid-cols-2 md:divide-x md:divide-y-0">
                <div class="p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Draft Packages</h4>
                    <div class="mt-4 space-y-3">
                        @forelse($recentDrafts as $draft)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $draft->draft_reference_code }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $draft->purok?->display_name ?? 'No purok' }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $draft->draft_status_label }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No draft submissions yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Update Requests</h4>
                    <div class="mt-4 space-y-3">
                        @forelse($recentUpdateRequests as $updateRequest)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $updateRequest->subject_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $updateRequest->subject_label }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $updateRequest->request_status_label }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No correction requests yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
