@extends('layouts.admin')

@section('title', 'Municipal Reports Hub - HealthLink Admin')
@section('header', 'Municipal Reports Hub')

@section('content')
    <div class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
        <p class="text-sm leading-6 text-blue-900">
            This hub is reserved for municipality-wide exports and cross-barangay analysis. Printable resident and household forms remain on their individual profile pages so the daily data screens stay clean.
        </p>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Active Barangays</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($activeBarangayCount) }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Covered in Current View</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($coveredBarangayCount) }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Municipal Clinicians</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($municipalClinicianCount) }}</p>
        </div>
    </div>

    <div class="mb-6 rounded-2xl bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="grid gap-4 md:grid-cols-4">
            <div>
                <label for="barangay_id" class="block text-sm font-medium text-slate-700">Barangay</label>
                <select name="barangay_id" id="barangay_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">All barangays</option>
                    @foreach($barangays as $barangay)
                        <option value="{{ $barangay->id }}" {{ (string) request('barangay_id') === (string) $barangay->id ? 'selected' : '' }}>
                            {{ $barangay->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from" class="block text-sm font-medium text-slate-700">Clinical Window From</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFrom->toDateString() }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label for="date_to" class="block text-sm font-medium text-slate-700">Clinical Window To</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateTo->toDateString() }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center rounded-md bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                    Apply Filters
                </button>
                <a href="{{ route('admin.reports.index') }}" class="inline-flex items-center rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="space-y-6">
        @foreach($reports as $report)
            <section class="overflow-hidden rounded-3xl bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tubigon">{{ $report['title'] }}</p>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $report['description'] }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.reports.export', array_merge(['report' => $report['key'], 'format' => 'csv'], request()->query())) }}" class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                CSV
                            </a>
                            <a href="{{ route('admin.reports.export', array_merge(['report' => $report['key'], 'format' => 'xlsx'], request()->query())) }}" class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                Excel
                            </a>
                            <a href="{{ route('admin.reports.export', array_merge(['report' => $report['key'], 'format' => 'pdf'], request()->query())) }}" class="inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                                PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                @foreach($report['columns'] as $label => $key)
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($report['rows'] as $row)
                                <tr>
                                    @foreach($report['columns'] as $label => $key)
                                        @php($value = data_get($row, $key))
                                        <td class="px-6 py-4 text-sm text-slate-700">
                                            {{ is_numeric($value) ? number_format((float) $value) : $value }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($report['columns']) }}" class="px-6 py-6 text-center text-sm text-slate-500">
                                        No records are available for this report with the current filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
