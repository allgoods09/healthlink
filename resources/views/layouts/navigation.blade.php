<nav x-data="{ open: false }" class="border-b border-tubigon/20 bg-white shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-7">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 text-tubigon">
                    <img src="{{ asset('images/tubigon-logo.png') }}" alt="Municipality of Tubigon" class="h-10 w-10 object-contain">
                    <span class="hidden sm:block">
                        <span class="block text-sm font-bold leading-4">HealthLink</span>
                        <span class="block text-xs text-slate-500">Municipality of Tubigon</span>
                    </span>
                </a>

                <div class="hidden h-full items-center sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden items-center sm:flex">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-tubigon/30 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20">
                            <span class="max-w-40 truncate">{{ Auth::user()->display_name }}</span>
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <button type="button" @click="open = !open" class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition hover:bg-tubigon-light hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20 sm:hidden" aria-label="Toggle navigation">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak class="border-t border-slate-200 bg-white sm:hidden">
        <div class="space-y-1 px-4 py-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-responsive-nav-link>
        </div>
        <div class="border-t border-slate-200 px-4 py-4">
            <p class="font-semibold text-slate-900">{{ Auth::user()->display_name }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ Auth::user()->email }}</p>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">Profile</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
