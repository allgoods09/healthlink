@extends('layouts.portal')

@section('title', 'OPT+ Measurement Detail - HealthLink')
@section('header', 'OPT+ Measurement Detail')
@section('subheader', 'Official WHO-based nutritional assessment saved under the verified resident pool.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.opt-measurements.create', ['resident_id' => $measurement->resident_id]) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Log Follow-up
        </a>
        <a href="{{ route('bns.opt-measurements.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Measurements
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $measurement->resident?->formal_name ?? 'Unknown resident' }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $measurement->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                    · {{ $measurement->resident?->official_resident_code }}
                </p>
            </div>

            <div class="grid gap-4 p-6 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-500">Measurement Date</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->measurement_date?->format('M d, Y') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-500">Campaign Period</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->campaignPeriod?->name ?? 'No campaign' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-500">Weight</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->weight_kg }} kg</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-500">Length / Height</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->height_cm }} cm</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-500">Age Snapshot</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->age_in_months }} month(s)</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-500">Posture</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->measurement_posture_label }}</p>
                </div>
            </div>

            @if($measurement->remarks)
                <div class="border-t border-slate-200 px-6 py-5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Remarks</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ $measurement->remarks }}</p>
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Computed Nutritional Status</h3>
                </div>
                <div class="space-y-4 p-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Weight-for-Age</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->weight_for_age_status }}</p>
                        <p class="mt-1 text-sm text-slate-500">Z-score: {{ $measurement->weight_for_age_z_score }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Height-for-Age</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->height_for_age_status }}</p>
                        <p class="mt-1 text-sm text-slate-500">Z-score: {{ $measurement->height_for_age_z_score }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Weight-for-Length/Height</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $measurement->weight_for_length_height_status }}</p>
                        <p class="mt-1 text-sm text-slate-500">Z-score: {{ $measurement->weight_for_length_height_z_score }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Target Client Result</h3>
                </div>
                <div class="p-6">
                    @if($measurement->is_target_client)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <p class="font-semibold">This child is on the Target Client List.</p>
                            <p class="mt-2">{{ implode(', ', $measurement->target_client_reasons) }}</p>
                        </div>
                    @else
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            <p class="font-semibold">No undernutrition TCL trigger from this measurement.</p>
                            <p class="mt-1 text-emerald-800">The child is not currently flagged for underweight, stunting, or wasting based on this official entry.</p>
                        </div>
                    @endif

                    <p class="mt-4 text-xs text-slate-500">
                        Logged by {{ $measurement->measuredBy?->name ?? 'Unknown user' }} on {{ $measurement->created_at?->format('M d, Y h:i A') }}.
                    </p>
                </div>
            </section>
        </aside>
    </div>
@endsection
