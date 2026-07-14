@extends('layouts.portal')

@section('title', 'Reset Frontline Password - HealthLink')
@section('header', 'Reset Password for '.$frontlineUser->name)
@section('subheader', 'Use a custom password or generate a temporary password for secure frontline account recovery.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.team.show', $frontlineUser) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Profile
        </a>
    </div>
@endsection

@section('content')
    @if(session('temporary_password'))
        <div class="mb-6 rounded-[24px] border border-amber-300 bg-amber-50 p-5">
            <p class="text-sm font-semibold text-amber-900">One-Time Temporary Password</p>
            <p class="mt-1 text-sm text-amber-800">This value is shown only once. Share it through a secure channel.</p>
            <div class="mt-3 rounded-2xl bg-white px-4 py-3 font-mono text-lg tracking-wide text-slate-900">
                {{ session('temporary_password') }}
            </div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Set Custom Password</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('secretary.team.password.reset', $frontlineUser) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">New Password</label>
                        <input type="password" name="password" id="password" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('password') border-rose-400 @enderror" required>
                        @error('password')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                    </div>

                    <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Set Password
                    </button>
                </form>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Generate Temporary Password</h3>
            </div>
            <div class="p-6">
                <p class="text-sm leading-7 text-slate-600">
                    Generate a random temporary password for this frontline user. The current password will be replaced immediately.
                </p>

                <form method="POST" action="{{ route('secretary.team.password.generate', $frontlineUser) }}" class="mt-5">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-full bg-amber-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-amber-700">
                        Generate Temporary Password
                    </button>
                </form>

                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Make sure the temporary password reaches the user through a secure channel before they attempt to log in again.
                </div>
            </div>
        </section>
    </div>
@endsection
