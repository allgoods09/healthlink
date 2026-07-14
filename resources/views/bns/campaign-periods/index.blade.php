@extends('layouts.portal')

@section('title', 'BNS Campaign Periods - HealthLink')
@section('header', 'Campaign Periods')
@section('subheader', 'Barangay-scoped nutrition cycles for OPT+ and future intervention modules. Only one active period per campaign type is allowed at a time.')

@section('actions')
    <a href="{{ route('bns.campaign-periods.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        Create Campaign
    </a>
@endsection

@section('content')
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Active Campaigns</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($activeCampaignCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm md:col-span-2">
            <p class="text-sm text-slate-500">Guardrail</p>
            <p class="mt-2 text-sm leading-6 text-slate-700">
                If a campaign period is marked active, any other campaign in the same barangay and campaign type must stay inactive. This keeps BNS measurement and intervention cycles pointed at the correct field period.
            </p>
        </div>
    </div>

    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.campaign-periods.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Campaign name or notes" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="campaign_type" class="block text-sm font-medium text-slate-700">Type</label>
                    <select name="campaign_type" id="campaign_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All campaign types</option>
                        @foreach($campaignTypes as $campaignType => $label)
                            <option value="{{ $campaignType }}" @selected(request('campaign_type') === $campaignType)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="active" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="active" id="active" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="1" @selected(request('active') === '1')>Active</option>
                        <option value="0" @selected(request('active') === '0')>Inactive</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('bns.campaign-periods.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Schedule</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($campaignPeriods as $campaignPeriod)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $campaignPeriod->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $campaignPeriod->campaign_type_label }}</p>
                                @if($campaignPeriod->notes)
                                    <p class="mt-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($campaignPeriod->notes, 110) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $campaignPeriod->starts_on?->format('M d, Y') ?? 'No start date' }}
                                <span class="text-slate-400">to</span>
                                {{ $campaignPeriod->ends_on?->format('M d, Y') ?? 'Open-ended' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ number_format($campaignPeriod->opt_measurements_count) }} OPT+ measurement(s)</p>
                                <p class="mt-1">{{ number_format($campaignPeriod->feeding_programs_count) }} feeding program(s)</p>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $campaignPeriod->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $campaignPeriod->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('bns.campaign-periods.edit', $campaignPeriod) }}" class="text-tubigon hover:text-tubigon-hover">Edit</a>
                                    @if($campaignPeriod->campaign_type === \App\Models\NutritionCampaignPeriod::TYPE_OPT_PLUS)
                                        <a href="{{ route('bns.opt-measurements.index', ['campaign_period_id' => $campaignPeriod->id]) }}" class="text-indigo-600 hover:text-indigo-800">Measurements</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                No campaign periods match the current filters yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $campaignPeriods->links() }}
        </div>
    </div>
@endsection
