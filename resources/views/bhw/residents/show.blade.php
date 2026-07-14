@extends('layouts.portal')

@section('title', 'Resident Detail - HealthLink')
@section('header', 'Resident Detail')
@section('subheader', 'Read-only resident profile with direct BHW actions for triage, correction requests, and nutrition handoff.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('bhw.triage.create', ['resident_id' => $resident->id]) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            New Triage Entry
        </a>
        <a href="{{ route('bhw.update-requests.create-resident', ['resident_id' => $resident->id]) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Request Correction
        </a>
        @if($resident->birth_date && $resident->birth_date->greaterThan(now()->subMonths(72)))
            <a href="{{ route('bhw.nutrition-flags.create', ['resident_id' => $resident->id]) }}" class="inline-flex items-center rounded-full border border-amber-200 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50">
                Flag for BNS
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $resident->formal_name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $resident->official_resident_code ?? 'No resident code yet' }}</p>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Household</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $resident->household?->full_identifier ?? 'Unknown household' }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $resident->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Status</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ ucfirst($resident->resident_status) }}</p>
                    </div>
                </div>
                <div class="grid gap-4 border-t border-slate-200 px-6 py-5 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Birth Details</p>
                        <p class="mt-2 text-sm text-slate-700">{{ optional($resident->birth_date)->format('M d, Y') ?? 'Not recorded' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $resident->birth_place }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Contact</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $resident->contact_number ?: 'No contact number' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $resident->email_address ?: 'No email address' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Recent Triage Entries You Logged</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($recentTriage as $triage)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $triage->measured_at?->format('M d, Y h:i A') }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $triage->triage_status_label }}</p>
                            @if($triage->triage_notes)
                                <p class="mt-2 text-sm text-slate-600">{{ $triage->triage_notes }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">No triage entries recorded for this resident yet.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            @if($resident->latestOptMeasurement)
                <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-slate-900">Latest Nutrition Snapshot</h3>
                    </div>
                    <div class="space-y-3 p-6 text-sm text-slate-700">
                        <p>{{ $resident->latestOptMeasurement->measurement_date?->format('M d, Y') }}</p>
                        <p>WFA: {{ $resident->latestOptMeasurement->weight_for_age_status }}</p>
                        <p>HFA: {{ $resident->latestOptMeasurement->height_for_age_status }}</p>
                        <p>WFH/L: {{ $resident->latestOptMeasurement->weight_for_length_height_status }}</p>
                    </div>
                </section>
            @endif

            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Nutrition Handoff Status</h3>
                </div>
                <div class="p-6">
                    @if($openFlag)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            <p class="font-semibold">Open flag is already waiting in the BNS queue.</p>
                            <p class="mt-1">{{ $openFlag->flag_reason }}</p>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">No open BHW-to-BNS flag is currently attached to this resident.</p>
                    @endif
                </div>
            </section>
        </aside>
    </div>
@endsection
