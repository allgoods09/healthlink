@extends('layouts.portal')

@section('title', 'PHN Resident Directory - HealthLink')
@section('header', 'Resident Directory')
@section('subheader', 'Read-only municipal resident lookup with quick routing into consultation, nutrition context, and Secretary correction requests.')

@section('content')
    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('phn.residents.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Resident, household, or code" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
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
                <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                <select id="purok_id" name="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">All puroks</option>
                    @foreach($puroks as $purok)
                        <option value="{{ $purok->id }}" @selected((string) request('purok_id') === (string) $purok->id)>{{ $purok->barangay?->name }} · {{ $purok->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="resident_status" class="block text-sm font-medium text-slate-700">Civil Status</label>
                <select id="resident_status" name="resident_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">All</option>
                    <option value="active" @selected(request('resident_status') === 'active')>Active</option>
                    <option value="deceased" @selected(request('resident_status') === 'deceased')>Deceased</option>
                    <option value="relocated" @selected(request('resident_status') === 'relocated')>Relocated</option>
                </select>
            </div>
            <div>
                <label for="sex" class="block text-sm font-medium text-slate-700">Sex</label>
                <select id="sex" name="sex" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">All</option>
                    <option value="Female" @selected(request('sex') === 'Female')>Female</option>
                    <option value="Male" @selected(request('sex') === 'Male')>Male</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply</button>
                <a href="{{ route('phn.residents.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
            </div>
        </form>
    </div>

    <div class="mt-8 overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Resident</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Assignment</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Latest Nutrition</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($residents as $resident)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $resident->formal_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $resident->official_resident_code ?: 'No resident code' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $resident->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                <div class="mt-1 text-slate-500">{{ $resident->household?->purok?->display_name ?? 'Unknown purok' }} · Household #{{ $resident->household?->household_no ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $resident->resident_status_label }}
                                <div class="mt-1 text-slate-500">{{ $resident->is_active ? 'Active in registry' : 'Inactive in registry' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                @if($resident->latestOptMeasurement)
                                    {{ $resident->latestOptMeasurement->measurement_date?->format('M d, Y') }}
                                    <div class="mt-1 text-slate-500">{{ $resident->latestOptMeasurement->weight_for_age_status }}</div>
                                @else
                                    No OPT+ record
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('phn.residents.show', $resident) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">No residents matched the current filters.</td>
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
