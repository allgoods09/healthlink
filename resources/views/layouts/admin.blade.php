<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'HealthLink Admin')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body x-data="sidebarLayout('admin')" class="font-sans antialiased bg-gray-100">
    @php
        $isOverrideWorkspace = request()->routeIs([
            'admin.users.*',
            'admin.barangays.*',
            'admin.puroks.*',
            'admin.households.*',
            'admin.residents.*',
        ]);
    @endphp
    <div class="min-h-screen">
        <div x-show="!isDesktop && sidebarOpen" x-cloak @click="closeSidebar()" class="fixed inset-0 z-40 bg-slate-950/30 lg:hidden"></div>

        <aside
               x-show="sidebarOpen"
               x-cloak
               @click.capture="handleNavClick($event)"
               class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col overflow-hidden border-r border-gray-200/80 bg-tubigon-light shadow-md">
            
            <div class="flex min-h-0 flex-1 flex-col">
                <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200/80">
                    <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold text-tubigon tracking-tight">
                        HealthLink
                    </a>
                    <span class="px-2.5 py-0.5 text-xs font-semibold text-white bg-tubigon rounded-full">Admin</span>
                </div>

                <nav x-ref="sidebarScroll" class="sidebar-scrollbar sidebar-scrollbar-admin flex-1 overflow-y-auto px-3 py-4 overscroll-contain">
                    <x-sidebar-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="dashboard">
                        Dashboard
                    </x-sidebar-link>

                    <div class="pt-4 mt-4 border-t border-gray-200/80">
                        <p class="px-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Management</p>
                        
                        <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="users">
                            Users
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.barangays.index')" :active="request()->routeIs('admin.barangays.*')" icon="barangay">
                            Barangays
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.puroks.index')" :active="request()->routeIs('admin.puroks.*')" icon="purok">
                            Puroks
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.households.index')" :active="request()->routeIs('admin.households.*')" icon="household">
                            Households
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.residents.index')" :active="request()->routeIs('admin.residents.*')" icon="resident">
                            Residents
                        </x-sidebar-link>
                    </div>

                    <div class="pt-4 mt-4 border-t border-gray-200/80">
                        <p class="px-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Oversight</p>

                        <x-sidebar-link :href="route('admin.oversight.field')" :active="request()->routeIs('admin.oversight.field')" icon="household">
                            Field Operations
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.oversight.nutrition')" :active="request()->routeIs('admin.oversight.nutrition')" icon="metrics">
                            Nutrition Oversight
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.oversight.clinical')" :active="request()->routeIs('admin.oversight.clinical')" icon="sync">
                            Clinical Oversight
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')" icon="metrics">
                            Reports Hub
                        </x-sidebar-link>
                    </div>

                    <div class="pt-4 mt-4 border-t border-gray-200/80">
                        <p class="px-3 text-xs font-bold text-gray-500 uppercase tracking-wider">System</p>
                        
                        <x-sidebar-link :href="route('admin.audit.index')" :active="request()->routeIs('admin.audit.*')" icon="audit">
                            Audit Trail
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.devices.index')" :active="request()->routeIs('admin.devices.*')" icon="devices">
                            Mobile Devices
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.sync-logs.index')" :active="request()->routeIs('admin.sync-logs.*')" icon="sync">
                            Sync Logs
                        </x-sidebar-link>
                    </div>

                    <div class="pt-4 mt-4 border-t border-gray-200/80 pb-20">
                        <p class="px-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Maintenance</p>
                        
                        <x-sidebar-link :href="route('admin.backups.index')" :active="request()->routeIs('admin.backups.*')" icon="backup">
                            Backups
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.archive.index')" :active="request()->routeIs('admin.archive.*')" icon="archive">
                            Data Archive
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.metrics.index')" :active="request()->routeIs('admin.metrics.*')" icon="metrics">
                            System Metrics
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.rate-limits.index')" :active="request()->routeIs('admin.rate-limits.*')" icon="rate-limit">
                            Rate Limits
                        </x-sidebar-link>
                    </div>
                </nav>
            </div>

            <div class="p-4 border-t border-gray-200/80 bg-tubigon-light/95 backdrop-blur-sm">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full bg-tubigon flex items-center justify-center text-white font-medium text-sm shadow-sm">
                            {{ substr(Auth::user()->name, 0, 2) }}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                    </div>
                    <button @click="closeSidebar()" class="text-gray-400 transition-colors hover:text-tubigon lg:hidden" aria-label="Close sidebar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <div :class="isDesktop && sidebarOpen ? 'lg:ml-64' : 'lg:ml-0'">
            <nav class="bg-white border-b border-gray-200 shadow-sm">
                <div class="px-4 mx-auto sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center">
                            <button @click="toggleSidebar()" class="text-gray-500 hover:text-gray-700 focus:outline-none" aria-label="Toggle sidebar">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center space-x-4">
                            <span class="text-sm font-medium text-gray-700 lg:hidden">
                                @yield('header')
                            </span>

                            <button class="text-gray-500 hover:text-gray-700 relative">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
                            </button>

                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-tubigon">
                                    <div class="w-8 h-8 rounded-full bg-tubigon-light flex items-center justify-center text-tubigon font-medium text-sm">
                                        {{ substr(Auth::user()->name, 0, 2) }}
                                    </div>
                                </button>

                                <div x-show="open" 
                                     @click.away="open = false" 
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="absolute right-0 z-50 w-48 mt-2 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5">
                                    <div class="py-1">
                                        <div class="px-4 py-2 border-b border-gray-100">
                                            <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                                            <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
                                        </div>
                                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Profile
                                        </a>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-4 py-2 text-sm text-left text-red-600 hover:bg-gray-100">
                                                Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="p-4 sm:p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">@yield('header')</h1>
                    <div>
                        @yield('actions')
                    </div>
                </div>

                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 transform translate-y-2"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                        <button @click="show = false" class="absolute top-0 right-0 px-4 py-3">
                            <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 transform translate-y-2"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                        <button @click="show = false" class="absolute top-0 right-0 px-4 py-3">
                            <svg class="w-4 h-4 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                @if($isOverrideWorkspace)
                    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                        <p class="font-semibold uppercase tracking-[0.18em] text-amber-800">Supervisory Override</p>
                        <p class="mt-2 leading-6">
                            This municipal admin workspace is for exceptional intervention, data correction, and continuity support.
                            Routine encoding and day-to-day operations should stay inside the Secretary, BHW, BNS, PHN, and MHO role modules whenever possible.
                        </p>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
