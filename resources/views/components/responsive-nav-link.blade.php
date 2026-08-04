@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-tubigon bg-tubigon-light py-2 pe-4 ps-3 text-start text-base font-semibold text-tubigon transition focus:outline-none focus:border-tubigon focus:bg-tubigon-light'
            : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-slate-600 transition hover:border-tubigon/30 hover:bg-slate-50 hover:text-tubigon focus:outline-none focus:border-tubigon/30 focus:bg-slate-50 focus:text-tubigon';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
