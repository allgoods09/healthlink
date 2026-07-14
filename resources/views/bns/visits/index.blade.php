@extends('layouts.portal')

@section('title', 'Visit Logs - HealthLink')
@section('header', 'Visit Logs')
@section('subheader', 'Review synced household visits, check integrity photos, and export barangay field activity.')

@section('actions')
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.visits.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-6">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Notes, household, address" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>

                <div>
                    <label for="user_id" class="block text-sm font-medium text-slate-700">BHW</label>
                    <select name="user_id" id="user_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All BHWs</option>
                        @foreach($bhws as $bhw)
                            <option value="{{ $bhw->id }}" {{ (string) request('user_id') === (string) $bhw->id ? 'selected' : '' }}>{{ $bhw->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                    <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All puroks</option>
                        @foreach($puroks as $purok)
                            <option value="{{ $purok->id }}" {{ (string) request('purok_id') === (string) $purok->id ? 'selected' : '' }}>{{ $purok->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="date_from" class="block text-sm font-medium text-slate-700">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-slate-700">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filters</button>
                    <a href="{{ route('bns.visits.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Visited</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">BHW</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Household</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Notes</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Photos</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Sync</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($visits as $visit)
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $visit->visited_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $visit->recordedBy?->name ?? 'Unknown BHW' }}</p>
                                <p class="text-xs text-slate-500">{{ $visit->recordedBy?->assignedPurok?->display_name ?? 'No purok' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p class="font-semibold text-slate-900">#{{ $visit->household?->household_no }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $visit->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($visit->notes ?: 'No notes recorded.', 70) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $visit->photo_count }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $visit->last_synced_at?->diffForHumans() ?? 'Not tagged' }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('bns.visits.show', $visit) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">No field visits matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $visits->links() }}
        </div>
    </div>
@endsection
