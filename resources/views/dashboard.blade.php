@extends('layouts.portal')

@section('title', 'HealthLink Portal')
@section('header', auth()->user()->role_label.' Portal')
@section('subheader', 'Role-based tools are being rolled out in phases, starting with barangay oversight and record workflows.')

@section('content')
@php
    $user = auth()->user();

    $buildQueue = match ($user->role) {
        'bns' => [
            'Barangay-wide household and resident oversight',
            'BHW registration approvals and purok assignment controls',
            'Visit log review, corrections, and demographic exports',
        ],
        'secretary' => [
            'Barangay record maintenance and export tools',
            'Shared visibility into household and resident updates',
            'Local administrative workflows connected to field teams',
        ],
        'phn' => [
            'Municipality-wide visibility into barangay performance',
            'Coverage summaries, visit oversight, and trend reporting',
            'Escalation workflows for local record integrity issues',
        ],
        'mho' => [
            'Municipal dashboards, health program visibility, and escalations',
            'Cross-barangay reporting and performance summaries',
            'Oversight tools for local user and record activity',
        ],
        'bhw' => [
            'Web companion access for assigned households and residents',
            'Visit history review and field follow-up coordination',
            'Shared workflows with BNS supervision and approvals',
        ],
        default => [
            'Role-based tools are being prepared for this account.',
        ],
    };
@endphp

<div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
    <section class="overflow-hidden rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover text-white shadow-xl shadow-tubigon/20">
        <div class="grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[1.2fr_0.8fr]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-white/65">Current Access</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ $user->role_label }} web access is active.
                </h2>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-white/80">
                    Your account is ready for the role portal. The next build wave is focused on scoped record work, field-team oversight, and exports that match your assignment.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white">
                        {{ $user->assignment_label }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white">
                        {{ strtoupper($user->role) }}
                    </span>
                </div>
            </div>

            <div class="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/65">Build Queue</p>
                <ul class="mt-4 space-y-3">
                    @foreach($buildQueue as $item)
                        <li class="flex items-start gap-3 text-sm leading-6 text-white/90">
                            <span class="mt-2 h-2 w-2 rounded-full bg-amber-300"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="space-y-6">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Portal Status</p>
            <h3 class="mt-3 text-xl font-semibold tracking-tight text-slate-900">Ready for scoped modules</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                The non-admin shell is now live with a dedicated sidebar, role identity, and room for the modules that come next. As each role portal is implemented, those sidebar slots will turn into active tools instead of placeholders.
            </p>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Next Priority</p>
            <h3 class="mt-3 text-xl font-semibold tracking-tight text-slate-900">
                {{ $user->role === 'bns' ? 'BNS oversight module' : 'Role-specific workspace' }}
            </h3>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                {{ $user->role === 'bns'
                    ? 'This portal is positioned to receive barangay-wide records, BHW supervision, approvals, exports, and future nutrition forms next.'
                    : 'Once your role workflow starts, this layout is already prepared to host the right modules, summaries, and scoped navigation.' }}
            </p>
        </div>
    </section>
</div>
@endsection
