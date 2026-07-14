@extends('layouts.portal')

@section('title', 'Triage Record Details - HealthLink')
@section('header', 'Triage Record Details')
@section('subheader', 'Barangay-level visibility into field triage data while PHN and MHO remain the clinical consumers of the queue.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.triage.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Queue
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Triage Overview</h3>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Resident</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->resident?->formal_name ?? 'Unknown resident' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Status</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->triage_status_label }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Household</p>
                    <p class="mt-1 text-sm text-slate-900">#{{ $triageRecord->household?->household_no ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Purok</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->purok?->display_name ?? 'Unknown purok' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Recorded By</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->recordedBy?->name ?? 'Unknown BHW' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Measured At</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->measured_at?->format('F d, Y h:i A') }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Consumed By</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->consumedBy?->name ?? 'Waiting for PHN/MHO review' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Consumed At</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->consumed_at?->format('F d, Y h:i A') ?? 'Not yet consumed' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Triage Notes</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $triageRecord->triage_notes ?: 'No triage notes were recorded.' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Vitals Snapshot</h3>
            </div>
            <div class="grid gap-4 p-6 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Blood Pressure</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900">{{ $triageRecord->bp_systolic && $triageRecord->bp_diastolic ? $triageRecord->bp_systolic.'/'.$triageRecord->bp_diastolic : 'N/A' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Heart Rate</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900">{{ $triageRecord->heart_rate ?: 'N/A' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Temperature</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900">{{ $triageRecord->temperature_celsius ? $triageRecord->temperature_celsius.' C' : 'N/A' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Respiratory Rate</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900">{{ $triageRecord->respiratory_rate ?: 'N/A' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                    <p class="text-sm text-slate-500">Blood Glucose</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900">{{ $triageRecord->blood_glucose_mg_dl ? $triageRecord->blood_glucose_mg_dl.' mg/dL' : 'N/A' }}</p>
                </div>
            </div>
        </section>
    </div>
@endsection
