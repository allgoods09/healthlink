@extends('layouts.portal')

@section('title', 'Frontline User Details - HealthLink')
@section('header', 'Frontline User Details')
@section('subheader', 'Review approval readiness, scope assignment, and recent account activity for this local health user.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.team.edit', $frontlineUser) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Manage Assignment
        </a>
        <a href="{{ route('secretary.team.password.edit', $frontlineUser) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Reset Password
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Profile</h3>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Full Name</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Email</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->email }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Email Verification</p>
                    <div class="mt-1">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $frontlineUser->hasVerifiedEmail() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $frontlineUser->email_verification_status_label }}
                        </span>
                        @if($frontlineUser->hasVerifiedEmail())
                            <p class="mt-2 text-xs text-slate-500">{{ $frontlineUser->email_verified_at?->format('F d, Y h:i A') }}</p>
                        @endif
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Role</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->role_label }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Approval</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->approval_status_label }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Assigned Barangay</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->assignedBarangay?->name ?? $frontlineUser->requestedBarangay?->name ?? auth()->user()->assignedBarangay?->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Assigned Purok</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->assignedPurok?->display_name ?? 'Barangay-wide / not yet assigned' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Status</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->is_active ? 'Active' : 'Inactive' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Joined</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->created_at->format('F d, Y h:i A') }}</p>
                </div>
                @if($frontlineUser->approval_notes)
                    <div class="md:col-span-2">
                        <p class="text-sm font-medium text-slate-500">Approval Notes</p>
                        <p class="mt-1 text-sm text-slate-900">{{ $frontlineUser->approval_notes }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Source</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $frontlineUser->registered_via_label }}</p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Barangay Scope</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $frontlineUser->assignedBarangay?->name ?? $frontlineUser->requestedBarangay?->name ?? 'Pending assignment' }}</p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Recent Account Logs</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($recentActivity->count()) }}</p>
            </div>
        </section>
    </div>

    <section class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Verification Controls</h3>
        </div>
        <div class="space-y-4 px-6 py-5">
            <div class="rounded-2xl border {{ $frontlineUser->hasVerifiedEmail() ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} px-4 py-3 text-sm">
                @if($frontlineUser->hasVerifiedEmail())
                    This email address is already verified and ready for password recovery and mobile access.
                @else
                    This email address is still unverified. Self-registered BHW and BNS users must verify their email before mobile access will be granted.
                @endif
            </div>

            @unless($frontlineUser->hasVerifiedEmail())
                <div class="flex flex-wrap items-center gap-3">
                    <form action="{{ route('secretary.team.verification.resend', $frontlineUser) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                            Resend Verification Email
                        </button>
                    </form>

                    <form action="{{ route('secretary.team.verification.mark', $frontlineUser) }}" method="POST" onsubmit="return confirm('Mark this email as verified manually?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-5 py-2.5 text-sm font-medium text-amber-900 transition hover:bg-amber-200">
                            Mark as Verified
                        </button>
                    </form>
                </div>
            @endunless
        </div>
    </section>

    <section class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Recent Account Activity</h3>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse($recentActivity as $log)
                <div class="px-6 py-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $log->event_description }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $log->actor_name }} · {{ $log->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="px-6 py-8 text-sm text-slate-500">No recent account activity has been recorded for this user.</div>
            @endforelse
        </div>
    </section>
@endsection
