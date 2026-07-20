@extends('layouts.portal')

@section('title', 'BHW Triage Queue - HealthLink')
@section('header', 'Clinic Triage Queue')
@section('subheader', 'Review the triage entries you logged and edit them only while they remain unconsumed.')

@section('actions')
    <a href="{{ route('bhw.triage.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        New Triage Entry
    </a>
@endsection

@section('content')
    <div class="mb-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Logged Today</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($todayCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Still Editable</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($editableCount) }}</p>
        </div>
    </div>

    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bhw.triage.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Resident or notes" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="editable" @selected(request('status') === 'editable')>Editable Only</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="reviewed" @selected(request('status') === 'reviewed')>Reviewed</option>
                        <option value="closed" @selected(request('status') === 'closed')>Closed</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filters</button>
                    <a href="{{ route('bhw.triage.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Resident</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Measured At</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($triageRecords as $triageRecord)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $triageRecord->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $triageRecord->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $triageRecord->measured_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $triageRecord->triage_status_label }}</td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('bhw.triage.show', $triageRecord) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                                    @if(is_null($triageRecord->consumed_at))
                                        <a href="{{ route('bhw.triage.edit', $triageRecord) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">No triage records match the current filters.</td>
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
