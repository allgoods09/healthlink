@extends('layouts.portal')

@section('title', 'Visit Details - HealthLink')
@section('header', 'Visit Details')
@section('subheader', 'Integrity notes, visit metadata, and attached photos captured during this household visit.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.visits.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Visit Logs
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Visit Summary</h3>
            </div>
            <div class="space-y-4 p-6 text-sm">
                <div>
                    <p class="font-medium text-slate-500">Visited At</p>
                    <p class="mt-1 text-slate-900">{{ $visit->visited_at?->format('F d, Y h:i A') }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">BHW</p>
                    <p class="mt-1 text-slate-900">{{ $visit->recordedBy?->name ?? 'Unknown BHW' }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">Barangay / Purok</p>
                    <p class="mt-1 text-slate-900">{{ $visit->household?->purok?->barangay?->name }} / {{ $visit->household?->purok?->display_name }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">Household</p>
                    <p class="mt-1 text-slate-900">#{{ $visit->household?->household_no }} · {{ $visit->household?->household_address }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">Source</p>
                    <p class="mt-1 text-slate-900">{{ ucfirst((string) $visit->source) }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">Last Synced</p>
                    <p class="mt-1 text-slate-900">{{ $visit->last_synced_at?->format('F d, Y h:i A') ?? 'Not tagged' }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">Notes</p>
                    <p class="mt-1 leading-7 text-slate-900">{{ $visit->notes ?: 'No notes recorded for this visit.' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Integrity Photos</h3>
            </div>
            <div class="p-6">
                @if(($visit->photos ?? []) !== [])
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($visit->photos as $photo)
                            <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                                <img src="{{ route('bns.visits.photo', [$visit, $loop->index]) }}" alt="Visit photo {{ $loop->iteration }}" class="h-56 w-full object-cover">
                                <figcaption class="space-y-1 px-4 py-3 text-xs text-slate-600">
                                    <p class="font-semibold text-slate-900">{{ $photo['file_name'] ?? 'Captured photo' }}</p>
                                    <p>{{ $photo['captured_at'] ?? 'Capture time unavailable' }}</p>
                                    <p>{{ $photo['mime_type'] ?? 'Unknown type' }}</p>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-10 text-center text-sm text-slate-500">
                        No photos were attached to this visit.
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
