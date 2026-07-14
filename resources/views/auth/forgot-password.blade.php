<x-guest-layout
    page-title="HealthLink - Forgot Password"
    heading="Reset your password"
    description="Enter the email address tied to your HealthLink account and we’ll send a reset link so you can choose a new password."
    hero-title="Recovery should stay simple and secure"
    hero-description="Password recovery is handled through your registered email so only the right account owner can continue the reset flow."
>
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email Address')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full">
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>

    <x-slot:footer>
        <div class="flex items-center justify-between gap-4 text-sm text-slate-600">
            <span>Need to return to your account page?</span>
            <a href="{{ route('login') }}" class="font-semibold text-tubigon transition hover:text-tubigon-hover">Back to login</a>
        </div>
    </x-slot:footer>
</x-guest-layout>
