@extends('layouts.admin')

@section('title', 'Nutrition Oversight - HealthLink Admin')
@section('header', 'Nutrition Oversight')

@section('content')
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
        <p class="font-semibold uppercase tracking-[0.18em] text-emerald-800">Read-Only Oversight</p>
        <p class="mt-2 leading-6">
            This municipal monitor surfaces barangay nutrition risk, OPT+ campaign movement, feeding activity, and maternal surveillance without replacing the BNS workflow.
        </p>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('admin.oversight.nutrition') }}" class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto]">
            <div>
                <label for="barangay_id" class="block text-sm font-medium text-gray-700">Barangay</label>
                <select id="barangay_id" name="barangay_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">All barangays</option>
                    @foreach($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-md bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply</button>
                <a href="{{ route('admin.oversight.nutrition') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
            </div>
        </form>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Active Campaigns</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($activeCampaignCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">OPT+ Cycles</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($activeOptCampaignCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Open Flags</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ number_format($openNutritionFlagCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Target Clients</p>
            <p class="mt-2 text-3xl font-semibold text-amber-600">{{ number_format($targetClientCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Feeding Programs</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($activeFeedingProgramCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Maternal Active Cases</p>
            <p class="mt-2 text-3xl font-semibold text-violet-700">{{ number_format($activeMaternalCaseCount) }}</p>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-[1.08fr_0.92fr]">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Barangay Nutrition Hotspots</h3>
                <p class="mt-1 text-sm text-gray-500">Latest municipal pressure points for flags, malnutrition watchlists, feeding enrollments, and maternal surveillance.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                @php
                    $hotspotPeak = max($hotspotPeak, 1);
                @endphp
                @forelse($barangayHotspots as $row)
                    @php
                        $peakLoad = max($row['open_flag_count'], $row['target_client_count'], $row['active_feeding_enrollment_count'], $row['active_maternal_case_count']);
                        $barWidth = max(($peakLoad / $hotspotPeak) * 100, 6);
                    @endphp
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">{{ $row['barangay']->name }}</p>
                            <span class="text-xs uppercase tracking-[0.18em] text-gray-400">Peak {{ number_format($peakLoad) }}</span>
                        </div>
                        <div class="mt-4 h-2 rounded-full bg-gray-100">
                            <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $barWidth }}%"></div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600 md:grid-cols-4">
                            <div>Open Flags: <span class="font-semibold text-gray-900">{{ number_format($row['open_flag_count']) }}</span></div>
                            <div>Target Clients: <span class="font-semibold text-gray-900">{{ number_format($row['target_client_count']) }}</span></div>
                            <div>Feeding Enrollments: <span class="font-semibold text-gray-900">{{ number_format($row['active_feeding_enrollment_count']) }}</span></div>
                            <div>Maternal Cases: <span class="font-semibold text-gray-900">{{ number_format($row['active_maternal_case_count']) }}</span></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                        No nutrition data is available yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Open Nutrition Flags</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($openFlags as $flag)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $flag->resident?->formal_name ?? 'Unknown resident' }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $flag->barangay?->name ?? 'Unknown barangay' }} · {{ $flag->purok?->display_name ?? 'Unknown purok' }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $flag->flag_reason ?: 'No reason recorded.' }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No open nutrition flags right now.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Maternal Surveillance Snapshot</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($maternalProfiles as $profile)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $profile->resident?->formal_name ?? 'Unknown resident' }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $profile->barangay?->name ?? 'Unknown barangay' }} · {{ $profile->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $profile->status_summary }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No active maternal surveillance cases right now.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Campaign Periods</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentCampaigns as $campaign)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $campaign->name }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $campaign->barangay?->name ?? 'Unknown barangay' }} · {{ $campaign->campaign_type_label }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $campaign->starts_on?->format('M d, Y') }} to {{ $campaign->ends_on?->format('M d, Y') }}</p>
                        <p class="mt-2 text-xs uppercase tracking-[0.18em] text-gray-400">{{ $campaign->opt_measurements_count }} measurements · {{ $campaign->feeding_programs_count }} feeding program(s)</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No nutrition campaign periods found in this scope.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Feeding Programs</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($feedingPrograms as $program)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $program->name }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $program->barangay?->name ?? 'Unknown barangay' }} · {{ $program->program_status_label }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $program->starts_on?->format('M d, Y') }} to {{ $program->ends_on?->format('M d, Y') }}</p>
                        <p class="mt-2 text-xs uppercase tracking-[0.18em] text-gray-400">{{ $program->active_enrollments_count }} active / {{ $program->enrollments_count }} total enrollment(s)</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No feeding programs found in this scope.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

