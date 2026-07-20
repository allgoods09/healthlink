<x-guest-layout
    page-title="HealthLink - Pending Approval"
    heading="Registration received"
    description="Your email is verified, and your account is now waiting for barangay approval before HealthLink access is unlocked."
    hero-title="Verified account, pending local approval"
    hero-description="HealthLink separates identity verification from role approval so field access only opens after the right secretary or municipal reviewer confirms your assignment."
>
    <div class="space-y-5">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            Your email address has already been verified successfully.
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
            Your account is still pending approval. Please wait for the assigned Barangay Secretary to review your registration and finalize your HealthLink assignment.
        </div>

        <dl class="grid gap-4 rounded-3xl border border-slate-200 bg-white/80 px-5 py-5 text-sm text-slate-700 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Name</dt>
                <dd class="mt-2 font-medium text-slate-900">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Email</dt>
                <dd class="mt-2 font-medium text-slate-900">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Requested Role</dt>
                <dd class="mt-2 font-medium text-slate-900">{{ \App\Models\User::ROLES[$user->requested_role ?? $user->role] ?? strtoupper($user->requested_role ?? $user->role) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Requested Barangay</dt>
                <dd class="mt-2 font-medium text-slate-900">{{ $user->requestedBarangay?->name ?? 'Pending assignment' }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="w-full rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20 focus:ring-offset-2">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
