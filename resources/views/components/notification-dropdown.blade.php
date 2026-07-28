@props([
    'notifications' => collect(),
    'unreadCount' => 0,
    'theme' => 'portal',
])

<div class="relative" x-data="{ open: false }">
    <button
        type="button"
        @click="open = !open"
        class="{{ $theme === 'admin' ? 'text-gray-500 hover:text-gray-700' : 'text-slate-500 hover:text-tubigon' }} relative rounded-full p-2 transition focus:outline-none focus:ring-2 focus:ring-tubigon/30"
        aria-label="Open notifications"
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unreadCount > 0)
            <span class="absolute -right-1 -top-1 inline-flex min-h-[20px] min-w-[20px] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="absolute right-0 z-50 mt-2 w-[22rem] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
    >
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                <p class="text-xs text-slate-500">{{ number_format($unreadCount) }} unread</p>
            </div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-tubigon transition hover:text-tubigon-hover">
                        Mark all read
                    </button>
                </form>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $notification)
                @php
                    $level = $notification->data['level'] ?? 'info';
                    $dotClass = match ($level) {
                        'success' => 'bg-emerald-500',
                        'warning' => 'bg-amber-500',
                        'error' => 'bg-rose-500',
                        default => 'bg-sky-500',
                    };
                @endphp
                <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left transition hover:bg-slate-50 {{ is_null($notification->read_at) ? 'bg-blue-50/40' : 'bg-white' }}"
                    >
                        <span class="mt-1.5 h-2.5 w-2.5 rounded-full {{ $dotClass }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900">
                                {{ $notification->data['title'] ?? 'HealthLink notification' }}
                            </span>
                            <span class="mt-1 block text-sm leading-5 text-slate-600">
                                {{ $notification->data['body'] ?? '' }}
                            </span>
                            <span class="mt-2 block text-xs text-slate-400">
                                {{ $notification->created_at?->diffForHumans() }}
                            </span>
                        </span>
                    </button>
                </form>
            @empty
                <div class="px-4 py-10 text-center text-sm text-slate-500">
                    No notifications yet.
                </div>
            @endforelse
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            <a href="{{ route('notifications.index') }}" class="inline-flex items-center text-sm font-semibold text-tubigon transition hover:text-tubigon-hover">
                View all notifications
            </a>
        </div>
    </div>
</div>
