@extends('layouts.portal')

@section('title', 'PHN Triage Detail - HealthLink')
@section('header', 'Triage Detail')
@section('subheader', 'Review intake vitals, BHW notes, and nutrition context before opening the consultation record.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        @if($triageRecord->clinicalEncounter)
            <a href="{{ route('phn.encounters.show', $triageRecord->clinicalEncounter) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                Open Encounter
            </a>
        @elseif($triageRecord->triage_status === \App\Models\TriageRecord::STATUS_PENDING && is_null($triageRecord->consumed_at))
            <a href="{{ route('phn.encounters.create', ['triage_record_id' => $triageRecord->id, 'resident_id' => $triageRecord->resident_id]) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                Start Consultation
            </a>
        @endif
        <a href="{{ route('phn.residents.show', $triageRecord->resident) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Resident Profile
        </a>
        <a href="{{ route('phn.triage.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Queue
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $triageRecord->resident?->formal_name ?? 'Unknown resident' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $triageRecord->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · {{ $triageRecord->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Vitals</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">Blood Pressure</dt><dd class="text-slate-900">{{ $triageRecord->bp_systolic && $triageRecord->bp_diastolic ? "{$triageRecord->bp_systolic}/{$triageRecord->bp_diastolic}" : 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Temperature</dt><dd class="text-slate-900">{{ $triageRecord->temperature_celsius ? "{$triageRecord->temperature_celsius} C" : 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Heart Rate</dt><dd class="text-slate-900">{{ $triageRecord->heart_rate ?: 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Respiratory Rate</dt><dd class="text-slate-900">{{ $triageRecord->respiratory_rate ?: 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Blood Glucose</dt><dd class="text-slate-900">{{ $triageRecord->blood_glucose_mg_dl ? "{$triageRecord->blood_glucose_mg_dl} mg/dL" : 'N/A' }}</dd></div>
                    </dl>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Queue Metadata</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">Recorded By</dt><dd class="text-slate-900">{{ $triageRecord->recordedBy?->name ?? 'Unknown BHW' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Measured At</dt><dd class="text-slate-900">{{ $triageRecord->measured_at?->format('F j, Y h:i A') }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Queue Status</dt><dd class="text-slate-900">{{ $triageRecord->triage_status_label }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Consumed By</dt><dd class="text-slate-900">{{ $triageRecord->consumedBy?->name ?? 'Pending PHN review' }}</dd></div>
                    </dl>
                </div>
                <div class="md:col-span-2">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">BHW Notes</h3>
                    <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">
                        {{ $triageRecord->triage_notes ?: 'No triage note was recorded for this intake.' }}
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Resident Context</h3>
                </div>
                <div class="space-y-4 p-6 text-sm">
                    <div>
                        <p class="font-medium text-slate-500">Resident Code</p>
                        <p class="mt-1 text-slate-900">{{ $triageRecord->resident?->official_resident_code ?? 'No resident code' }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-slate-500">Household</p>
                        <p class="mt-1 text-slate-900">{{ $triageRecord->household?->full_identifier ?? 'No household linked' }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-slate-500">Open Nutrition Flags</p>
                        <p class="mt-1 text-slate-900">{{ $triageRecord->resident?->nutritionFlags?->where('flag_status', 'open')->count() ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-slate-500">Latest OPT+</p>
                        <p class="mt-1 text-slate-900">
                            @if($triageRecord->resident?->latestOptMeasurement)
                                {{ $triageRecord->resident->latestOptMeasurement->measurement_date?->format('F j, Y') }}
                                · {{ $triageRecord->resident->latestOptMeasurement->weight_for_age_status }}
                            @else
                                No nutrition measurement recorded yet.
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Nutrition Flag Timeline</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($triageRecord->resident?->nutritionFlags ?? collect() as $flag)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ ucfirst($flag->flag_status) }} flag</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $flag->flagged_at?->format('M d, Y h:i A') }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $flag->flag_reason }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">
                            No BHW-to-BNS nutrition flags are linked to this resident yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
