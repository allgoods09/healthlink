@extends('layouts.portal')

@section('title', 'BHW Household Directory - HealthLink')
@section('header', 'Household Directory')
@section('subheader', 'Barangay-wide read-only household lookup for field validation and correction requests.')

@section('actions')
    <a href="{{ route('bhw.update-requests.create-household') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        New Household Update Request
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bhw.households.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Household no or address" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                    <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All puroks</option>
                        @foreach($puroks as $purok)
                            <option value="{{ $purok->id }}" @selected((string) request('purok_id') === (string) $purok->id)>{{ $purok->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filters</button>
                    <a href="{{ route('bhw.households.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Household</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Head</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Residents</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($households as $household)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $household->full_identifier }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $household->official_household_code ?? 'No code yet' }} · {{ $household->purok?->display_name ?? 'Unknown purok' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $household->headResident?->formal_name ?? 'No household head assigned' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ number_format($household->residents_count) }} resident(s)
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('bhw.households.show', $household) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                                    <a href="{{ route('bhw.update-requests.create-household', ['household_id' => $household->id]) }}" class="text-indigo-600 hover:text-indigo-800">Request Update</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">No households match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $households->links() }}
        </div>
    </div>
@endsection
