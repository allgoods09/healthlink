<x-guest-layout
    page-title="HealthLink - Register"
    heading="Request a Frontline Account"
    description="Barangay Health Workers and Barangay Nutrition Scholars can self-register here. Your request stays pending until the assigned Barangay Secretary approves and finalizes your local assignment."
    hero-title="Registration starts with barangay verification"
    hero-description="HealthLink keeps new frontline accounts in a pending sandbox until the Barangay Secretary validates the role and assignment, so verified field work stays clean and scoped."
>
    <div
        class="space-y-6"
    >
        <div class="rounded-2xl border border-tubigon/10 bg-tubigon-light px-4 py-3 text-sm text-tubigon">
            Your registration will remain pending until the assigned Barangay Secretary validates your role and final local assignment.
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="name" :value="__('Full Name')" />
                    <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" :value="__('Email Address')" />
                    <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                    <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="requested_role" :value="__('Requested Role')" />
                    <select
                        id="requested_role"
                        name="requested_role"
                        required
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm focus:border-tubigon focus:ring-tubigon @error('requested_role') border-rose-400 @enderror"
                    >
                        <option value="">Select role</option>
                        <option value="bhw" {{ old('requested_role') === 'bhw' ? 'selected' : '' }}>Barangay Health Worker (BHW)</option>
                        <option value="bns" {{ old('requested_role') === 'bns' ? 'selected' : '' }}>Barangay Nutrition Scholar (BNS)</option>
                    </select>
                    <x-input-error :messages="$errors->get('requested_role')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="requested_barangay_id" :value="__('Barangay Assignment')" />
                    <select
                        id="requested_barangay_id"
                        name="requested_barangay_id"
                        required
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm focus:border-tubigon focus:ring-tubigon @error('requested_barangay_id') border-rose-400 @enderror"
                    >
                        <option value="">Select barangay</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}" {{ (string) old('requested_barangay_id') === (string) $barangay->id ? 'selected' : '' }}>
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('requested_barangay_id')" class="mt-2" />
                </div>
            </div>

            <label class="inline-flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <input type="checkbox" name="terms" required class="mt-1 rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                <span class="text-sm leading-6 text-slate-600">
                    I confirm that my requested assignment is accurate and understand that account access stays pending until approved.
                </span>
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />

            <div class="pt-2">
                <x-primary-button class="w-full">
                    {{ __('Submit Registration') }}
                </x-primary-button>
            </div>
        </form>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Self-registration is available for <strong>BHW</strong> and <strong>BNS</strong> accounts only. The Barangay Secretary will finalize the local assignment after review.
        </div>
    </div>

    <x-slot:footer>
        <div class="flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
            <p>
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-tubigon transition hover:text-tubigon-hover">
                    Sign in here
                </a>
            </p>
            <p class="text-xs uppercase tracking-[0.22em] text-slate-400">Pending Until Approved</p>
        </div>
    </x-slot:footer>

</x-guest-layout>
