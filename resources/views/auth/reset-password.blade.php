<x-guest-layout
    page-title="HealthLink - Reset Password"
    heading="Choose a new password"
    description="Set a fresh password for your HealthLink account and use it the next time you sign in."
    hero-title="A clean reset, then straight back to work"
    hero-description="After resetting your password, you can sign back in using the same approved role and assignment already tied to your account."
>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" :value="__('Email Address')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="password" :value="__('New Password')" />
                <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full">
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>

    <x-slot:footer>
        <div class="flex items-center justify-between gap-4 text-sm text-slate-600">
            <span>Already remember it?</span>
            <a href="{{ route('login') }}" class="font-semibold text-tubigon transition hover:text-tubigon-hover">Back to login</a>
        </div>
    </x-slot:footer>
</x-guest-layout>
