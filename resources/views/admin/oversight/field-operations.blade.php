@extends('layouts.admin')

@section('title', 'Field Operations Monitor - HealthLink Admin')
@section('header', 'Field Operations Monitor')

@section('content')
    <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900">
        <p class="font-semibold uppercase tracking-[0.18em] text-blue-800">Read-Only Oversight</p>
        <p class="mt-2 leading-6">
            This municipal monitor is for queue visibility and audit review only. Draft approvals and correction handling should continue inside the Secretary workspace unless a supervisory intervention is necessary.
        </p>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('admin.oversight.field') }}" class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto]">
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
                <a href="{{ route('admin.oversight.field') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
            </div>
        </form>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Pending Draft Packages</p>
            <p class="mt-2 text-3xl font-semibold text-amber-600">{{ number_format($pendingDraftCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Reviewed Drafts Today</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($reviewedDraftTodayCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Pending Correction Requests</p>
            <p class="mt-2 text-3xl font-semibold text-blue-700">{{ number_format($pendingUpdateRequestCount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Reviewed Requests Today</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($reviewedUpdateTodayCount) }}</p>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Barangay Queue Breakdown</h3>
                <p class="mt-1 text-sm text-gray-500">Pending and resolved frontline verification pressure across each barangay.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                @php
                    $breakdownPeak = max($breakdownPeak, 1);
                @endphp
                @forelse($barangayBreakdown as $row)
                    @php
                        $peakLoad = max($row['pending_draft_count'], $row['reviewed_draft_count'], $row['pending_update_count'], $row['reviewed_update_count']);
                        $barWidth = max(($peakLoad / $breakdownPeak) * 100, 6);
                    @endphp
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">{{ $row['barangay']->name }}</p>
                            <span class="text-xs uppercase tracking-[0.18em] text-gray-400">Peak {{ number_format($peakLoad) }}</span>
                        </div>
                        <div class="mt-4 h-2 rounded-full bg-gray-100">
                            <div class="h-2 rounded-full bg-tubigon" style="width: {{ $barWidth }}%"></div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600 md:grid-cols-4">
                            <div>Pending Drafts: <span class="font-semibold text-gray-900">{{ number_format($row['pending_draft_count']) }}</span></div>
                            <div>Reviewed Drafts: <span class="font-semibold text-gray-900">{{ number_format($row['reviewed_draft_count']) }}</span></div>
                            <div>Pending Requests: <span class="font-semibold text-gray-900">{{ number_format($row['pending_update_count']) }}</span></div>
                            <div>Reviewed Requests: <span class="font-semibold text-gray-900">{{ number_format($row['reviewed_update_count']) }}</span></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                        No field operations data is available yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Pending Draft Packages</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($recentPendingDrafts as $draft)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $draft->draft_reference_code }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $draft->barangay?->name ?? 'Unknown barangay' }} · {{ $draft->purok?->display_name ?? 'Unknown purok' }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $draft->resident_drafts_count }} resident draft(s) · {{ $draft->submittedBy?->name ?? 'Unknown submitter' }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No pending draft packages right now.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Pending Correction Requests</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($recentPendingUpdateRequests as $requestItem)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $requestItem->subject_name }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $requestItem->barangay?->name ?? 'Unknown barangay' }} · {{ $requestItem->subject_label }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $requestItem->request_reason ?: 'No request reason recorded.' }}</p>
                            <p class="mt-2 text-xs uppercase tracking-[0.18em] text-gray-400">{{ $requestItem->submittedBy?->name ?? 'Unknown sender' }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No pending correction requests right now.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Recently Reviewed Drafts</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentlyReviewedDrafts as $draft)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $draft->draft_reference_code }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $draft->draft_status_label }} · {{ $draft->barangay?->name ?? 'Unknown barangay' }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $draft->verification_notes ?: 'No review note recorded.' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No reviewed drafts yet in this monitor scope.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Recently Reviewed Requests</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentlyReviewedUpdateRequests as $requestItem)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $requestItem->subject_name }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $requestItem->request_status_label }} · {{ $requestItem->barangay?->name ?? 'Unknown barangay' }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $requestItem->review_notes ?: 'No review note recorded.' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">No reviewed correction requests yet in this monitor scope.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

