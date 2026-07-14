@extends('layouts.portal')

@section('title', 'MHO Dashboard - HealthLink')
@section('header', 'MHO Dashboard')
@section('subheader', 'Municipal escalation oversight for PHN-endorsed cases, final reviews, referrals, and follow-up pressure across all barangays.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('mho.escalations.index') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Open Escalation Queue
        </a>
        <a href="{{ route('mho.escalations.index', ['status' => 'reviewed']) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Reviewed Cases
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover px-6 py-8 text-white shadow-xl shadow-tubigon/20">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/65">Municipal Clinical Authority</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Tubigon RHU Escalation Command</h2>
            <p class="mt-4 max-w-2xl text-sm leading-7 text-white/85">
                Final municipal review now sits here. PHN escalations, unresolved follow-ups, and inter-barangay workload pressure are consolidated into one clean oversight desk for the MHO.
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Pending Escalations</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($pendingEscalationCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Reviewed Today</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($reviewedTodayCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Closed Today</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($closedTodayCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Open Follow-Ups</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($openFollowUpCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Referrals Logged</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($referralCount) }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Fast Access</p>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('mho.escalations.index', ['status' => 'pending']) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon">
                        <span>Pending Municipal Reviews</span>
                        <span>{{ number_format($pendingEscalationCount) }}</span>
                    </a>
                    <a href="{{ route('mho.escalations.index', ['status' => 'follow_up']) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon">
                        <span>Open Follow-Ups</span>
                        <span>{{ number_format($openFollowUpCount) }}</span>
                    </a>
                    <a href="{{ route('mho.residents.index') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon">
                        <span>Resident Directory</span>
                        <span>Open</span>
                    </a>
                </div>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Clinical Balance</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Municipal Queue Load</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($pendingEscalationCount + $openFollowUpCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Resolved Today</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($closedTodayCount) }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Pending Escalations</h3>
                    <p class="text-sm text-slate-500">PHN-endorsed cases waiting for a municipal decision.</p>
                </div>
                <a href="{{ route('mho.escalations.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Queue</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($pendingEscalations as $encounter)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                    · {{ $encounter->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">{{ $encounter->escalation_notes ?: ($encounter->working_impression ?: 'No escalation note recorded.') }}</p>
                                <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">PHN {{ $encounter->attendedBy?->name ?? 'Unknown' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="whitespace-nowrap text-xs font-semibold uppercase tracking-[0.18em] text-amber-600">{{ $encounter->clinical_status_label }}</p>
                                <a href="{{ route('mho.reviews.create', $encounter) }}" class="mt-3 inline-flex items-center rounded-full bg-tubigon px-3 py-1.5 text-xs font-medium text-white hover:bg-tubigon-hover">
                                    Review Now
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No pending municipal escalations are stacked right now.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Reviewed Today</h3>
                    <p class="text-sm text-slate-500">Freshly finalized municipal decisions and overrides.</p>
                </div>
                <a href="{{ route('mho.escalations.index', ['status' => 'reviewed']) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Reviewed Log</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($reviewedToday as $review)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $review->clinicalEncounter?->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $review->clinicalEncounter?->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                            · {{ $review->reviewed_at?->format('M d, Y h:i A') ?? 'No timestamp' }}
                        </p>
                        <p class="mt-2 text-sm text-slate-600">{{ $review->final_assessment ?: 'No final assessment recorded.' }}</p>
                        <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">
                            {{ $review->reviewedBy?->name ?? 'Unknown MHO' }}
                            · {{ $review->final_disposition ?: 'No disposition recorded' }}
                        </p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No municipal reviews have been logged yet today.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Barangay Workload Breakdown</h3>
            <p class="text-sm text-slate-500">Escalation pressure, municipal reviews, and open follow-up demand across the whole municipality.</p>
        </div>
        <div class="space-y-4 px-6 py-5">
            @php
                $workloadPeak = max($workloadPeak, 1);
            @endphp
            @forelse($workloadBreakdown as $row)
                @php
                    $peakLoad = max($row['pending_escalation_count'], $row['reviewed_today_count'], $row['open_follow_up_count'], $row['closed_today_count']);
                    $barWidth = max(($peakLoad / $workloadPeak) * 100, 6);
                @endphp
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $row['barangay']->name }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">Peak {{ number_format($peakLoad) }}</p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ number_format($row['closed_today_count']) }} closed today</span>
                    </div>
                    <div class="mt-4 h-2 rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-tubigon" style="width: {{ $barWidth }}%"></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-600 md:grid-cols-4">
                        <div>Pending: <span class="font-semibold text-slate-900">{{ number_format($row['pending_escalation_count']) }}</span></div>
                        <div>Reviewed: <span class="font-semibold text-slate-900">{{ number_format($row['reviewed_today_count']) }}</span></div>
                        <div>Follow-Ups: <span class="font-semibold text-slate-900">{{ number_format($row['open_follow_up_count']) }}</span></div>
                        <div>Closed: <span class="font-semibold text-slate-900">{{ number_format($row['closed_today_count']) }}</span></div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-10 text-center text-sm text-slate-500">
                    No barangay workload data is available yet.
                </div>
            @endforelse
        </div>
    </div>
@endsection
