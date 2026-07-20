@extends('layouts.portal')

@section('title', 'Manage Frontline User - HealthLink')
@section('header', 'Manage Frontline User')
@section('subheader', 'Keep the role, barangay scope, approval state, and purok assignment aligned with the official secretary workflow.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.team.show', $frontlineUser) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            View Profile
        </a>
        <a href="{{ route('secretary.team.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Team
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Assignment Controls</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('secretary.team.update', $frontlineUser) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Full Name</label>
                            <input type="text" value="{{ $frontlineUser->name }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50 text-slate-500 shadow-sm" disabled>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">Email</label>
                            <input type="text" value="{{ $frontlineUser->email }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50 text-slate-500 shadow-sm" disabled>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-slate-700">Role</label>
                            <select name="role" id="role" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('role') border-rose-400 @enderror" required>
                                <option value="bhw" {{ old('role', $frontlineUser->requested_role ?? $frontlineUser->role) === 'bhw' ? 'selected' : '' }}>Barangay Health Worker</option>
                                <option value="bns" {{ old('role', $frontlineUser->requested_role ?? $frontlineUser->role) === 'bns' ? 'selected' : '' }}>Barangay Nutrition Scholar</option>
                            </select>
                            @error('role')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">Assigned Barangay</label>
                            <input type="text" value="{{ auth()->user()->assignedBarangay?->name ?? auth()->user()->assignment_label }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50 text-slate-500 shadow-sm" disabled>
                        </div>

                        <div id="purok-field" class="md:col-span-2">
                            <label for="assigned_purok_id" class="block text-sm font-medium text-slate-700">Assigned Purok</label>
                            <select name="assigned_purok_id" id="assigned_purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('assigned_purok_id') border-rose-400 @enderror">
                                <option value="">Select a purok</option>
                                @foreach($puroks as $purok)
                                    <option value="{{ $purok->id }}" {{ (string) old('assigned_purok_id', $frontlineUser->assigned_purok_id) === (string) $purok->id ? 'selected' : '' }}>
                                        {{ $purok->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-sm text-slate-500">Required for BHW accounts. BNS stays barangay-scoped.</p>
                            @error('assigned_purok_id')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $frontlineUser->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                        <span class="ml-2 text-sm text-slate-700">Keep this account active</span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                            Save Assignment
                        </button>
                    </div>
                </form>

                @if($frontlineUser->approval_status === \App\Models\User::APPROVAL_PENDING)
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <form action="{{ route('secretary.team.approve', $frontlineUser) }}" method="POST" onsubmit="return confirm('Approve this registration now?')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                                Approve Registration
                            </button>
                        </form>

                        <form action="{{ route('secretary.team.reject', $frontlineUser) }}" method="POST" onsubmit="return captureRejectionReason(this, '{{ addslashes($frontlineUser->name) }}')">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="approval_notes" value="">
                            <button type="submit" class="inline-flex items-center rounded-full bg-rose-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-rose-700">
                                Reject Registration
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Approval Context</h3>
            </div>
            <div class="space-y-4 p-6 text-sm">
                <div>
                    <p class="font-medium text-slate-500">Current Approval Status</p>
                    <p class="mt-1 text-slate-900">{{ $frontlineUser->approval_status_label }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">Email Verification</p>
                    <p class="mt-1 text-slate-900">{{ $frontlineUser->email_verification_status_label }}</p>
                    @if($frontlineUser->hasVerifiedEmail())
                        <p class="mt-1 text-xs text-slate-500">{{ $frontlineUser->email_verified_at?->format('F d, Y h:i A') }}</p>
                    @endif
                </div>
                <div>
                    <p class="font-medium text-slate-500">Current Role</p>
                    <p class="mt-1 text-slate-900">{{ $frontlineUser->role_label }}</p>
                </div>
                <div>
                    <p class="font-medium text-slate-500">Current Assignment</p>
                    <p class="mt-1 text-slate-900">{{ $frontlineUser->assignedPurok?->display_name ?? 'Barangay-wide / not yet assigned' }}</p>
                </div>
                @if($frontlineUser->approval_notes)
                    <div>
                        <p class="font-medium text-slate-500">Approval Notes</p>
                        <p class="mt-1 text-slate-900">{{ $frontlineUser->approval_notes }}</p>
                    </div>
                @endif
                @unless($frontlineUser->hasVerifiedEmail())
                    <div class="flex flex-wrap items-center gap-3">
                        <form action="{{ route('secretary.team.verification.resend', $frontlineUser) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                                Resend Verification Email
                            </button>
                        </form>

                        <form action="{{ route('secretary.team.verification.mark', $frontlineUser) }}" method="POST" onsubmit="return confirm('Mark this email as verified manually?')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-4 py-2 text-sm font-medium text-amber-900 transition hover:bg-amber-200">
                                Mark as Verified
                            </button>
                        </form>
                    </div>
                @endunless
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                    Keep BHW accounts tied to a specific purok before approval so future field and sync data stay isolated to the right area.
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        const roleSelect = document.getElementById('role');
        const purokField = document.getElementById('purok-field');
        const purokInput = document.getElementById('assigned_purok_id');

        function syncRoleFormState() {
            const requiresPurok = roleSelect.value === 'bhw';

            purokField.classList.toggle('hidden', !requiresPurok);
            purokInput.required = requiresPurok;

            if (!requiresPurok) {
                purokInput.value = '';
            }
        }

        function captureRejectionReason(form, userName) {
            const reason = window.prompt(`Enter a rejection note for ${userName}:`);

            if (!reason) {
                return false;
            }

            form.querySelector('input[name="approval_notes"]').value = reason;

            return true;
        }

        roleSelect.addEventListener('change', syncRoleFormState);
        syncRoleFormState();
    </script>
@endpush
