<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'HealthLink Portal')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body x-data="sidebarLayout('portal-{{ $user?->role ?? 'default' }}')" class="font-sans antialiased bg-slate-50">
@php
    $user = Auth::user();

    $portalHeading = match ($user?->role) {
        'bns' => 'Nutrition Operations',
        'secretary' => 'Barangay Records',
        'phn' => 'Community Health Oversight',
        'mho' => 'Municipal Health Overview',
        'bhw' => 'Field Records',
        default => 'Role Portal',
    };

    $sidebarSections = match ($user?->role) {
        'bns' => [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('bns.dashboard'), 'active' => request()->routeIs('bns.dashboard'), 'icon' => 'dashboard'],
                ],
            ],
            [
                'label' => 'Nutrition',
                'items' => [
                    ['label' => 'Campaign Periods', 'href' => route('bns.campaign-periods.index'), 'active' => request()->routeIs('bns.campaign-periods.*'), 'icon' => 'sync'],
                    ['label' => 'OPT+ Measurements', 'href' => route('bns.opt-measurements.index'), 'active' => request()->routeIs('bns.opt-measurements.*'), 'icon' => 'metrics'],
                    ['label' => 'TCL / Watchlist', 'href' => route('bns.watchlist.index'), 'active' => request()->routeIs('bns.watchlist.*'), 'icon' => 'audit'],
                ],
            ],
            [
                'label' => 'Next Modules',
                'items' => [
                    ['label' => 'Feeding Programs', 'href' => route('bns.feeding-programs.index'), 'active' => request()->routeIs('bns.feeding-programs.*'), 'icon' => 'household'],
                    ['label' => 'Maternal Tracking', 'href' => route('bns.maternal.index'), 'active' => request()->routeIs('bns.maternal.*'), 'icon' => 'resident'],
                    ['label' => 'Micronutrients', 'href' => route('bns.micronutrients.index'), 'active' => request()->routeIs('bns.micronutrients.*'), 'icon' => 'devices'],
                ],
            ],
        ],
        'secretary' => [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('secretary.dashboard'), 'active' => request()->routeIs('secretary.dashboard'), 'icon' => 'dashboard'],
                ],
            ],
            [
                'label' => 'Civil Registry',
                'items' => [
                    ['label' => 'Residents', 'href' => route('secretary.residents.index'), 'active' => request()->routeIs('secretary.residents.*'), 'icon' => 'resident'],
                    ['label' => 'Households', 'href' => route('secretary.households.index'), 'active' => request()->routeIs('secretary.households.*'), 'icon' => 'household'],
                    ['label' => 'Puroks', 'href' => route('secretary.puroks.index'), 'active' => request()->routeIs('secretary.puroks.*'), 'icon' => 'barangay'],
                    ['label' => 'Certificates', 'href' => route('secretary.certificates.index'), 'active' => request()->routeIs('secretary.certificates.*'), 'icon' => 'metrics'],
                ],
            ],
            [
                'label' => 'Frontline Team',
                'items' => [
                    ['label' => 'Users', 'href' => route('secretary.team.index'), 'active' => request()->routeIs('secretary.team.*') && request('approval_status') !== \App\Models\User::APPROVAL_PENDING, 'icon' => 'users'],
                    ['label' => 'Pending Approvals', 'href' => route('secretary.team.index', ['approval_status' => \App\Models\User::APPROVAL_PENDING]), 'active' => request()->routeIs('secretary.team.*') && request('approval_status') === \App\Models\User::APPROVAL_PENDING, 'icon' => 'users'],
                ],
            ],
            [
                'label' => 'Verification Pipeline',
                'items' => [
                    ['label' => 'Field Drafts', 'href' => route('secretary.drafts.index'), 'active' => request()->routeIs('secretary.drafts.*'), 'icon' => 'household'],
                    ['label' => 'Update Requests', 'href' => route('secretary.update-requests.index'), 'active' => request()->routeIs('secretary.update-requests.*'), 'icon' => 'audit'],
                    ['label' => 'Pending Triage', 'href' => route('secretary.triage.index'), 'active' => request()->routeIs('secretary.triage.*'), 'icon' => 'sync'],
                ],
            ],
            [
                'label' => 'Reports',
                'items' => [
                    ['label' => 'Demographics', 'href' => route('secretary.reports.demographics'), 'active' => request()->routeIs('secretary.reports.demographics*'), 'icon' => 'metrics'],
                    ['label' => 'Activity Feed', 'href' => route('secretary.activity.index'), 'active' => request()->routeIs('secretary.activity.*'), 'icon' => 'sync'],
                ],
            ],
        ],
        'phn' => [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('phn.dashboard'), 'active' => request()->routeIs('phn.dashboard'), 'icon' => 'dashboard'],
                ],
            ],
            [
                'label' => 'Clinical Intake',
                'items' => [
                    ['label' => 'Pending Triage', 'href' => route('phn.triage.index'), 'active' => request()->routeIs('phn.triage.*'), 'icon' => 'sync'],
                    ['label' => 'New Walk-In', 'href' => route('phn.encounters.create'), 'active' => request()->routeIs('phn.encounters.create') && !request('triage_record_id'), 'icon' => 'metrics'],
                    ['label' => 'Consultation Log', 'href' => route('phn.encounters.index'), 'active' => request()->routeIs('phn.encounters.*') && (!request()->routeIs('phn.encounters.create') || request('triage_record_id')), 'icon' => 'audit'],
                    ['label' => 'Follow-Ups', 'href' => route('phn.follow-ups.index'), 'active' => request()->routeIs('phn.follow-ups.*'), 'icon' => 'devices'],
                ],
            ],
            [
                'label' => 'Registry',
                'items' => [
                    ['label' => 'Residents', 'href' => route('phn.residents.index'), 'active' => request()->routeIs('phn.residents.*'), 'icon' => 'resident'],
                    ['label' => 'Correction Requests', 'href' => route('phn.update-requests.index'), 'active' => request()->routeIs('phn.update-requests.*'), 'icon' => 'users'],
                ],
            ],
        ],
        'mho' => [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('mho.dashboard'), 'active' => request()->routeIs('mho.dashboard'), 'icon' => 'dashboard'],
                ],
            ],
            [
                'label' => 'Clinical Oversight',
                'items' => [
                    ['label' => 'Escalation Queue', 'href' => route('mho.escalations.index'), 'active' => (request()->routeIs('mho.escalations.index') && request('status', 'pending') === 'pending') || request()->routeIs('mho.escalations.show') || request()->routeIs('mho.reviews.*'), 'icon' => 'sync'],
                    ['label' => 'Reviewed Cases', 'href' => route('mho.escalations.index', ['status' => 'reviewed']), 'active' => request()->routeIs('mho.escalations.*') && request('status') === 'reviewed', 'icon' => 'audit'],
                    ['label' => 'Open Follow-Ups', 'href' => route('mho.escalations.index', ['status' => 'follow_up']), 'active' => request()->routeIs('mho.escalations.*') && request('status') === 'follow_up', 'icon' => 'devices'],
                ],
            ],
            [
                'label' => 'Registry',
                'items' => [
                    ['label' => 'Residents', 'href' => route('mho.residents.index'), 'active' => request()->routeIs('mho.residents.*'), 'icon' => 'resident'],
                ],
            ],
        ],
        'bhw' => [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('bhw.dashboard'), 'active' => request()->routeIs('bhw.dashboard'), 'icon' => 'dashboard'],
                ],
            ],
            [
                'label' => 'Field Work',
                'items' => [
                    ['label' => 'Campaign Tasks', 'href' => route('bhw.campaigns.index'), 'active' => request()->routeIs('bhw.campaigns.*'), 'icon' => 'metrics'],
                    ['label' => 'Nutrition Flags', 'href' => route('bhw.nutrition-flags.index'), 'active' => request()->routeIs('bhw.nutrition-flags.*'), 'icon' => 'audit'],
                    ['label' => 'Clinic Triage', 'href' => route('bhw.triage.index'), 'active' => request()->routeIs('bhw.triage.*'), 'icon' => 'sync'],
                    ['label' => 'Field Drafts', 'href' => route('bhw.drafts.index'), 'active' => request()->routeIs('bhw.drafts.*'), 'icon' => 'household'],
                    ['label' => 'Update Requests', 'href' => route('bhw.update-requests.index'), 'active' => request()->routeIs('bhw.update-requests.*'), 'icon' => 'users'],
                ],
            ],
            [
                'label' => 'Directory',
                'items' => [
                    ['label' => 'Residents', 'href' => route('bhw.residents.index'), 'active' => request()->routeIs('bhw.residents.*'), 'icon' => 'resident'],
                    ['label' => 'Households', 'href' => route('bhw.households.index'), 'active' => request()->routeIs('bhw.households.*'), 'icon' => 'barangay'],
                ],
            ],
        ],
        default => [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'dashboard'],
                ],
            ],
        ],
    };
@endphp
    <div class="min-h-screen">
        <div x-show="!isDesktop && sidebarOpen" x-cloak @click="closeSidebar()" class="fixed inset-0 z-40 bg-slate-950/30 lg:hidden"></div>

        <aside
               x-show="sidebarOpen"
               x-cloak
               @click.capture="handleNavClick($event)"
               class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col overflow-hidden bg-tubigon shadow-2xl">
            <div class="flex min-h-0 flex-1 flex-col">
                <div class="border-b border-white/15 px-5 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <a href="{{ route('dashboard') }}" class="text-xl font-bold tracking-tight text-white">
                                HealthLink
                            </a>
                            <p class="mt-1 text-sm text-white/70">{{ $portalHeading }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white/90">
                            {{ strtoupper($user->role) }}
                        </span>
                    </div>
                </div>

                <nav x-ref="sidebarScroll" class="sidebar-scrollbar sidebar-scrollbar-brand flex-1 overflow-y-auto px-4 py-5 overscroll-contain">
                    @foreach($sidebarSections as $section)
                        <div class="{{ $loop->first ? '' : 'mt-6 border-t border-white/10 pt-5' }}">
                            <p class="px-4 text-[11px] font-bold uppercase tracking-[0.22em] text-white/55">
                                {{ $section['label'] }}
                            </p>

                            @foreach($section['items'] as $item)
                                <x-sidebar-link
                                    :href="$item['href'] ?? null"
                                    :active="$item['active'] ?? false"
                                    :icon="$item['icon'] ?? ''"
                                    :disabled="$item['disabled'] ?? false"
                                    :badge="$item['badge'] ?? null"
                                    scheme="brand"
                                >
                                    {{ $item['label'] }}
                                </x-sidebar-link>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            </div>

            <div class="border-t border-white/10 bg-tubigon-hover/35 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/12 text-sm font-semibold text-white ring-1 ring-white/10">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                        <p class="truncate text-xs text-white/70">{{ $user->assignment_label }}</p>
                    </div>
                    <button @click="closeSidebar()" class="rounded-full p-1 text-white/60 transition hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close sidebar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <div :class="isDesktop && sidebarOpen ? 'lg:ml-72' : 'lg:ml-0'">
            <nav class="border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-4">
                        <button @click="toggleSidebar()" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-tubigon" aria-label="Toggle sidebar">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">{{ $portalHeading }}</p>
                            <p class="text-sm font-medium text-slate-500">{{ $user->role_label }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('profile.edit') }}" class="hidden rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-tubigon/20 hover:text-tubigon sm:inline-flex">
                            Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-tubigon-hover">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </nav>

            <main class="p-4 sm:p-6 lg:p-8">
                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">@yield('header')</h1>
                        @hasSection('subheader')
                            <p class="mt-1 text-sm text-slate-500">@yield('subheader')</p>
                        @endif
                    </div>
                    <div>
                        @yield('actions')
                    </div>
                </div>

                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="block sm:inline">{{ session('success') }}</span>
                            <button @click="show = false" class="text-emerald-700 transition hover:text-emerald-900">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="block sm:inline">{{ session('error') }}</span>
                            <button @click="show = false" class="text-rose-700 transition hover:text-rose-900">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
