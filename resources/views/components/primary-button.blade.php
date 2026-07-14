<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-tubigon-hover focus:outline-none focus:ring-2 focus:ring-tubigon/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
