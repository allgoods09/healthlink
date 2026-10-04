@props(['href' => null, 'variant' => 'view', 'size' => 'standard'])

@php
    $variants = [
        'view' => 'border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-900',
        'edit' => 'border-transparent bg-blue-600 text-white hover:bg-blue-700',
        'add' => 'border-transparent bg-teal-600 text-white hover:bg-teal-700',
        'activate' => 'border-green-200 bg-green-50 text-green-600 hover:bg-green-100 hover:text-green-900',
        'deactivate' => 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 hover:text-amber-900',
        'relocate' => 'border-teal-200 bg-teal-50 text-teal-700 hover:bg-teal-100 hover:text-teal-900',
        'back' => 'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200',
        'security' => 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 hover:text-amber-900',
        'document' => 'border-transparent bg-rose-600 text-white hover:bg-rose-700',
    ];
    $geometry = $size === 'compact'
        ? 'min-h-8 px-3 py-1.5 text-xs font-semibold'
        : 'min-h-10 px-4 py-2 text-sm font-medium';
    $styleVariant = $variant === 'manage' ? 'edit' : $variant;
    $classes = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border transition focus:outline-none focus:ring-2 focus:ring-slate-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 '.$geometry.' '.$variants[$styleVariant];
@endphp

@if($href !== null)
    <a href="{{ $href }}" data-record-action="{{ $variant }}" data-record-action-size="{{ $size }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button data-record-action="{{ $variant }}" data-record-action-size="{{ $size }}" {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
