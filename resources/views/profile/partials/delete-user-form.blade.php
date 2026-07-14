<section class="space-y-6">
    <header>
        <h2 class="text-xl font-semibold tracking-tight text-slate-900">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-2 text-sm leading-7 text-slate-600">
            {{ __('Deleting your account is permanent. Once removed, all linked access and personal account data tied to this profile will be deleted and cannot be recovered.') }}
        </p>
    </header>

    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-900">
        {{ __('Use this only when you are certain you no longer need this HealthLink account.') }}
    </div>

    <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        {{ __('Delete Account') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <h2 class="text-xl font-semibold tracking-tight text-slate-900">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-3 text-sm leading-7 text-slate-600">
                {{ __('This action cannot be undone. Enter your password to confirm that you want to permanently delete this account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full sm:w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-8 flex flex-wrap justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
