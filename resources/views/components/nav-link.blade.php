@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex h-full items-center border-b-2 border-tubigon px-1 pt-1 text-sm font-semibold leading-5 text-tubigon transition focus:outline-none focus:border-tubigon'
            : 'inline-flex h-full items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-slate-500 transition hover:border-tubigon/30 hover:text-tubigon focus:outline-none focus:border-tubigon/30 focus:text-tubigon';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
