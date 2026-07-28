<x-guest-layout
    page-title="HealthLink - Login"
    content-width="max-w-xl"
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
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
            />
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

            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <label class="inline-flex items-center">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                <span class="ml-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="pt-2">
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-tubigon px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-tubigon-hover focus:outline-none focus:ring-2 focus:ring-tubigon/30 focus:ring-offset-2">
                {{ __('Sign In') }}
            </button>
        </div>
    </form>

    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-semibold text-slate-900">HealthLink BHW Mobile App</p>
                <p class="mt-1 leading-6">
                    Download the Android app for offline field work, local record lookup, and manual sync once the device goes back online.
                </p>
            </div>
            <a
                href="{{ route('mobile.bhw.update') }}"
                class="inline-flex shrink-0 items-center justify-center rounded-lg border border-tubigon bg-white px-4 py-2 text-sm font-semibold text-tubigon transition hover:bg-tubigon-light"
            >
                App Details
            </a>
        </div>
    </div>

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
