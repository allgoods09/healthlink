@extends('layouts.portal')

@section('title', 'BNS OPT+ Measurements - HealthLink')
@section('header', 'OPT+ Measurements')
@section('subheader', 'Official anthropometric measurements logged against verified child profiles using embedded WHO child growth standards.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.opt-measurements.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="inline-flex items-center rounded-full bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            CSV
        </a>
        <a href="{{ route('bns.opt-measurements.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="inline-flex items-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            Excel
        </a>
        <a href="{{ route('bns.opt-measurements.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="inline-flex items-center rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
            PDF
        </a>
        <a href="{{ route('bns.opt-measurements.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Log Measurement
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900 shadow-sm">
        <p class="font-semibold">Official OPT+ scope</p>
        <p class="mt-1 text-blue-800">
            This phase validates official OPT+ measurements for verified children aged 0 to 59 months. Duplicate entries for the same child, date, and campaign period are blocked automatically.
        </p>
    </div>

    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.opt-measurements.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Resident or remarks" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="campaign_period_id" class="block text-sm font-medium text-slate-700">Campaign</label>
                    <select name="campaign_period_id" id="campaign_period_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All OPT+ campaigns</option>
                        @foreach($campaignPeriods as $campaignPeriod)
                            <option value="{{ $campaignPeriod->id }}" @selected((string) request('campaign_period_id') === (string) $campaignPeriod->id)>{{ $campaignPeriod->name }}</option>
                        @endforeach
                    </select>
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
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="checkbox" name="target_client" value="1" @checked(request()->filled('target_client')) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                        <span class="text-sm text-slate-700">TCL only</span>
                    </label>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('bns.opt-measurements.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                        Reset
                    </a>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Measurement</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Statuses</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($measurements as $measurement)
                        <tr class="{{ $measurement->is_target_client ? 'bg-amber-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $measurement->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $measurement->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                                    · {{ $measurement->age_in_months }} month(s)
                                </p>
                                <p class="mt-1 text-xs text-slate-400">{{ $measurement->resident?->official_resident_code }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ $measurement->measurement_date?->format('M d, Y') }}</p>
                                <p class="mt-1">{{ $measurement->weight_kg }} kg · {{ $measurement->height_cm }} cm</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $measurement->measurement_posture_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>WFA: {{ $measurement->weight_for_age_status }}</p>
                                <p class="mt-1">HFA: {{ $measurement->height_for_age_status }}</p>
                                <p class="mt-1">WFH/L: {{ $measurement->weight_for_length_height_status }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ $measurement->campaignPeriod?->name ?? 'No campaign' }}</p>
                                <p class="mt-1 text-xs text-slate-400">Logged by {{ $measurement->measuredBy?->name ?? 'Unknown user' }}</p>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('bns.opt-measurements.show', $measurement) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                No OPT+ measurements match the current filters yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $measurements->links() }}
        </div>
    </div>
@endsection
