@extends('layouts.portal')

@section('title', 'Triage Entry Detail - HealthLink')
@section('header', 'Triage Entry Detail')
@section('subheader', 'Review the forwarded clinic triage package and whether it is still editable.')

@section('actions')
    @if($isEditable)
        <a href="{{ route('bhw.triage.edit', $triageRecord) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Edit Triage Entry
        </a>
    @endif
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $triageRecord->resident?->formal_name ?? 'Unknown resident' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $triageRecord->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
            </div>
            <div class="grid gap-4 p-6 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="text-sm text-slate-500">Measured At</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ $triageRecord->measured_at?->format('M d, Y h:i A') }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="text-sm text-slate-500">Status</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ $triageRecord->triage_status_label }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="text-sm text-slate-500">Blood Pressure</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ $triageRecord->bp_systolic && $triageRecord->bp_diastolic ? $triageRecord->bp_systolic . '/' . $triageRecord->bp_diastolic : 'Not recorded' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="text-sm text-slate-500">Heart Rate</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ $triageRecord->heart_rate ?: 'Not recorded' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="text-sm text-slate-500">Temperature</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ $triageRecord->temperature_celsius ? $triageRecord->temperature_celsius . ' C' : 'Not recorded' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="text-sm text-slate-500">Respiratory Rate</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ $triageRecord->respiratory_rate ?: 'Not recorded' }}</p></div>
            </div>
            @if($triageRecord->triage_notes)
                <div class="border-t border-slate-200 px-6 py-5 text-sm text-slate-700">{{ $triageRecord->triage_notes }}</div>
            @endif
        </section>

        <aside class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-lg font-semibold text-slate-900">Clinical Queue State</h3>
            </div>
            <div class="space-y-3 p-6 text-sm text-slate-700">
                <p>Editable: {{ $isEditable ? 'Yes, still awaiting PHN/MHO consumption' : 'No, already consumed by clinical review' }}</p>
                <p>Consumed By: {{ $triageRecord->consumedBy?->name ?? 'Not consumed yet' }}</p>
                <p>Consumed At: {{ $triageRecord->consumed_at?->format('M d, Y h:i A') ?? 'Not consumed yet' }}</p>
            </div>
        </aside>
    </div>
@endsection
