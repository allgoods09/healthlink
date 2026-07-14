@extends('layouts.portal')

@section('title', 'Correction Request Details - HealthLink')
@section('header', 'Correction Request Details')
@section('subheader', 'Compare the current verified record against the proposed field correction before deciding how the change should be applied.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        @if($profileUpdateRequest->request_status === \App\Models\ProfileUpdateRequest::STATUS_PENDING)
            <a href="{{ route('secretary.update-requests.edit', $profileUpdateRequest) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                Review & Apply
            </a>
        @endif
        <a href="{{ route('secretary.update-requests.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Queue
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Request Overview</h3>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Subject Type</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $profileUpdateRequest->subject_label }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Status</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $profileUpdateRequest->request_status_label }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Subject</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $profileUpdateRequest->subject_name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Submitted By</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $profileUpdateRequest->submittedBy?->name ?? 'Unknown user' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Submitted At</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $profileUpdateRequest->created_at->format('F d, Y h:i A') }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Reason</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $profileUpdateRequest->request_reason ?: 'No reason provided.' }}</p>
                </div>
                @if($profileUpdateRequest->review_notes)
                    <div class="md:col-span-2">
                        <p class="text-sm font-medium text-slate-500">Review Notes</p>
                        <p class="mt-1 text-sm text-slate-900">{{ $profileUpdateRequest->review_notes }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Workflow</p>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                Correction requests do not directly overwrite the verified registry. They wait here until the secretary confirms the final values and explicitly applies the approved changes.
            </p>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Current Verified Data</h3>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($currentSnapshot as $row)
                    <div class="flex items-start justify-between gap-4 px-6 py-4">
                        <p class="text-sm font-medium text-slate-500">{{ $row['label'] }}</p>
                        <p class="text-sm text-right text-slate-900">{{ $row['value'] }}</p>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No current snapshot was stored for this request.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Proposed Changes</h3>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($proposedChanges as $row)
                    <div class="flex items-start justify-between gap-4 px-6 py-4">
                        <p class="text-sm font-medium text-slate-500">{{ $row['label'] }}</p>
                        <p class="text-sm text-right text-slate-900">{{ $row['value'] }}</p>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No proposed changes were stored for this request.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
