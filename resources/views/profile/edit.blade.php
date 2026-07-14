@php
    $user = auth()->user();
    $layout = $user?->role === 'admin' ? 'layouts.admin' : 'layouts.portal';
    $isVerifiable = $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail;
@endphp

@extends($layout)

@section('title', 'Profile - HealthLink')
@section('header', 'Profile')
@section('subheader', 'Keep your account details current, protect your password, and manage your own access safely.')

@section('actions')
    <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-tubigon/20 hover:text-tubigon">
        Back to Dashboard
    </a>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="overflow-hidden rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover text-white shadow-xl shadow-tubigon/20">
            <div class="px-6 py-8 sm:px-8">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-white/65">Account Overview</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $user->name }}</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-white/82">
                    Your profile reflects the role, assignment, and contact details tied to your HealthLink access. Keep these current so approvals, notices, and account recovery continue working cleanly.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white">
                        {{ $user->role_label }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white">
                        {{ $user->assignment_label }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white">
                        {{ $user->is_active ? 'Active account' : 'Inactive account' }}
                    </span>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/65">Email</p>
                        <p class="mt-3 text-sm font-medium text-white">{{ $user->email }}</p>
                        <p class="mt-2 text-xs text-white/70">
                            {{ $isVerifiable && ! $user->hasVerifiedEmail() ? 'Verification still pending' : 'Verified and available for recovery' }}
                        </p>
                    </div>

                    <div class="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/65">Scope</p>
                        <p class="mt-3 text-sm font-medium text-white">{{ ucfirst($user->scope_level) }} access</p>
                        <p class="mt-2 text-xs text-white/70">Access is still constrained by your assigned role and location.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @include('profile.partials.update-profile-information-form')
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @include('profile.partials.update-password-form')
            </section>
        </div>
    </div>

    <section class="mt-6 rounded-[28px] border border-rose-200 bg-white p-6 shadow-sm sm:p-8">
        @include('profile.partials.delete-user-form')
    </section>
@endsection
