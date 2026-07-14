@extends('layouts.portal')

@section('title', 'PHN Follow-Ups - HealthLink')
@section('header', 'Follow-Up Workspace')
@section('subheader', 'Update due, missed, rescheduled, and completed municipal follow-up cases from one focused queue.')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Follow-Up Pulse</p>
            <div class="mt-4 grid grid-cols-3 gap-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Due</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($dueCount) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Missed</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($missedCount) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Completed</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($completedCount) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('phn.follow-ups.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-5">
                <div class="xl:col-span-2">
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Resident or follow-up note" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="barangay_id" class="block text-sm font-medium text-slate-700">Barangay</label>
                    <select id="barangay_id" name="barangay_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All barangays</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="active" @selected(request('status', 'active') === 'active')>Active Queue</option>
                        <option value="rescheduled" @selected(request('status') === 'rescheduled')>Rescheduled</option>
                        <option value="missed" @selected(request('status') === 'missed')>Missed</option>
                        <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        <option value="all" @selected(request('status') === 'all')>All</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply</button>
                    <a href="{{ route('phn.follow-ups.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </section>
    </div>

    <div class="mt-8 space-y-4">
        @forelse($followUps as $encounter)
            <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</h3>
                            <p class="text-sm text-slate-500">
                                {{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                · {{ $encounter->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                                · Encounter {{ $encounter->encountered_at?->format('M d, Y h:i A') }}
                            </p>
                        </div>
                        <a href="{{ route('phn.encounters.show', $encounter) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Encounter</a>
                    </div>
                </div>
                <div class="grid gap-6 p-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="text-sm font-medium text-slate-700">Current Follow-Up Context</p>
                        <div class="mt-3 space-y-2 text-sm text-slate-600">
                            <p>Status: <span class="font-semibold text-slate-900">{{ $encounter->follow_up_status_label }}</span></p>
                            <p>Date: <span class="font-semibold text-slate-900">{{ $encounter->follow_up_date?->format('F j, Y') ?? 'No date set' }}</span></p>
                            <p>Notes: <span class="text-slate-900">{{ $encounter->follow_up_notes ?: 'No follow-up note recorded.' }}</span></p>
                            <p>Assessment: <span class="text-slate-900">{{ $encounter->working_impression ?: ($encounter->consultation_notes ?: 'No clinical summary recorded.') }}</span></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('phn.follow-ups.update', $encounter) }}" class="grid gap-4 md:grid-cols-3 xl:grid-cols-1">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="follow_up_status_{{ $encounter->id }}" class="block text-sm font-medium text-slate-700">Update Status</label>
                            <select id="follow_up_status_{{ $encounter->id }}" name="follow_up_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <option value="due" @selected($encounter->follow_up_status === 'due')>Due</option>
                                <option value="rescheduled" @selected($encounter->follow_up_status === 'rescheduled')>Rescheduled</option>
                                <option value="completed" @selected($encounter->follow_up_status === 'completed')>Completed</option>
                                <option value="missed" @selected($encounter->follow_up_status === 'missed')>Missed</option>
                            </select>
                        </div>
                        <div>
                            <label for="follow_up_date_{{ $encounter->id }}" class="block text-sm font-medium text-slate-700">Follow-Up Date</label>
                            <input type="date" id="follow_up_date_{{ $encounter->id }}" name="follow_up_date" value="{{ optional($encounter->follow_up_date)->format('Y-m-d') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div class="md:col-span-3 xl:col-span-1">
                            <label for="follow_up_notes_{{ $encounter->id }}" class="block text-sm font-medium text-slate-700">Follow-Up Notes</label>
                            <textarea id="follow_up_notes_{{ $encounter->id }}" name="follow_up_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ $encounter->follow_up_notes }}</textarea>
                        </div>
                        <div>
                            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                                Save Follow-Up
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        @empty
            <div class="rounded-[24px] border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                No follow-up cases matched the current filters.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $followUps->links() }}
    </div>
@endsection
