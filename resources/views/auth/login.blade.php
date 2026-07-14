<x-guest-layout
    page-title="HealthLink - Login"
    heading="Sign in to HealthLink"
    description="Use your approved account to access the right workspace for your role, assignment, and current responsibilities."
    hero-title="One login for the right level of access"
    hero-description="From barangay oversight to field-ready BHW records, HealthLink keeps users inside the scope they are actually assigned to."
>
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email Address')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between gap-4">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-tubigon transition hover:text-tubigon-hover">
                        Forgot password?
                    </a>
                @endif
            </div>

            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label class="inline-flex items-center">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
            <span class="ml-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
        </label>

        <div class="pt-2">
            <x-primary-button class="w-full">
                {{ __('Sign In') }}
            </x-primary-button>
        </div>
    </form>

    <x-slot:footer>
        <div class="flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
            <p>
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold text-tubigon transition hover:text-tubigon-hover">
                    Register as a BHW
                </a>
            </p>
            <p class="text-xs uppercase tracking-[0.22em] text-slate-400">LGU Tubigon · Secure Access</p>
        </div>
    </x-slot:footer>
</x-guest-layout>
