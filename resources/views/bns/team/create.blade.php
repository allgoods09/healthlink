@extends('layouts.portal')

@section('title', 'Add BHW - HealthLink')
@section('header', 'Add BHW')
@section('subheader', 'Create a new Barangay Health Worker account directly under your barangay and assign the correct purok from the start.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.team.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Team
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.85fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">BHW Account Details</h3>
            </div>

            <div class="p-6">
                <form method="POST" action="{{ route('bns.team.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
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

                        <div>
                            <label class="block text-sm font-medium text-slate-700">Assigned Barangay</label>
                            <input type="text" value="{{ auth()->user()->assignedBarangay?->name ?? auth()->user()->assignment_label }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50 text-slate-500 shadow-sm" disabled>
                        </div>

                        <div>
                            <label for="assigned_purok_id" class="block text-sm font-medium text-slate-700">Assigned Purok</label>
                            <select name="assigned_purok_id" id="assigned_purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('assigned_purok_id') border-rose-400 @enderror" required>
                                <option value="">Select a purok</option>
                                @foreach($puroks as $purok)
                                    <option value="{{ $purok->id }}" {{ (string) old('assigned_purok_id') === (string) $purok->id ? 'selected' : '' }}>
                                        {{ $purok->display_name }}
                                    </option>
                                @endforeach
                            </select>
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
                        <span class="ml-2 text-sm text-slate-700">Activate this BHW account immediately</span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                            Create BHW Account
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
                    Directly created BHW accounts are marked approved right away because they are being issued by the assigned BNS.
                </div>

                <div>
                    <p class="font-medium text-slate-500">Barangay Scope</p>
                    <p class="mt-1">This form only allows puroks inside your assigned barangay, so the BHW cannot be placed outside your coverage area.</p>
                </div>

                <div>
                    <p class="font-medium text-slate-500">Initial Login</p>
                    <p class="mt-1">The BHW can use this email and password for web login now, and later for mobile access once that role flow is resumed.</p>
                </div>

                <div>
                    <p class="font-medium text-slate-500">Audit Trail</p>
                    <p class="mt-1">HealthLink records this account creation in the audit log so admin and barangay oversight remain traceable.</p>
                </div>
            </div>
        </section>
    </div>
@endsection
