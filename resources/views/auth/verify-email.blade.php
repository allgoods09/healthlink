<x-guest-layout
    page-title="HealthLink - Verify Email"
    heading="Verify your email address"
    description="Before continuing, confirm your email through the verification link we already sent. If it didn’t arrive, request a fresh one here."
    hero-title="Verified email keeps account recovery and notices dependable"
    hero-description="HealthLink uses your email for important verification and recovery steps, so confirming it early reduces friction later."
>
    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="space-y-5">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button class="w-full">
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="w-full rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20 focus:ring-offset-2">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
