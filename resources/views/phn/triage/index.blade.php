@extends('layouts.portal')

@section('title', 'PHN Triage Queue - HealthLink')
@section('header', 'Pending Triage Queue')
@section('subheader', 'Consume BHW clinic intake across the municipality and convert reviewed entries into formal PHN encounters.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('phn.encounters.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            New Walk-In Encounter
        </a>
        <a href="{{ route('phn.triage.export', ['format' => 'csv'] + request()->query()) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            CSV
        </a>
        <a href="{{ route('phn.triage.export', ['format' => 'xlsx'] + request()->query()) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Excel
        </a>
        <a href="{{ route('phn.triage.export', ['format' => 'pdf'] + request()->query()) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            PDF
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Queue Snapshot</p>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Pending Intake</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($pendingCount) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Reviewed Today</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($reviewedTodayCount) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('phn.triage.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Resident, household, or note" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
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
                    <label for="recorded_by_user_id" class="block text-sm font-medium text-slate-700">BHW</label>
                    <select id="recorded_by_user_id" name="recorded_by_user_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All BHWs</option>
                        @foreach($bhwUsers as $bhw)
                            <option value="{{ $bhw->id }}" @selected((string) request('recorded_by_user_id') === (string) $bhw->id)>{{ $bhw->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="pending" @selected(request('status', 'pending') === 'pending')>Pending</option>
                        <option value="reviewed" @selected(request('status') === 'reviewed')>Reviewed</option>
                        <option value="closed" @selected(request('status') === 'closed')>Closed</option>
                        <option value="all" @selected(request('status') === 'all')>All</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply</button>
                    <a href="{{ route('phn.triage.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">BHW</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Measured</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($triageRecords as $triageRecord)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $triageRecord->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $triageRecord->resident?->official_resident_code ?? 'No resident code' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $triageRecord->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                <div class="mt-1 text-slate-500">{{ $triageRecord->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $triageRecord->recordedBy?->name ?? 'Unknown BHW' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $triageRecord->measured_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $triageRecord->triage_status_label }}</td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('phn.triage.show', $triageRecord) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No triage records matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $triageRecords->links() }}
        </div>
    </div>
@endsection
