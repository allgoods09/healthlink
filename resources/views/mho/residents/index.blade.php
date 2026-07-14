@extends('layouts.portal')

@section('title', 'MHO Resident Directory - HealthLink')
@section('header', 'Resident Directory')
@section('subheader', 'Municipal read-only resident directory with demographic filters, household context, and nutrition-linked clinical visibility.')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.86fr_1.14fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Directory Snapshot</p>
            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Results</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($residents->total()) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Page Items</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($residents->count()) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Barangays</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($barangays->count()) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Puroks</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($puroks->count()) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('mho.residents.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Resident, code, household, or PhilSys" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
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
                    <label for="sex" class="block text-sm font-medium text-slate-700">Sex</label>
                    <select id="sex" name="sex" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All</option>
                        <option value="Male" @selected(request('sex') === 'Male')>Male</option>
                        <option value="Female" @selected(request('sex') === 'Female')>Female</option>
                    </select>
                </div>
                <div>
                    <label for="resident_status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select id="resident_status" name="resident_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('resident_status') === 'active')>Active</option>
                        <option value="relocated" @selected(request('resident_status') === 'relocated')>Relocated</option>
                        <option value="deceased" @selected(request('resident_status') === 'deceased')>Deceased</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 xl:col-span-6">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply</button>
                    <a href="{{ route('mho.residents.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Household</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Profile</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Nutrition Context</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($residents as $resident)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $resident->formal_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $resident->official_resident_code ?: 'No code assigned' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $resident->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                                <div class="mt-1 text-slate-500">{{ $resident->household?->purok?->display_name ?? 'Unknown purok' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $resident->household?->full_identifier ?? 'No household linked' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <div>{{ $resident->sex }} · Age {{ $resident->age }}</div>
                                <div class="mt-1 text-slate-500">{{ $resident->resident_status_label }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                @if($resident->latestOptMeasurement)
                                    {{ $resident->latestOptMeasurement->measurement_date?->format('M d, Y') }}
                                    <div class="mt-1 text-slate-500">
                                        WFA {{ $resident->latestOptMeasurement->weight_for_age_status }},
                                        HFA {{ $resident->latestOptMeasurement->height_for_age_status }}
                                    </div>
                                @else
                                    <span class="text-slate-500">No OPT+ record yet</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('mho.residents.show', $resident) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No residents matched the current filters.</td>
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
