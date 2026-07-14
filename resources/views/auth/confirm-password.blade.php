<x-guest-layout
    page-title="HealthLink - Confirm Password"
    heading="Confirm your password"
    description="This area is protected. Enter your current password to continue."
    hero-title="Sensitive actions deserve a second check"
    hero-description="HealthLink asks for password confirmation before especially sensitive account actions so only the current account holder can proceed."
>
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full">
                {{ __('Confirm Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
