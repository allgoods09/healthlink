@extends($layout)

@section('title', 'Notifications - HealthLink')
@section('header', 'Notifications')
@section('subheader', 'Track approvals, field handoffs, registry updates, clinical escalations, and mobile release notices.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-semibold text-white transition hover:bg-tubigon-hover">
                    Mark All Read
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    <div class="mb-5 flex flex-wrap gap-2">
        <a
            href="{{ route('notifications.index') }}"
            class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ $statusFilter === 'all' ? 'bg-tubigon text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-tubigon/20 hover:text-tubigon' }}"
        >
            All Notifications
        </a>
        <a
            href="{{ route('notifications.index', ['status' => 'unread']) }}"
            class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ $statusFilter === 'unread' ? 'bg-tubigon text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-tubigon/20 hover:text-tubigon' }}"
        >
            Unread Only
        </a>
    </div>

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <p class="text-sm text-slate-500">
                {{ number_format($unreadCount) }} unread notification{{ $unreadCount === 1 ? '' : 's' }} across your HealthLink workspace.
            </p>
        </div>

        <div class="divide-y divide-slate-200">
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
                    <button type="submit" class="flex w-full items-start gap-4 px-6 py-5 text-left transition hover:bg-slate-50 {{ is_null($notification->read_at) ? 'bg-blue-50/30' : '' }}">
                        <span class="mt-1.5 h-3 w-3 rounded-full {{ $dotClass }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-base font-semibold text-slate-900">
                                    {{ $notification->data['title'] ?? 'HealthLink notification' }}
                                </span>
                                @if(is_null($notification->read_at))
                                    <span class="inline-flex items-center rounded-full bg-tubigon/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-tubigon">
                                        New
                                    </span>
                                @endif
                            </span>
                            <span class="mt-2 block text-sm leading-6 text-slate-600">
                                {{ $notification->data['body'] ?? '' }}
                            </span>
                            <span class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                                <span>{{ $notification->created_at?->format('F j, Y \\a\\t g:i A') }}</span>
                                <span>{{ $notification->created_at?->diffForHumans() }}</span>
                                @if(!empty($notification->data['action_label']))
                                    <span class="font-semibold text-tubigon">{{ $notification->data['action_label'] }}</span>
                                @endif
                            </span>
                        </span>
                    </button>
                </form>
            @empty
                <div class="px-6 py-14 text-center text-sm text-slate-500">
                    No notifications are available for this filter yet.
                </div>
            @endforelse
        </div>
    </section>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
@endsection
