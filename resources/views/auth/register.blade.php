<x-guest-layout
    page-title="HealthLink - Register"
    content-width="max-w-3xl"
    heading="Request a Frontline Account"
    description="Barangay Health Workers and Barangay Nutrition Scholars can self-register here. Your request stays pending until the assigned Barangay Secretary approves and finalizes your local assignment."
    hero-title="Registration starts with barangay verification"
    hero-description="HealthLink keeps new frontline accounts in a pending sandbox until the Barangay Secretary validates the role and assignment, so verified field work stays clean and scoped."
>
    <div class="space-y-6">
        <div class="rounded-lg border border-tubigon/10 bg-tubigon-light px-4 py-3 text-sm text-tubigon">
            After submitting, you must verify your email address first. Your account will still remain pending until the assigned Barangay Secretary validates your role and final local assignment.
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="first_name" :value="__('First Name')" />
                    <input
                        id="first_name"
                        type="text"
                        name="first_name"
                        value="{{ old('first_name') }}"
                        required
                        autofocus
                        autocomplete="given-name"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
                    />
                    <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="last_name" :value="__('Last Name')" />
                    <input
                        id="last_name"
                        type="text"
                        name="last_name"
                        value="{{ old('last_name') }}"
                        required
                        autocomplete="family-name"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
                    />
                    <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="middle_name" :value="__('Middle Name')" />
                    <input
                        id="middle_name"
                        type="text"
                        name="middle_name"
                        value="{{ old('middle_name') }}"
                        autocomplete="additional-name"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
                    />
                    <x-input-error :messages="$errors->get('middle_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="suffix" :value="__('Suffix')" />
                    <input
                        id="suffix"
                        type="text"
                        name="suffix"
                        value="{{ old('suffix') }}"
                        autocomplete="honorific-suffix"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
                    />
                    <x-input-error :messages="$errors->get('suffix')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="email" :value="__('Email Address')" />
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="username"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
                    />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="md:col-start-2">
                    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-tubigon focus:ring-2 focus:ring-tubigon/20"
                    />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="requested_role" :value="__('Requested Role')" />
                    <select
                        id="requested_role"
                        name="requested_role"
                        required
                        class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm focus:border-tubigon focus:ring-2 focus:ring-tubigon/20 @error('requested_role') border-rose-400 @enderror"
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
                        class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm focus:border-tubigon focus:ring-2 focus:ring-tubigon/20 @error('requested_barangay_id') border-rose-400 @enderror"
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

            <label class="inline-flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <input type="checkbox" name="terms" required class="mt-1 rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                <span class="text-sm leading-6 text-slate-600">
                    I confirm that my requested assignment is accurate and understand that account access stays pending until approved.
                </span>
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />

            <div class="pt-2">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-tubigon px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-tubigon-hover focus:outline-none focus:ring-2 focus:ring-tubigon/30 focus:ring-offset-2">
                    {{ __('Submit Registration') }}
                </button>
            </div>
        </form>

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Self-registration is available for <strong>BHW</strong> and <strong>BNS</strong> accounts only. Email verification and secretary approval are both required before the account can start working inside HealthLink.
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
