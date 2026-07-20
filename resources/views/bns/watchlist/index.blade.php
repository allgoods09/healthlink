@extends('layouts.portal')

@section('title', 'BNS Target Client List - HealthLink')
@section('header', 'TCL / Malnutrition Watchlist')
@section('subheader', 'Latest official undernutrition cases based on verified resident profiles and OPT+ measurements, with open BHW handoff flags shown alongside them.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.watchlist.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="inline-flex items-center rounded-full bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            CSV
        </a>
        <a href="{{ route('bns.watchlist.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="inline-flex items-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            Excel
        </a>
        <a href="{{ route('bns.watchlist.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="inline-flex items-center rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
            PDF
        </a>
        <a href="{{ route('bns.opt-measurements.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Log OPT+ Measurement
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Target Clients</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($watchlistCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Severely Underweight</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($severelyUnderweightCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Stunted</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($stuntedCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Wasted</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($wastedCount) }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.watchlist.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Resident name or code" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="campaign_period_id" class="block text-sm font-medium text-slate-700">Campaign</label>
                    <select name="campaign_period_id" id="campaign_period_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Latest across all campaigns</option>
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
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('bns.watchlist.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Resident</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Latest Measurement</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Risk Drivers</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($watchlist as $measurement)
                        <tr class="bg-amber-50/40">
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
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ implode(', ', $measurement->target_client_reasons) }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    WFA {{ $measurement->weight_for_age_status }} ·
                                    HFA {{ $measurement->height_for_age_status }} ·
                                    WFH/L {{ $measurement->weight_for_length_height_status }}
                                </p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ $measurement->campaignPeriod?->name ?? 'No campaign' }}</p>
                                <p class="mt-1 text-xs text-slate-400">Logged by {{ $measurement->measuredBy?->name ?? 'Unknown user' }}</p>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('bns.opt-measurements.show', $measurement) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                                    <a href="{{ route('bns.opt-measurements.create', ['resident_id' => $measurement->resident_id]) }}" class="text-indigo-600 hover:text-indigo-800">Follow-up</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                No target-client cases match the current filters yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $watchlist->links() }}
        </div>
    </div>

    <div class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Open BHW Assessment Flags</h3>
                <p class="text-sm text-slate-500">Children flagged from the field before an official BNS OPT+ assessment is logged.</p>
            </div>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Handoff Queue</span>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse($openFlags as $flag)
                <div class="px-6 py-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $flag->resident?->formal_name ?? 'Unknown resident' }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $flag->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                        · flagged {{ $flag->flagged_at?->diffForHumans() }}
                    </p>
                    <p class="mt-2 text-sm text-slate-600">{{ $flag->flag_reason ?: 'No field note was recorded.' }}</p>
                </div>
            @empty
                <div class="px-6 py-8 text-sm text-slate-500">No open BHW assessment flags are waiting right now.</div>
            @endforelse
        </div>
    </div>
@endsection
