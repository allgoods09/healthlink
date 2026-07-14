@extends('layouts.admin')

@section('title', 'Clinical Oversight - HealthLink Admin')
@section('header', 'Clinical Oversight')

@section('content')
    <div class="mb-6 rounded-xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-900">
        <p class="font-semibold uppercase tracking-[0.18em] text-violet-800">Read-Only Oversight</p>
        <p class="mt-2 leading-6">
            This municipal monitor tracks the clinical pipeline end to end: BHW triage intake, PHN consultations, overdue follow-ups, and cases still unresolved at the MHO level.
        </p>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('admin.oversight.clinical') }}" class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto]">
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
                <a href="{{ route('admin.oversight.clinical') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
            </div>
        </form>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Pending Triage</p>
            <p class="mt-2 text-3xl font-semibold text-amber-600">{{ number_format($pendingTriageCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Consumed Today</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($triageConsumedTodayCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">PHN Reviewed Today</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($phnReviewedTodayCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Overdue Follow-Ups</p>
            <p class="mt-2 text-3xl font-semibold text-rose-700">{{ number_format($overdueFollowUpCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Active Escalations</p>
            <p class="mt-2 text-3xl font-semibold text-violet-700">{{ number_format($activeEscalationCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">MHO Reviewed Today</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($mhoReviewedTodayCount) }}</p>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-[1.08fr_0.92fr]">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Barangay Clinical Throughput</h3>
                <p class="mt-1 text-sm text-gray-500">Daily movement from BHW intake to PHN review and MHO escalation pressure.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                @php
                    $breakdownPeak = max($breakdownPeak, 1);
                @endphp
                @forelse($barangayBreakdown as $row)
                    @php
                        $peakLoad = max($row['pending_triage_count'], $row['consumed_today_count'], $row['phn_reviewed_today_count'], $row['due_follow_up_count'], $row['active_escalation_count']);
                        $barWidth = max(($peakLoad / $breakdownPeak) * 100, 6);
                    @endphp
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">{{ $row['barangay']->name }}</p>
                            <span class="text-xs uppercase tracking-[0.18em] text-gray-400">Peak {{ number_format($peakLoad) }}</span>
                        </div>
                        <div class="mt-4 h-2 rounded-full bg-gray-100">
                            <div class="h-2 rounded-full bg-violet-500" style="width: {{ $barWidth }}%"></div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600 md:grid-cols-5">
                            <div>Pending Triage: <span class="font-semibold text-gray-900">{{ number_format($row['pending_triage_count']) }}</span></div>
                            <div>Consumed Today: <span class="font-semibold text-gray-900">{{ number_format($row['consumed_today_count']) }}</span></div>
                            <div>PHN Reviewed: <span class="font-semibold text-gray-900">{{ number_format($row['phn_reviewed_today_count']) }}</span></div>
                            <div>Follow-Ups: <span class="font-semibold text-gray-900">{{ number_format($row['due_follow_up_count']) }}</span></div>
                            <div>Escalations: <span class="font-semibold text-gray-900">{{ number_format($row['active_escalation_count']) }}</span></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                        No clinical pipeline data is available yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Pending Triage Queue</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($pendingTriages as $triage)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $triage->resident?->formal_name ?? 'Unknown resident' }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $triage->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · {{ $triage->measured_at?->format('M d, Y h:i A') ?? 'No timestamp' }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $triage->triage_notes ?: 'No triage note recorded.' }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No pending triage records right now.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Active Escalations</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($activeEscalations as $encounter)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · {{ $encounter->attendedBy?->name ?? 'Unknown PHN' }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $encounter->escalation_notes ?: ($encounter->working_impression ?: 'No escalation note recorded.') }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No active escalations right now.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Overdue Follow-Ups</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($dueFollowUps as $encounter)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $encounter->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $encounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · Follow-up {{ $encounter->follow_up_date?->format('M d, Y') ?? 'No date' }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $encounter->follow_up_notes ?: ($encounter->working_impression ?: 'No follow-up note recorded.') }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No overdue follow-up cases right now.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent MHO Reviews</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentMhoReviews as $review)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $review->clinicalEncounter?->resident?->formal_name ?? 'Unknown resident' }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $review->clinicalEncounter?->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }} · {{ $review->reviewedBy?->name ?? 'Unknown MHO' }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $review->final_assessment ?: 'No final assessment recorded.' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No recent MHO reviews in this monitor scope.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
