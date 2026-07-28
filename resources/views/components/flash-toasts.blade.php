@php
    $toasts = collect([
        session('success') ? ['level' => 'success', 'message' => session('success')] : null,
        session('error') ? ['level' => 'error', 'message' => session('error')] : null,
        session('warning') ? ['level' => 'warning', 'message' => session('warning')] : null,
        session('info') ? ['level' => 'info', 'message' => session('info')] : null,
        session('status') && ! in_array(session('status'), ['verification-link-sent', 'registration-submitted'], true)
            ? ['level' => 'info', 'message' => session('status')]
            : null,
    ])->filter()->values();
@endphp

@if($toasts->isNotEmpty())
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[80] flex justify-center px-4 sm:justify-end sm:px-6">
        <div class="flex w-full max-w-md flex-col gap-3">
            @foreach($toasts as $toast)
                @php
                    $palette = match ($toast['level']) {
                        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
                        'error' => 'border-rose-200 bg-rose-50 text-rose-900',
                        default => 'border-sky-200 bg-sky-50 text-sky-900',
                    };
                @endphp
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 5000)"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="pointer-events-auto rounded-2xl border px-4 py-3 shadow-xl {{ $palette }}"
                    role="alert"
                >
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1 text-sm font-medium leading-6">
                            {{ $toast['message'] }}
                        </div>
                        <button type="button" @click="show = false" class="rounded-full p-1 transition hover:bg-black/5" aria-label="Dismiss notification">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
