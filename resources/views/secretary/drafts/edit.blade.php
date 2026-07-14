@extends('layouts.portal')

@section('title', 'Review Field Draft - HealthLink')
@section('header', 'Review Field Draft')
@section('subheader', 'Finalize the official household placement, adjust resident details if needed, and approve the entire package into the verified registry.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.drafts.show', $householdDraft) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            View Draft
        </a>
        <a href="{{ route('secretary.drafts.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Queue
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Approval Form</h3>
            </div>

            <div class="p-6">
                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <p class="font-semibold">Please review the draft approval form.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('secretary.drafts.approve', $householdDraft) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="purok_id" class="block text-sm font-medium text-slate-700">Official Purok</label>
                            <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('purok_id') border-rose-400 @enderror" required>
                                @foreach($puroks as $purok)
                                    <option value="{{ $purok->id }}" {{ (string) old('purok_id', $householdDraft->purok_id) === (string) $purok->id ? 'selected' : '' }}>
                                        {{ $purok->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('purok_id')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="household_no" class="block text-sm font-medium text-slate-700">Official Household No.</label>
                            <input type="text" name="household_no" id="household_no" value="{{ old('household_no') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('household_no') border-rose-400 @enderror" required>
                            @error('household_no')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="household_address" class="block text-sm font-medium text-slate-700">Household Address</label>
                            <textarea name="household_address" id="household_address" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('household_address') border-rose-400 @enderror" required>{{ old('household_address', $householdDraft->household_address) }}</textarea>
                            @error('household_address')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="drinking_water_source" class="block text-sm font-medium text-slate-700">Water Source</label>
                            <input type="text" name="drinking_water_source" id="drinking_water_source" value="{{ old('drinking_water_source', $householdDraft->drinking_water_source) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>

                        <div>
                            <label for="sanitary_toilet_type" class="block text-sm font-medium text-slate-700">Toilet Type</label>
                            <input type="text" name="sanitary_toilet_type" id="sanitary_toilet_type" value="{{ old('sanitary_toilet_type', $householdDraft->sanitary_toilet_type) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>

                        <div>
                            <input type="hidden" name="has_sanitary_toilet" value="0">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="has_sanitary_toilet" value="1" {{ old('has_sanitary_toilet', $householdDraft->has_sanitary_toilet) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                <span class="ml-2 text-sm text-slate-700">Has sanitary toilet</span>
                            </label>
                        </div>

                        <div>
                            <input type="hidden" name="is_social_aid_beneficiary" value="0">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_social_aid_beneficiary" value="1" {{ old('is_social_aid_beneficiary', $householdDraft->is_social_aid_beneficiary) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                <span class="ml-2 text-sm text-slate-700">Social aid beneficiary</span>
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label for="head_draft_id" class="block text-sm font-medium text-slate-700">Head of Household</label>
                            <select name="head_draft_id" id="head_draft_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('head_draft_id') border-rose-400 @enderror">
                                <option value="">Leave household temporarily without a head</option>
                                @foreach($householdDraft->residentDrafts as $residentDraft)
                                    <option value="{{ $residentDraft->id }}" {{ (string) old('head_draft_id', $householdDraft->residentDrafts->firstWhere('is_household_head_candidate', true)?->id) === (string) $residentDraft->id ? 'selected' : '' }}>
                                        {{ $residentDraft->formal_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('head_draft_id')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="verification_notes" class="block text-sm font-medium text-slate-700">Secretary Notes</label>
                            <textarea name="verification_notes" id="verification_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('verification_notes') }}</textarea>
                        </div>
                    </div>

                    <div class="space-y-5">
                        @foreach($householdDraft->residentDrafts as $residentDraft)
                            <div class="rounded-[22px] border border-slate-200 bg-slate-50/70 p-5">
                                <div class="mb-4 flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Resident Draft {{ $loop->iteration }}</p>
                                        <p class="text-sm text-slate-500">{{ $residentDraft->formal_name }}</p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Draft</span>
                                </div>

                                <input type="hidden" name="residents[{{ $loop->index }}][draft_id]" value="{{ old("residents.{$loop->index}.draft_id", $residentDraft->id) }}">

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">PhilSys ID</label>
                                        <input type="text" name="residents[{{ $loop->index }}][philsys_card_no]" value="{{ old("residents.{$loop->index}.philsys_card_no", $residentDraft->philsys_card_no) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Relationship to Head</label>
                                        <input type="text" name="residents[{{ $loop->index }}][relationship_to_head]" value="{{ old("residents.{$loop->index}.relationship_to_head", $residentDraft->relationship_to_head) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Last Name</label>
                                        <input type="text" name="residents[{{ $loop->index }}][last_name]" value="{{ old("residents.{$loop->index}.last_name", $residentDraft->last_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">First Name</label>
                                        <input type="text" name="residents[{{ $loop->index }}][first_name]" value="{{ old("residents.{$loop->index}.first_name", $residentDraft->first_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Middle Name</label>
                                        <input type="text" name="residents[{{ $loop->index }}][middle_name]" value="{{ old("residents.{$loop->index}.middle_name", $residentDraft->middle_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Suffix</label>
                                        <input type="text" name="residents[{{ $loop->index }}][suffix]" value="{{ old("residents.{$loop->index}.suffix", $residentDraft->suffix) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Birth Date</label>
                                        <input type="date" name="residents[{{ $loop->index }}][birth_date]" value="{{ old("residents.{$loop->index}.birth_date", optional($residentDraft->birth_date)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Birth Place</label>
                                        <input type="text" name="residents[{{ $loop->index }}][birth_place]" value="{{ old("residents.{$loop->index}.birth_place", $residentDraft->birth_place) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Sex</label>
                                        <select name="residents[{{ $loop->index }}][sex]" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                            <option value="Male" {{ old("residents.{$loop->index}.sex", $residentDraft->sex) === 'Male' ? 'selected' : '' }}>Male</option>
                                            <option value="Female" {{ old("residents.{$loop->index}.sex", $residentDraft->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Civil Status</label>
                                        <input type="text" name="residents[{{ $loop->index }}][civil_status]" value="{{ old("residents.{$loop->index}.civil_status", $residentDraft->civil_status) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Citizenship</label>
                                        <input type="text" name="residents[{{ $loop->index }}][citizenship]" value="{{ old("residents.{$loop->index}.citizenship", $residentDraft->citizenship) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Religion</label>
                                        <input type="text" name="residents[{{ $loop->index }}][religion]" value="{{ old("residents.{$loop->index}.religion", $residentDraft->religion) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Contact Number</label>
                                        <input type="text" name="residents[{{ $loop->index }}][contact_number]" value="{{ old("residents.{$loop->index}.contact_number", $residentDraft->contact_number) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Email Address</label>
                                        <input type="email" name="residents[{{ $loop->index }}][email_address]" value="{{ old("residents.{$loop->index}.email_address", $residentDraft->email_address) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                            Approve Draft Package
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Review Context</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Approval creates one verified household plus all verified residents in this package. The draft itself stays in the queue as the audit source and links to the official records it created.
                </p>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Reject Instead</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Reject only when the field package is unusable or clearly wrong. Use the review form when the package is mostly correct but still needs secretary adjustments before approval.
                </p>

                <form action="{{ route('secretary.drafts.reject', $householdDraft) }}" method="POST" class="mt-5" onsubmit="return captureRejectionReason(this, '{{ addslashes($householdDraft->draft_reference_code) }}')">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="review_notes" value="">
                    <button type="submit" class="inline-flex items-center rounded-full bg-rose-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-rose-700">
                        Reject Draft Package
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        function captureRejectionReason(form, referenceCode) {
            const reason = window.prompt(`Enter a rejection note for ${referenceCode}:`);

            if (!reason) {
                return false;
            }

            form.querySelector('input[name="review_notes"]').value = reason;

            return true;
        }
    </script>
@endpush
