@extends('layouts.portal')

@section('title', $pageTitle ?? 'Update Request Detail - HealthLink')
@section('header', $pageHeader ?? 'Update Request Detail')
@section('subheader', $pageSubheader ?? 'Review the correction payload you submitted and the Secretary review result.')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $updateRequest->subject_name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $updateRequest->subject_label }} · {{ $updateRequest->request_status_label }}</p>
            </div>
            <div class="space-y-4 p-6 text-sm text-slate-700">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reason</p>
                    <p class="mt-2">{{ $updateRequest->request_reason }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Review Notes</p>
                    <p class="mt-2">{{ $updateRequest->review_notes ?: 'No review notes yet.' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reviewed By</p>
                    <p class="mt-2">{{ $updateRequest->reviewedBy?->name ?? 'Pending Secretary review' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-lg font-semibold text-slate-900">Proposed Changes</h3>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($updateRequest->proposed_changes ?? [] as $field => $value)
                    <div class="grid gap-2 px-6 py-4 md:grid-cols-[180px_1fr]">
                        <p class="text-sm font-semibold text-slate-900">{{ str($field)->replace('_', ' ')->title() }}</p>
                        <p class="text-sm text-slate-600">
                            @if(is_bool($value))
                                {{ $value ? 'Yes' : 'No' }}
                            @elseif(is_array($value))
                                {{ json_encode($value) }}
                            @else
                                {{ $value ?: 'No value provided' }}
                            @endif
                        </p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">No proposed changes were stored on this request.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
