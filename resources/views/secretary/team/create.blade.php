@extends('layouts.portal')

@section('title', 'Add Frontline User - HealthLink')
@section('header', 'Add Frontline User')
@section('subheader', 'Create a BHW or BNS account directly from the barangay hall and keep approval and assignment under secretary control.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.team.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Team
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.85fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Account Details</h3>
            </div>

            <div class="p-6">
                <form method="POST" action="{{ route('secretary.team.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="role" class="block text-sm font-medium text-slate-700">Role</label>
                            <select name="role" id="role" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('role') border-rose-400 @enderror" required>
                                <option value="">Select a role</option>
                                <option value="bhw" {{ old('role') === 'bhw' ? 'selected' : '' }}>Barangay Health Worker</option>
                                <option value="bns" {{ old('role') === 'bns' ? 'selected' : '' }}>Barangay Nutrition Scholar</option>
                            </select>
                            @error('role')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">Assigned Barangay</label>
                            <input type="text" value="{{ auth()->user()->assignedBarangay?->name ?? auth()->user()->assignment_label }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50 text-slate-500 shadow-sm" disabled>
                        </div>

                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-medium text-slate-700">Full Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('name') border-rose-400 @enderror" required>
                            @error('name')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="email" class="block text-sm font-medium text-slate-700">Email Address</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('email') border-rose-400 @enderror" required>
                            @error('email')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="purok-field" class="md:col-span-2">
                            <label for="assigned_purok_id" class="block text-sm font-medium text-slate-700">Assigned Purok</label>
                            <select name="assigned_purok_id" id="assigned_purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('assigned_purok_id') border-rose-400 @enderror">
                                <option value="">Select a purok</option>
                                @foreach($puroks as $purok)
                                    <option value="{{ $purok->id }}" {{ (string) old('assigned_purok_id') === (string) $purok->id ? 'selected' : '' }}>
                                        {{ $purok->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-sm text-slate-500">Required for BHW accounts. BNS accounts remain barangay-scoped.</p>
                            @error('assigned_purok_id')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                            <input type="password" name="password" id="password" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('password') border-rose-400 @enderror" required>
                            @error('password')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                        </div>
                    </div>

                    <input type="hidden" name="is_active" value="0">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                        <span class="ml-2 text-sm text-slate-700">Activate this account immediately</span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                            Create Frontline Account
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">What Happens Next</h3>
            </div>

            <div class="space-y-4 p-6 text-sm text-slate-700">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                    Secretary-created frontline accounts are marked approved right away because they are being issued directly from the barangay hall.
                </div>

                <div>
                    <p class="font-medium text-slate-500">Scope Control</p>
                    <p class="mt-1">This form only works inside your assigned barangay, so frontline accounts cannot be placed outside your jurisdiction.</p>
                </div>

                <div>
                    <p class="font-medium text-slate-500">BHW Assignment</p>
                    <p class="mt-1">BHW accounts must have a specific purok so later field, sync, and household scopes stay isolated.</p>
                </div>

                <div>
                    <p class="font-medium text-slate-500">Audit Trail</p>
                    <p class="mt-1">HealthLink records this account creation in the audit log so admin and barangay oversight remain traceable.</p>
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

        roleSelect.addEventListener('change', syncRoleFormState);
        syncRoleFormState();
    </script>
@endpush
