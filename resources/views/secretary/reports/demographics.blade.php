@extends('layouts.portal')

@section('title', 'Demographic Report - HealthLink Secretary')
@section('header', 'Local Demographic Export')
@section('subheader', 'Generate filtered civil roster reports such as seniors in a specific purok, then export them as CSV, Excel, or PDF.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.reports.demographics.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="inline-flex items-center rounded-full bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            CSV
        </a>
        <a href="{{ route('secretary.reports.demographics.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="inline-flex items-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            Excel
        </a>
        <a href="{{ route('secretary.reports.demographics.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="inline-flex items-center rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
            PDF
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('secretary.reports.demographics') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <div>
                    <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                    <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Entire barangay</option>
                        @foreach($puroks as $purok)
                            <option value="{{ $purok->id }}" {{ (string) request('purok_id') === (string) $purok->id ? 'selected' : '' }}>
                                {{ $purok->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="sex" class="block text-sm font-medium text-slate-700">Sex</label>
                    <select name="sex" id="sex" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All</option>
                        <option value="Male" {{ request('sex') === 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ request('sex') === 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>

                <div>
                    <label for="age_group" class="block text-sm font-medium text-slate-700">Age Group</label>
                    <select name="age_group" id="age_group" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All groups</option>
                        <option value="minor" {{ request('age_group') === 'minor' ? 'selected' : '' }}>Minors (0-17)</option>
                        <option value="adult" {{ request('age_group') === 'adult' ? 'selected' : '' }}>Adults (18-59)</option>
                        <option value="senior" {{ request('age_group') === 'senior' ? 'selected' : '' }}>Seniors (60+)</option>
                    </select>
                </div>

                <div>
                    <label for="resident_status" class="block text-sm font-medium text-slate-700">Civil Registry Status</label>
                    <select name="resident_status" id="resident_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="active" {{ request('resident_status') === 'active' ? 'selected' : '' }}>Active Resident</option>
                        <option value="deceased" {{ request('resident_status') === 'deceased' ? 'selected' : '' }}>Deceased</option>
                        <option value="relocated" {{ request('resident_status') === 'relocated' ? 'selected' : '' }}>Relocated</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Availability</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All records</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="md:col-span-5 flex gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filter</button>
                    <a href="{{ route('secretary.reports.demographics') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Residents</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['residents']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Households</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['households']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Active Residents</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['active']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Deceased</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['deceased']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Relocated</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['relocated']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Seniors</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['seniors']) }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Male</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['male']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Female</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['female']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Minors</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['minors']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Adults</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['adults']) }}</p>
        </div>
    </div>

    <div class="mt-8 overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Purok Breakdown</h3>
            <p class="text-sm text-slate-500">Filtered demographic density by purok under the current report view.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Purok</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Households</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Residents</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Active</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Deceased</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Relocated</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Minors</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Seniors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($byPurok as $row)
                        <tr>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $row['purok'] }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['households']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['residents']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['active']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['deceased']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['relocated']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['minors']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['seniors']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500">No purok breakdown is available for the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8 overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Filtered Roster</h3>
            <p class="text-sm text-slate-500">Export-ready resident list based on the current demographic filter set.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Resident</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Household</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Profile</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Civil Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($residents as $resident)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $resident->formal_name }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $resident->sex }} · Age {{ $resident->age }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $resident->household?->purok?->display_name ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">#{{ $resident->household?->household_no ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $resident->relationship_to_head }}<br>
                                <span class="text-slate-500">{{ $resident->socioEconomicProfile?->occupation ?: 'No occupation' }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <div>{{ $resident->resident_status_label }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $resident->is_active ? 'Active record' : 'Inactive record' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">No resident records match the current demographic filter.</td>
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
