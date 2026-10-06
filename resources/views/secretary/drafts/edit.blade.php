@extends('layouts.portal')

@section('title', 'Review Field Draft - HealthLink')
@section('header', 'Review Field Draft')
@section('subheader', $householdDraft->target_household_id ? 'Review new residents for an existing verified household.' : 'Finalize household placement and resident details before approval.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <x-record-action :href="route('secretary.drafts.show', $householdDraft)" variant="view">
            View Draft
        </x-record-action>
        <x-record-action :href="route('secretary.drafts.index')" variant="back">
            Back to Queue
        </x-record-action>
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

                    @if($householdDraft->target_household_id)
                        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                            New residents will join verified Household #{{ $householdDraft->targetHousehold?->household_no }}. The household itself will not be changed.
                        </div>
                        <input type="hidden" name="purok_id" value="{{ $householdDraft->targetHousehold?->purok_id }}">
                        <input type="hidden" name="household_no" value="{{ $householdDraft->targetHousehold?->household_no }}">
                    @endif
                    <div class="grid gap-6 md:grid-cols-2">
                        @if(!$householdDraft->target_household_id)
                        <div>
                            <label for="purok_id" class="block text-sm font-medium text-slate-700">Official Purok</label>
                            <select name="purok_id" id="purok_id" aria-invalid="{{ $errors->has('purok_id') ? 'true' : 'false' }}" @if($errors->has('purok_id')) aria-describedby="purok_id-error" @endif class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('purok_id') border-rose-400 @enderror" required>
                                @foreach($puroks as $purok)
                                    <option value="{{ $purok->id }}" {{ (string) old('purok_id', $householdDraft->purok_id) === (string) $purok->id ? 'selected' : '' }}>
                                        {{ $purok->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('purok_id')
                                <p id="purok_id-error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="household_no" class="block text-sm font-medium text-slate-700">Official Household No.</label>
                            <input type="text" name="household_no" id="household_no" aria-invalid="{{ $errors->has('household_no') ? 'true' : 'false' }}" @if($errors->has('household_no')) aria-describedby="household_no-error" @endif value="{{ old('household_no', $householdDraft->proposed_household_no) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('household_no') border-rose-400 @enderror" required>
                            @error('household_no')
                                <p id="household_no-error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="household_address" class="block text-sm font-medium text-slate-700">Household Address</label>
                            <textarea name="household_address" id="household_address" aria-invalid="{{ $errors->has('household_address') ? 'true' : 'false' }}" @if($errors->has('household_address')) aria-describedby="household_address-error" @endif rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('household_address') border-rose-400 @enderror" required>{{ old('household_address', $householdDraft->household_address) }}</textarea>
                            @error('household_address')
                                <p id="household_address-error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>
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
                            <select name="head_draft_id" id="head_draft_id" aria-invalid="{{ $errors->has('head_draft_id') ? 'true' : 'false' }}" @if($errors->has('head_draft_id')) aria-describedby="head_draft_id-error" @endif class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('head_draft_id') border-rose-400 @enderror">
                                <option value="">Leave household temporarily without a head</option>
                                @foreach($householdDraft->residentDrafts as $residentDraft)
                                    <option value="{{ $residentDraft->id }}" {{ (string) old('head_draft_id', $householdDraft->residentDrafts->firstWhere('is_household_head_candidate', true)?->id) === (string) $residentDraft->id ? 'selected' : '' }}>
                                        {{ $residentDraft->formal_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('head_draft_id')
                                <p id="head_draft_id-error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif

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
                                        <label for="draft_resident_{{ $loop->index }}_philsys_card_no" class="block text-sm font-medium text-slate-700">PhilSys ID</label>
                                        <input id="draft_resident_{{ $loop->index }}_philsys_card_no" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.philsys_card_no') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.philsys_card_no')) aria-describedby="draft_resident_{{ $loop->index }}_philsys_card_no-error" @endif type="text" name="residents[{{ $loop->index }}][philsys_card_no]" value="{{ old("residents.{$loop->index}.philsys_card_no", $residentDraft->philsys_card_no) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                        <x-input-error id="draft_resident_{{ $loop->index }}_philsys_card_no-error" :messages="$errors->get('residents.'.$loop->index.'.philsys_card_no')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_relationship_to_head" class="block text-sm font-medium text-slate-700">Relationship to Head</label>
                                        <input id="draft_resident_{{ $loop->index }}_relationship_to_head" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.relationship_to_head') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.relationship_to_head')) aria-describedby="draft_resident_{{ $loop->index }}_relationship_to_head-error" @endif type="text" name="residents[{{ $loop->index }}][relationship_to_head]" value="{{ old("residents.{$loop->index}.relationship_to_head", $residentDraft->relationship_to_head) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_relationship_to_head-error" :messages="$errors->get('residents.'.$loop->index.'.relationship_to_head')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_last_name" class="block text-sm font-medium text-slate-700">Last Name</label>
                                        <input id="draft_resident_{{ $loop->index }}_last_name" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.last_name') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.last_name')) aria-describedby="draft_resident_{{ $loop->index }}_last_name-error" @endif type="text" name="residents[{{ $loop->index }}][last_name]" value="{{ old("residents.{$loop->index}.last_name", $residentDraft->last_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_last_name-error" :messages="$errors->get('residents.'.$loop->index.'.last_name')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_first_name" class="block text-sm font-medium text-slate-700">First Name</label>
                                        <input id="draft_resident_{{ $loop->index }}_first_name" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.first_name') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.first_name')) aria-describedby="draft_resident_{{ $loop->index }}_first_name-error" @endif type="text" name="residents[{{ $loop->index }}][first_name]" value="{{ old("residents.{$loop->index}.first_name", $residentDraft->first_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_first_name-error" :messages="$errors->get('residents.'.$loop->index.'.first_name')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_middle_name" class="block text-sm font-medium text-slate-700">Middle Name</label>
                                        <input id="draft_resident_{{ $loop->index }}_middle_name" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.middle_name') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.middle_name')) aria-describedby="draft_resident_{{ $loop->index }}_middle_name-error" @endif type="text" name="residents[{{ $loop->index }}][middle_name]" value="{{ old("residents.{$loop->index}.middle_name", $residentDraft->middle_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                        <x-input-error id="draft_resident_{{ $loop->index }}_middle_name-error" :messages="$errors->get('residents.'.$loop->index.'.middle_name')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_suffix" class="block text-sm font-medium text-slate-700">Suffix</label>
                                        <input id="draft_resident_{{ $loop->index }}_suffix" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.suffix') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.suffix')) aria-describedby="draft_resident_{{ $loop->index }}_suffix-error" @endif type="text" name="residents[{{ $loop->index }}][suffix]" value="{{ old("residents.{$loop->index}.suffix", $residentDraft->suffix) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                        <x-input-error id="draft_resident_{{ $loop->index }}_suffix-error" :messages="$errors->get('residents.'.$loop->index.'.suffix')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_birth_date" class="block text-sm font-medium text-slate-700">Birth Date</label>
                                        <input id="draft_resident_{{ $loop->index }}_birth_date" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.birth_date') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.birth_date')) aria-describedby="draft_resident_{{ $loop->index }}_birth_date-error" @endif type="date" name="residents[{{ $loop->index }}][birth_date]" value="{{ old("residents.{$loop->index}.birth_date", optional($residentDraft->birth_date)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_birth_date-error" :messages="$errors->get('residents.'.$loop->index.'.birth_date')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_birth_place" class="block text-sm font-medium text-slate-700">Birth Place</label>
                                        <input id="draft_resident_{{ $loop->index }}_birth_place" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.birth_place') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.birth_place')) aria-describedby="draft_resident_{{ $loop->index }}_birth_place-error" @endif type="text" name="residents[{{ $loop->index }}][birth_place]" value="{{ old("residents.{$loop->index}.birth_place", $residentDraft->birth_place) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_birth_place-error" :messages="$errors->get('residents.'.$loop->index.'.birth_place')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_sex" class="block text-sm font-medium text-slate-700">Sex</label>
                                        <select id="draft_resident_{{ $loop->index }}_sex" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.sex') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.sex')) aria-describedby="draft_resident_{{ $loop->index }}_sex-error" @endif name="residents[{{ $loop->index }}][sex]" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                            <option value="Male" {{ old("residents.{$loop->index}.sex", $residentDraft->sex) === 'Male' ? 'selected' : '' }}>Male</option>
                                            <option value="Female" {{ old("residents.{$loop->index}.sex", $residentDraft->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                                        </select>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_sex-error" :messages="$errors->get('residents.'.$loop->index.'.sex')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_civil_status" class="block text-sm font-medium text-slate-700">Civil Status</label>
                                        <input id="draft_resident_{{ $loop->index }}_civil_status" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.civil_status') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.civil_status')) aria-describedby="draft_resident_{{ $loop->index }}_civil_status-error" @endif type="text" name="residents[{{ $loop->index }}][civil_status]" value="{{ old("residents.{$loop->index}.civil_status", $residentDraft->civil_status) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_civil_status-error" :messages="$errors->get('residents.'.$loop->index.'.civil_status')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_citizenship" class="block text-sm font-medium text-slate-700">Citizenship</label>
                                        <input id="draft_resident_{{ $loop->index }}_citizenship" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.citizenship') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.citizenship')) aria-describedby="draft_resident_{{ $loop->index }}_citizenship-error" @endif type="text" name="residents[{{ $loop->index }}][citizenship]" value="{{ old("residents.{$loop->index}.citizenship", $residentDraft->citizenship) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                        <x-input-error id="draft_resident_{{ $loop->index }}_citizenship-error" :messages="$errors->get('residents.'.$loop->index.'.citizenship')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_religion" class="block text-sm font-medium text-slate-700">Religion</label>
                                        <input id="draft_resident_{{ $loop->index }}_religion" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.religion') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.religion')) aria-describedby="draft_resident_{{ $loop->index }}_religion-error" @endif type="text" name="residents[{{ $loop->index }}][religion]" value="{{ old("residents.{$loop->index}.religion", $residentDraft->religion) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                        <x-input-error id="draft_resident_{{ $loop->index }}_religion-error" :messages="$errors->get('residents.'.$loop->index.'.religion')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_contact_number" class="block text-sm font-medium text-slate-700">Contact Number</label>
                                        <input id="draft_resident_{{ $loop->index }}_contact_number" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.contact_number') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.contact_number')) aria-describedby="draft_resident_{{ $loop->index }}_contact_number-error" @endif type="text" name="residents[{{ $loop->index }}][contact_number]" value="{{ old("residents.{$loop->index}.contact_number", $residentDraft->contact_number) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                        <x-input-error id="draft_resident_{{ $loop->index }}_contact_number-error" :messages="$errors->get('residents.'.$loop->index.'.contact_number')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="draft_resident_{{ $loop->index }}_email_address" class="block text-sm font-medium text-slate-700">Email Address</label>
                                        <input id="draft_resident_{{ $loop->index }}_email_address" aria-invalid="{{ $errors->has('residents.'.$loop->index.'.email_address') ? 'true' : 'false' }}" @if($errors->has('residents.'.$loop->index.'.email_address')) aria-describedby="draft_resident_{{ $loop->index }}_email_address-error" @endif type="email" name="residents[{{ $loop->index }}][email_address]" value="{{ old("residents.{$loop->index}.email_address", $residentDraft->email_address) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                        <x-input-error id="draft_resident_{{ $loop->index }}_email_address-error" :messages="$errors->get('residents.'.$loop->index.'.email_address')" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-slate-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 border-transparent bg-emerald-600 text-white hover:bg-emerald-700">
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
                    {{ $householdDraft->target_household_id ? 'Approval adds these residents to the existing verified household. The household details remain unchanged.' : 'Approval creates a verified household and the residents in this package. The draft remains as the audit source.' }}
                </p>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Reject Instead</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Reject only when the field package is unusable or clearly wrong. Use the review form when the package is mostly correct but still needs secretary adjustments before approval.
                </p>

                <form action="{{ route('secretary.drafts.reject', $householdDraft) }}" method="POST" class="mt-5">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="review_notes" value="">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-slate-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 border-transparent bg-rose-600 text-white hover:bg-rose-700">
                        Reject Draft Package
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
