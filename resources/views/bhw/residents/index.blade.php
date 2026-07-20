@extends('layouts.portal')

@section('title', 'BHW Resident Directory - HealthLink')
@section('header', 'Resident Directory')
@section('subheader', 'Barangay-wide read-only search for clinic triage, correction requests, and child nutrition handoff.')

@section('actions')
    <a href="{{ route('bhw.update-requests.create-resident') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        New Resident Update Request
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bhw.residents.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Name or code" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
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
                <div>
                    <label for="resident_status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="resident_status" id="resident_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('resident_status') === 'active')>Active</option>
                        <option value="deceased" @selected(request('resident_status') === 'deceased')>Deceased</option>
                        <option value="relocated" @selected(request('resident_status') === 'relocated')>Relocated</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filters</button>
                    <a href="{{ route('bhw.residents.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Household</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($residents as $resident)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $resident->formal_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $resident->official_resident_code ?? 'No code yet' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ $resident->household?->full_identifier ?? 'Unknown household' }}</p>
                                <p class="mt-1 text-slate-400">{{ $resident->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ ucfirst($resident->resident_status) }}
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('bhw.residents.show', $resident) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                                    <a href="{{ route('bhw.triage.create', ['resident_id' => $resident->id]) }}" class="text-indigo-600 hover:text-indigo-800">Triage</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">No residents match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $residents->links() }}
        </div>
    </div>
@endsection
