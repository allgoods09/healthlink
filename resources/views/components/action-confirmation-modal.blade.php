<div
    x-data="actionConfirmationModal()"
    x-init="init()"
    @keydown.escape.window="if (open) { $event.preventDefault() }"
    @keydown.tab.window="trapFocus($event)"
>
    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-[1px]"
        aria-live="assertive"
    >
        <section
            x-ref="dialog"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="action-confirmation-title"
            aria-describedby="action-confirmation-description"
            class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        >
            <div class="border-b border-slate-200 px-6 py-5">
                <p x-text="eyebrow" :class="eyebrowClass" class="text-xs font-bold uppercase tracking-[0.18em]"></p>
                <h2 id="action-confirmation-title" x-text="title" class="mt-2 text-xl font-semibold tracking-tight text-slate-950"></h2>
                <p id="action-confirmation-description" x-text="description" class="mt-2 text-sm leading-6 text-slate-600"></p>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div x-show="requiresReason" x-cloak>
                    <label for="action-confirmation-reason" x-text="reasonLabel" class="block text-sm font-semibold text-slate-800"></label>
                    <textarea
                        x-ref="reasonInput"
                        x-model="reason"
                        id="action-confirmation-reason"
                        rows="4"
                        :placeholder="reasonPlaceholder"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-tubigon focus:ring-4 focus:ring-tubigon/10"
                    ></textarea>
                </div>

                <div x-show="confirmationWord" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-medium leading-6 text-amber-950">
                        To continue, type <span class="font-bold" x-text="confirmationWord"></span> below.
                    </p>
                    <input
                        x-ref="phraseInput"
                        x-model="confirmationPhrase"
                        type="text"
                        autocomplete="off"
                        class="mt-3 block w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-amber-600 focus:ring-4 focus:ring-amber-100"
                        :placeholder="confirmationWord"
                    >
                </div>

                <p x-show="errorMessage" x-cloak x-text="errorMessage" class="text-sm font-medium text-rose-700"></p>

                <div class="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
                    <button
                        x-ref="cancelButton"
                        type="button"
                        @click="cancel()"
                        :disabled="isSubmitting"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="confirm()"
                        :disabled="isSubmitting"
                        :class="{
                            'bg-tubigon hover:bg-tubigon-hover focus:ring-tubigon/25': confirmTone === 'primary',
                            'bg-emerald-700 hover:bg-emerald-800 focus:ring-emerald-200': confirmTone === 'success',
                            'bg-amber-700 hover:bg-amber-800 focus:ring-amber-200': confirmTone === 'warning',
                            'bg-rose-700 hover:bg-rose-800 focus:ring-rose-200': confirmTone === 'danger',
                        }"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60"
                        style="min-width: 8rem"
                    >
                        <span x-text="isSubmitting ? 'Please wait...' : confirmLabel"></span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</div>
