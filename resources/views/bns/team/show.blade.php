@extends('layouts.portal')

@section('title', 'BHW Details - HealthLink')
@section('header', 'BHW Details')
@section('subheader', 'Review assignment readiness, recent sync behavior, visits, and approval history for this field worker.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.team.edit', $bhw) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Manage Assignment
        </a>
        <a href="{{ route('bns.team.password.edit', $bhw) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Reset Password
        </a>
        <a href="{{ route('bns.devices.index', ['search' => $bhw->email]) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Device Access
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Profile</h3>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Full Name</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Email</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->email }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Assigned Barangay</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->assignedBarangay?->name ?? auth()->user()->assignedBarangay?->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Assigned Purok</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->assignedPurok?->display_name ?? 'Not yet assigned' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Approval</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->approval_status_label }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Status</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->is_active ? 'Active' : 'Inactive' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Requested Purok</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->requestedPurok?->display_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Joined</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $bhw->created_at->format('F d, Y h:i A') }}</p>
                </div>
                @if($bhw->approval_notes)
                    <div class="md:col-span-2">
                        <p class="text-sm font-medium text-slate-500">Approval Notes</p>
                        <p class="mt-1 text-sm text-slate-900">{{ $bhw->approval_notes }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Device Tokens</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($tokenCount) }}</p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Recent Syncs</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($recentSyncs->count()) }}</p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Recent Visits</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($recentVisits->count()) }}</p>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Recent Sync Activity</h3>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($recentSyncs as $sync)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ ucfirst($sync->status) }} sync on {{ $sync->device_name ?? 'Unknown device' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $sync->created_at->diffForHumans() }} · {{ number_format($sync->records_synced) }} record(s)</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $sync->error_message ?: 'No error recorded.' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No sync history has been logged for this BHW yet.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Recent Field Visits</h3>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($recentVisits as $visit)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">Household #{{ $visit->household?->household_no }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $visit->household?->purok?->display_name ?? 'Unknown purok' }} · {{ $visit->visited_at?->format('M d, Y h:i A') }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($visit->notes ?: 'No notes recorded.', 120) }}</p>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No field visits have been recorded by this BHW yet.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Recent Account Activity</h3>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse($recentActivity as $log)
                <div class="px-6 py-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $log->event_description }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $log->actor_name }} · {{ $log->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="px-6 py-8 text-sm text-slate-500">No recent account activity has been recorded for this BHW.</div>
            @endforelse
        </div>
    </section>
@endsection
