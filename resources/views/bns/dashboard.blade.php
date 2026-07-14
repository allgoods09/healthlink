@extends('layouts.portal')

@section('title', 'BNS Dashboard - HealthLink')
@section('header', 'BNS Dashboard')
@section('subheader', 'Nutrition-only monitoring built on the verified resident pool, with active OPT+ campaigns, official measurements, TCL tracking, and BHW assessment handoffs.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.feeding-programs.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Feeding Programs
        </a>
        <a href="{{ route('bns.maternal.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Maternal Tracking
        </a>
        <a href="{{ route('bns.watchlist.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            View TCL
        </a>
        <a href="{{ route('bns.micronutrients.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Micronutrients
        </a>
        <a href="{{ route('bns.opt-measurements.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Log OPT+ Measurement
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
        <section class="overflow-hidden rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover text-white shadow-xl shadow-tubigon/20">
            <div class="grid gap-6 px-6 py-8 sm:px-8 lg:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/65">Nutrition Operations</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ auth()->user()->assignedBarangay?->name ?? auth()->user()->assignment_label }}</h2>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-white/85">
                        The BNS workspace is now locked to nutrition work only. Verified residents remain the shared source of truth, while campaigns, OPT+ measurements, maternal surveillance, and feeding interventions sit on top of that clean registry.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <span class="inline-flex items-center rounded-full bg-white px-4 py-2 text-sm font-medium text-tubigon">
                            Verified Resident Pool
                        </span>
                        <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white">
                            WHO Standards Embedded
                        </span>
                        <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white">
                            Nutrition-Only Scope Active
                        </span>
                    </div>
                </div>

                <div class="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/65">Attention Needed</p>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-2xl bg-white/8 p-4">
                            <p class="text-sm text-white/70">Target Clients</p>
                            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($targetClientCount) }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/8 p-4">
                            <p class="text-sm text-white/70">Open Assessment Flags</p>
                            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($openAssessmentFlagCount) }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/8 p-4">
                            <p class="text-sm text-white/70">Active OPT+ Campaigns</p>
                            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($activeCampaignCount) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Coverage</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Eligible Children</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($eligibleChildCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Measured This Month</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($measuredThisMonthCount) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Interventions</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Feeding Programs</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($activeFeedingProgramCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Maternal Profiles</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($pregnantResidentCount + $lactatingResidentCount) }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Pregnant</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($pregnantResidentCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Lactating</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($lactatingResidentCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Campaign Periods</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($recentCampaigns->count()) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">TCL / Watchlist</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($targetClientCount) }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Recent OPT+ Measurements</h3>
                    <p class="text-sm text-slate-500">Latest official measurements logged against verified child profiles.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Read Only</span>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($recentMeasurements as $measurement)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $measurement->resident?->full_name ?? 'Unknown child' }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $measurement->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                            · {{ $measurement->measurement_date?->format('M d, Y') }}
                            · {{ number_format($measurement->age_in_months) }} month(s)
                        </p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $measurement->weight_kg }} kg · {{ $measurement->height_cm }} cm
                            @if($measurement->campaignPeriod)
                                · {{ $measurement->campaignPeriod->name }}
                            @endif
                        </p>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No OPT+ measurements have been logged yet.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Build Status</h3>
            </div>
            <div class="space-y-4 p-6 text-sm">
                <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3">
                    <p class="font-semibold text-blue-900">Role boundary cleanup is live</p>
                    <p class="mt-1 text-blue-800">Broad resident, household, team, device, and sync modules were removed from BNS web access.</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="font-semibold text-emerald-900">WHO-based OPT+ engine is live</p>
                    <p class="mt-1 text-emerald-800">Campaign periods, official measurements, and TCL classification now run on embedded WHO growth reference tables for under-five children.</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="font-semibold text-amber-900">Intervention modules are online</p>
                    <p class="mt-1 text-amber-800">Feeding rosters, maternal tracking, and micronutrient logs now sit directly on top of the verified resident pool.</p>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Open BHW Assessment Flags</h3>
                    <p class="text-sm text-slate-500">Child records flagged from the field that still need official BNS assessment.</p>
                </div>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Handoff</span>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($openFlags as $flag)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $flag->resident?->full_name ?? 'Unknown child' }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $flag->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                            · flagged {{ $flag->flagged_at?->diffForHumans() }}
                        </p>
                        <p class="mt-2 text-sm text-slate-600">{{ $flag->flag_reason ?: 'No flag reason was recorded.' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No open child assessment flags are waiting right now.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Maternal Nutrition Watch</h3>
                    <p class="text-sm text-slate-500">Verified residents currently marked pregnant or lactating inside your barangay.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Verified Pool</span>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($maternalProfiles as $profile)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $profile->resident?->full_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $profile->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $profile->is_currently_pregnant ? 'Pregnant' : 'Not pregnant' }}
                            · {{ $profile->is_currently_lactating ? 'Lactating' : 'Not lactating' }}
                        </p>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No maternal nutrition profiles are active yet.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
