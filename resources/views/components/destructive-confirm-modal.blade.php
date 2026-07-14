@props([
    'action',
    'method' => 'POST',
    'title',
    'description',
    'triggerLabel',
    'triggerClass' => 'inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700',
    'submitLabel' => 'Continue',
    'submitClass' => 'inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700',
    'confirmationWord' => 'CONFIRM',
    'reasonName' => 'action_reason',
    'reasonLabel' => 'Reason for this action',
    'reasonPlaceholder' => 'Explain why this action is necessary.',
])

<div x-data="{ open: false }" class="inline">
    <button type="button" @click="open = true" class="{{ $triggerClass }}">
        {{ $triggerLabel }}
    </button>

    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/40 px-4 py-6"
    >
        <div @click="open = false" class="absolute inset-0"></div>

        <div class="relative z-[91] w-full max-w-lg rounded-3xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-700">Protected Admin Action</p>
                <h3 class="mt-2 text-xl font-semibold text-slate-900">{{ $title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
            </div>

            <form method="POST" action="{{ $action }}" class="space-y-4 px-6 py-5">
                @csrf
                @if(! in_array(strtoupper($method), ['GET', 'POST'], true))
                    @method($method)
                @endif

                {{ $slot }}

                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Type <span class="font-semibold">{{ $confirmationWord }}</span> and explain why this action should happen. The reason will be written into the audit trail.
                </div>

                <div>
                    <label for="confirmation_phrase_{{ md5($action.$triggerLabel) }}" class="block text-sm font-medium text-slate-700">
                        Confirmation Phrase
                    </label>
                    <input
                        type="text"
                        name="confirmation_phrase"
                        id="confirmation_phrase_{{ md5($action.$triggerLabel) }}"
                        class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                        placeholder="{{ $confirmationWord }}"
                        autocomplete="off"
                    >
                </div>

                <div>
                    <label for="reason_{{ md5($action.$triggerLabel) }}" class="block text-sm font-medium text-slate-700">
                        {{ $reasonLabel }}
                    </label>
                    <textarea
                        name="{{ $reasonName }}"
                        id="reason_{{ md5($action.$triggerLabel) }}"
                        rows="4"
                        class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                        placeholder="{{ $reasonPlaceholder }}"
                    >{{ old($reasonName) }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="open = false"
                        class="inline-flex items-center rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200"
                    >
                        Cancel
                    </button>
                    <button type="submit" class="{{ $submitClass }}">
                        {{ $submitLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
