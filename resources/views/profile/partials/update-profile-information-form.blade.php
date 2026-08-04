<section class="space-y-6">
    <header>
        <h2 class="text-xl font-semibold tracking-tight text-slate-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-2 text-sm leading-7 text-slate-600">
            {{ __("Update your account's display information and keep the email address attached to your HealthLink access accurate.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('patch')

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="first_name" :value="__('First Name')" />
                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $user->resolved_first_name)" required autofocus autocomplete="given-name" />
                <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
            </div>

            <div>
                <x-input-label for="last_name" :value="__('Last Name')" />
                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $user->resolved_last_name)" required autocomplete="family-name" />
                <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
            </div>

            <div>
                <x-input-label for="middle_name" :value="__('Middle Name')" />
                <x-text-input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full" :value="old('middle_name', $user->resolved_middle_name)" autocomplete="additional-name" />
                <x-input-error class="mt-2" :messages="$errors->get('middle_name')" />
            </div>

            <div>
                <x-input-label for="suffix" :value="__('Suffix')" />
                <x-text-input id="suffix" name="suffix" type="text" class="mt-1 block w-full" :value="old('suffix', $user->resolved_suffix)" autocomplete="honorific-suffix" />
                <x-input-error class="mt-2" :messages="$errors->get('suffix')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="md:col-span-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm text-amber-900">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="font-semibold text-amber-900 underline decoration-amber-400 underline-offset-4 transition hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-emerald-700">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4 pt-2">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-medium text-emerald-700"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
