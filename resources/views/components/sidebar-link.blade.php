@props([
    'href' => null,
    'active' => false,
    'icon' => '',
    'scheme' => 'light',
    'disabled' => false,
    'badge' => null,
])

@php
    $icons = [
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
        'barangay' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
        'purok' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'household' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10.5l9-7 9 7M4 10v10a1 1 0 001 1h14a1 1 0 001-1V10M9 21v-6a1 1 0 011-1h4a1 1 0 011 1v6"/>',
        'resident' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7M8 12h12"/>',
        'audit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
        'devices' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
        'sync' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>',
        'backup' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>',
        'archive' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>',
        'metrics' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>',
        'rate-limit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
    ];
    
    $iconHtml = $icons[$icon] ?? '';

    $baseClasses = 'group flex items-center justify-between px-4 py-2.5 mt-1 text-sm font-medium rounded-xl transition-all duration-150';

    $classes = match (true) {
        $scheme === 'brand' && $disabled => $baseClasses.' cursor-not-allowed text-white/45 border border-white/10 bg-white/5',
        $scheme === 'brand' && $active => $baseClasses.' text-tubigon bg-white shadow-lg ring-2 ring-white/20 font-semibold',
        $scheme === 'brand' => $baseClasses.' text-white/85 hover:text-white hover:bg-white/12',
        $disabled => $baseClasses.' cursor-not-allowed text-gray-400 border border-gray-100 bg-white/40',
        $active => $baseClasses.' text-tubigon bg-white border border-gray-100 shadow-sm font-semibold',
        default => $baseClasses.' text-gray-600 hover:text-tubigon hover:bg-white/60',
    };
@endphp

@if($disabled || blank($href))
    <div class="{{ $classes }}" aria-disabled="true">
        <div class="flex items-center min-w-0">
            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $iconHtml !!}
            </svg>
            <span class="truncate">{{ $slot }}</span>
        </div>

        @if($badge)
            <span class="ml-3 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $scheme === 'brand' ? 'bg-white/12 text-white/75' : 'bg-gray-100 text-gray-500' }}">
                {{ $badge }}
            </span>
        @endif
    </div>
@else
    <a href="{{ $href }}" class="{{ $classes }}">
        <div class="flex items-center min-w-0">
            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $iconHtml !!}
            </svg>
            <span class="truncate">{{ $slot }}</span>
        </div>

        @if($badge)
            <span class="ml-3 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $scheme === 'brand' ? 'bg-white/15 text-white' : 'bg-tubigon-light text-tubigon' }}">
                {{ $badge }}
            </span>
        @elseif($scheme === 'brand' && $active)
            <span class="ml-3 h-2.5 w-2.5 rounded-full bg-tubigon"></span>
        @endif
    </a>
@endif
