@extends('layouts.portal')

@section('title', 'PHN Dashboard - HealthLink')
@section('header', 'PHN Dashboard')
@section('subheader', 'Municipal triage intake, consultation tracking, follow-up monitoring, and escalation oversight across all barangays.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('phn.encounters.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            New Walk-In Encounter
        </a>
        <a href="{{ route('phn.triage.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Open Triage Queue
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover px-6 py-8 text-white shadow-xl shadow-tubigon/20">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/65">Municipal Clinical Oversight</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Tubigon RHU Intake Command</h2>
            <p class="mt-4 max-w-2xl text-sm leading-7 text-white/85">
                The PHN now acts as the primary clinical consumer for BHW triage, municipal walk-ins, and active follow-up cases, with clean escalation paths reserved for the MHO.
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Pending Triage</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($pendingTriageCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Reviewed Today</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($reviewedTodayCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Follow-Ups Due</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($followUpsDueCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Active Escalations</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($activeEscalationsCount) }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Walk-Ins Today</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format($walkInTodayCount) }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Fast Access</p>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('phn.follow-ups.index') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon">
                        <span>Follow-Up Workspace</span>
                        <span>{{ number_format($followUpsDueCount) }}</span>
                    </a>
                    <a href="{{ route('phn.encounters.index', ['status' => 'escalated']) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon">
                        <span>Escalations to MHO</span>
                        <span>{{ number_format($activeEscalationsCount) }}</span>
                    </a>
                    <a href="{{ route('phn.residents.index') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon">
                        <span>Resident Directory</span>
                        <span>Open</span>
                    </a>
                </div>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Clinical Balance</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Queue Pressure</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($pendingTriageCount + $followUpsDueCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Cases Closed Today</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format(max($reviewedTodayCount - $followUpsDueCount, 0)) }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Pending Triage Queue</h3>
                    <p class="text-sm text-slate-500">Fresh BHW intake awaiting PHN review or walk-in conversion.</p>
                </div>
                <a href="{{ route('phn.triage.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Queue</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($pendingTriages as $triage)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $triage->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $triage->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                    · {{ $triage->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">{{ $triage->triage_notes ?: 'No BHW note added.' }}</p>
                            </div>
                            <span class="whitespace-nowrap text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $triage->measured_at?->format('M d h:i A') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No pending triage records are waiting right now.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Follow-Ups Due</h3>
                    <p class="text-sm text-slate-500">Clinical cases that need the next contact cycle.</p>
                </div>
                <a href="{{ route('phn.follow-ups.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Manage</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($followUpsDue as $encounter)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                    · {{ $encounter->follow_up_date?->format('F j, Y') ?? 'No date set' }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">{{ $encounter->follow_up_notes ?: ($encounter->working_impression ?: 'No follow-up note yet.') }}</p>
                            </div>
                            <span class="whitespace-nowrap text-xs font-semibold uppercase tracking-[0.18em] text-amber-600">{{ $encounter->follow_up_status_label }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No due follow-ups are stacked right now.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Reviewed Today</h3>
                    <p class="text-sm text-slate-500">Most recent consultations completed from the PHN desk.</p>
                </div>
                <a href="{{ route('phn.encounters.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Log</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($reviewedToday as $encounter)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · {{ $encounter->encounter_source_label }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $encounter->working_impression ?: ($encounter->consultation_notes ?: 'No assessment note captured.') }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No PHN encounters have been logged yet today.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Barangay Workload Breakdown</h3>
                <p class="text-sm text-slate-500">Live municipal pressure points across queue intake, reviews, due follow-ups, and escalations.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                @php
                    $workloadPeak = max($workloadPeak, 1);
                @endphp
                @if($workloadBreakdown->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-10 text-center text-sm text-slate-500">
                        No active barangays are available to summarize yet.
                    </div>
                @else
                    @foreach($workloadBreakdown as $row)
                        @php
                            $peakLoad = max($row['pending_triage_count'], $row['reviewed_today_count'], $row['follow_up_due_count'], $row['active_escalation_count']);
                            $barWidth = max(($peakLoad / $workloadPeak) * 100, 6);
                        @endphp
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $row['barangay']->name }}</p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ number_format($row['active_residents_count']) }} active residents</p>
                                </div>
                                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Peak {{ number_format($peakLoad) }}</span>
                            </div>
                            <div class="mt-4 h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-tubigon" style="width: {{ $barWidth }}%"></div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-600 md:grid-cols-4">
                                <div>Pending: <span class="font-semibold text-slate-900">{{ number_format($row['pending_triage_count']) }}</span></div>
                                <div>Reviewed: <span class="font-semibold text-slate-900">{{ number_format($row['reviewed_today_count']) }}</span></div>
                                <div>Due: <span class="font-semibold text-slate-900">{{ number_format($row['follow_up_due_count']) }}</span></div>
                                <div>Escalated: <span class="font-semibold text-slate-900">{{ number_format($row['active_escalation_count']) }}</span></div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>
    </div>
@endsection
