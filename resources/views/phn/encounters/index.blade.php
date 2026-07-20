@extends('layouts.portal')

@section('title', 'PHN Consultation Log - HealthLink')
@section('header', 'Consultation Log')
@section('subheader', 'Review PHN clinical encounters, export filtered logs, and reopen resident context for follow-up decisions.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('phn.encounters.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            New Walk-In Encounter
        </a>
        <a href="{{ route('phn.encounters.export', ['format' => 'csv'] + request()->query()) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            CSV
        </a>
        <a href="{{ route('phn.encounters.export', ['format' => 'xlsx'] + request()->query()) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Excel
        </a>
        <a href="{{ route('phn.encounters.export', ['format' => 'pdf'] + request()->query()) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            PDF
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Clinical Totals</p>
            <div class="mt-4 grid grid-cols-3 gap-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Active</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($activeCount) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Closed Today</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($closedTodayCount) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Escalated</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($escalatedCount) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('phn.encounters.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Resident, assessment, or household" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
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
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="closed" @selected(request('status') === 'closed')>Closed</option>
                        <option value="escalated" @selected(request('status') === 'escalated')>Escalated</option>
                        <option value="follow_up_due" @selected(request('status') === 'follow_up_due')>Follow-Up Due</option>
                        <option value="follow_up_missed" @selected(request('status') === 'follow_up_missed')>Follow-Up Missed</option>
                    </select>
                </div>
                <div>
                    <label for="source" class="block text-sm font-medium text-slate-700">Source</label>
                    <select id="source" name="source" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All sources</option>
                        <option value="triage" @selected(request('source') === 'triage')>BHW Triage</option>
                        <option value="walk_in" @selected(request('source') === 'walk_in')>Walk-In</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply</button>
                    <a href="{{ route('phn.encounters.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </section>
    </div>

    <div class="mt-8 overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Resident</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Follow-Up</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($encounters as $encounter)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $encounter->encountered_at?->format('M d, Y h:i A') }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                <div class="mt-1 text-slate-500">{{ $encounter->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $encounter->encounter_source_label }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $encounter->clinical_status_label }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $encounter->follow_up_status_label }}</td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('phn.encounters.show', $encounter) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No clinical encounters matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $encounters->links() }}
        </div>
    </div>
@endsection
