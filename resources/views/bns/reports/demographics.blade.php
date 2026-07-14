@extends('layouts.portal')

@section('title', 'Demographic Report - HealthLink')
@section('header', 'Demographic Report')
@section('subheader', 'Barangay-level demographic totals and purok-by-purok breakdowns, ready for export to CSV, Excel, or PDF.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.reports.demographics.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="inline-flex items-center rounded-full bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            CSV
        </a>
        <a href="{{ route('bns.reports.demographics.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="inline-flex items-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            Excel
        </a>
        <a href="{{ route('bns.reports.demographics.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="inline-flex items-center rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
            PDF
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.reports.demographics') }}" class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="w-full md:max-w-sm">
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

                <div class="flex gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filter</button>
                    <a href="{{ route('bns.reports.demographics') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Residents</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['residents']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Households</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['households']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Children</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['children']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Adults</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['adults']) }}</p>
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
            <p class="text-sm text-slate-500">PWD Flags</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['pwd']) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Solo Parents</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($summary['solo_parents']) }}</p>
        </div>
    </div>

    <div class="mt-8 overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Purok Breakdown</h3>
            <p class="text-sm text-slate-500">Demographic totals by purok inside your assigned barangay.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Purok</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Households</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Residents</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Male</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Female</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Children</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Adults</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Seniors</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">PWD</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Solo Parents</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($byPurok as $row)
                        <tr>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $row['purok'] }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['households']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['residents']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['male']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['female']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['children']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['adults']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['seniors']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['pwd']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['solo_parents']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-sm text-slate-500">No demographic records are available for the current filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
