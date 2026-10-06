@extends('layouts.portal')

@section('title', 'Review Correction Request - HealthLink')
@section('header', 'Review Correction Request')
@section('subheader', 'Confirm or adjust the proposed values, then apply the final approved version back into the verified registry.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <x-record-action :href="route('secretary.update-requests.show', $profileUpdateRequest)" variant="view">
            View Request
        </x-record-action>
        <x-record-action :href="route('secretary.update-requests.index')" variant="back">
            Back to Queue
        </x-record-action>
    </div>
@endsection

@section('content')
    @php
        $proposed = $profileUpdateRequest->proposed_changes ?? [];
        $subject = $profileUpdateRequest->subject_type === \App\Models\ProfileUpdateRequest::SUBJECT_RESIDENT
            ? $profileUpdateRequest->resident
            : $profileUpdateRequest->household;
        $householdSearchOptions = $households->map(fn ($household) => [
            'value' => $household->id,
            'label' => $household->purok?->display_name.' · Household #'.$household->household_no,
            'description' => $household->household_address ?: 'No household address',
            'search' => collect([
                $household->purok?->display_name,
                $household->household_no ? 'household '.$household->household_no : null,
                $household->household_address,
                $household->headResident?->formal_name,
            ])->filter()->implode(' '),
            'ranking' => [
                'kind' => 'household', 'primaryIdentifier' => $household->household_no,
                'primaryName' => 'Household #'.$household->household_no, 'purok' => $household->purok?->display_name,
                'secondaryFields' => [$household->purok?->display_name, $household->household_address, $household->headResident?->formal_name],
            ],
        ])->values()->all();
        $headResidentSearchOptions = collect($subject?->currentMembers ?? [])->map(fn ($resident) => [
            'value' => $resident->id,
            'label' => $resident->formal_name,
            'search' => collect([
                $resident->formal_name,
                $resident->official_resident_code,
            ])->filter()->implode(' '),
            'ranking' => [
                'kind' => 'resident', 'primaryIdentifier' => $resident->official_resident_code,
                'primaryName' => $resident->formal_name, 'nameAliases' => [$resident->full_name],
            ],
        ])->values()->all();
    @endphp

    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Apply Final Changes</h3>
            </div>
            <div class="p-6">
                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <p class="font-semibold">Please review the correction approval form.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('secretary.update-requests.approve', $profileUpdateRequest) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    @if($profileUpdateRequest->subject_type === \App\Models\ProfileUpdateRequest::SUBJECT_RESIDENT)
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="correction_household_id" class="block text-sm font-medium text-slate-700">Household</label>
                                <x-searchable-record-select
                                    id="correction_household_id" aria-invalid="{{ $errors->has('household_id') ? 'true' : 'false' }}" :aria-describedby="$errors->has('household_id') ? 'correction_household_id-error' : null"
                                    name="household_id"
                                    :options="$householdSearchOptions"
                                    :selected="old('household_id', data_get($proposed, 'household_id', $subject?->household_id))"
                                    placeholder="Search household number or address"
                                    empty-message="No household matches your search."
                                    required
                                />
                                <x-input-error id="correction_household_id-error" :messages="$errors->get('household_id')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_philsys_card_no" class="block text-sm font-medium text-slate-700">PhilSys ID</label>
                                <input id="correction_philsys_card_no" aria-invalid="{{ $errors->has('philsys_card_no') ? 'true' : 'false' }}" @if($errors->has('philsys_card_no')) aria-describedby="correction_philsys_card_no-error" @endif type="text" name="philsys_card_no" value="{{ old('philsys_card_no', data_get($proposed, 'philsys_card_no', $subject?->philsys_card_no)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_philsys_card_no-error" :messages="$errors->get('philsys_card_no')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_last_name" class="block text-sm font-medium text-slate-700">Last Name</label>
                                <input id="correction_last_name" aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" @if($errors->has('last_name')) aria-describedby="correction_last_name-error" @endif type="text" name="last_name" value="{{ old('last_name', data_get($proposed, 'last_name', $subject?->last_name)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_last_name-error" :messages="$errors->get('last_name')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_first_name" class="block text-sm font-medium text-slate-700">First Name</label>
                                <input id="correction_first_name" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" @if($errors->has('first_name')) aria-describedby="correction_first_name-error" @endif type="text" name="first_name" value="{{ old('first_name', data_get($proposed, 'first_name', $subject?->first_name)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_first_name-error" :messages="$errors->get('first_name')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_middle_name" class="block text-sm font-medium text-slate-700">Middle Name</label>
                                <input id="correction_middle_name" aria-invalid="{{ $errors->has('middle_name') ? 'true' : 'false' }}" @if($errors->has('middle_name')) aria-describedby="correction_middle_name-error" @endif type="text" name="middle_name" value="{{ old('middle_name', data_get($proposed, 'middle_name', $subject?->middle_name)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_middle_name-error" :messages="$errors->get('middle_name')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_suffix" class="block text-sm font-medium text-slate-700">Suffix</label>
                                <input id="correction_suffix" aria-invalid="{{ $errors->has('suffix') ? 'true' : 'false' }}" @if($errors->has('suffix')) aria-describedby="correction_suffix-error" @endif type="text" name="suffix" value="{{ old('suffix', data_get($proposed, 'suffix', $subject?->suffix)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_suffix-error" :messages="$errors->get('suffix')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_birth_date" class="block text-sm font-medium text-slate-700">Birth Date</label>
                                <input id="correction_birth_date" aria-invalid="{{ $errors->has('birth_date') ? 'true' : 'false' }}" @if($errors->has('birth_date')) aria-describedby="correction_birth_date-error" @endif type="date" name="birth_date" value="{{ old('birth_date', data_get($proposed, 'birth_date', optional($subject?->birth_date)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_birth_date-error" :messages="$errors->get('birth_date')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_birth_place" class="block text-sm font-medium text-slate-700">Birth Place</label>
                                <input id="correction_birth_place" aria-invalid="{{ $errors->has('birth_place') ? 'true' : 'false' }}" @if($errors->has('birth_place')) aria-describedby="correction_birth_place-error" @endif type="text" name="birth_place" value="{{ old('birth_place', data_get($proposed, 'birth_place', $subject?->birth_place)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_birth_place-error" :messages="$errors->get('birth_place')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_sex" class="block text-sm font-medium text-slate-700">Sex</label>
                                <select id="correction_sex" aria-invalid="{{ $errors->has('sex') ? 'true' : 'false' }}" @if($errors->has('sex')) aria-describedby="correction_sex-error" @endif name="sex" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    <option value="Male" {{ old('sex', data_get($proposed, 'sex', $subject?->sex)) === 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ old('sex', data_get($proposed, 'sex', $subject?->sex)) === 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                                <x-input-error id="correction_sex-error" :messages="$errors->get('sex')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_civil_status" class="block text-sm font-medium text-slate-700">Civil Status</label>
                                <input id="correction_civil_status" aria-invalid="{{ $errors->has('civil_status') ? 'true' : 'false' }}" @if($errors->has('civil_status')) aria-describedby="correction_civil_status-error" @endif type="text" name="civil_status" value="{{ old('civil_status', data_get($proposed, 'civil_status', $subject?->civil_status)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_civil_status-error" :messages="$errors->get('civil_status')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_citizenship" class="block text-sm font-medium text-slate-700">Citizenship</label>
                                <input id="correction_citizenship" aria-invalid="{{ $errors->has('citizenship') ? 'true' : 'false' }}" @if($errors->has('citizenship')) aria-describedby="correction_citizenship-error" @endif type="text" name="citizenship" value="{{ old('citizenship', data_get($proposed, 'citizenship', $subject?->citizenship)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_citizenship-error" :messages="$errors->get('citizenship')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_religion" class="block text-sm font-medium text-slate-700">Religion</label>
                                <input id="correction_religion" aria-invalid="{{ $errors->has('religion') ? 'true' : 'false' }}" @if($errors->has('religion')) aria-describedby="correction_religion-error" @endif type="text" name="religion" value="{{ old('religion', data_get($proposed, 'religion', $subject?->religion)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_religion-error" :messages="$errors->get('religion')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_contact_number" class="block text-sm font-medium text-slate-700">Contact Number</label>
                                <input id="correction_contact_number" aria-invalid="{{ $errors->has('contact_number') ? 'true' : 'false' }}" @if($errors->has('contact_number')) aria-describedby="correction_contact_number-error" @endif type="text" name="contact_number" value="{{ old('contact_number', data_get($proposed, 'contact_number', $subject?->contact_number)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_contact_number-error" :messages="$errors->get('contact_number')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_email_address" class="block text-sm font-medium text-slate-700">Email Address</label>
                                <input id="correction_email_address" aria-invalid="{{ $errors->has('email_address') ? 'true' : 'false' }}" @if($errors->has('email_address')) aria-describedby="correction_email_address-error" @endif type="email" name="email_address" value="{{ old('email_address', data_get($proposed, 'email_address', $subject?->email_address)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_email_address-error" :messages="$errors->get('email_address')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_relationship_to_head" class="block text-sm font-medium text-slate-700">Relationship to Head</label>
                                <input id="correction_relationship_to_head" aria-invalid="{{ $errors->has('relationship_to_head') ? 'true' : 'false' }}" @if($errors->has('relationship_to_head')) aria-describedby="correction_relationship_to_head-error" @endif type="text" name="relationship_to_head" value="{{ old('relationship_to_head', data_get($proposed, 'relationship_to_head', $subject?->relationship_to_head)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_relationship_to_head-error" :messages="$errors->get('relationship_to_head')" class="mt-2" />
                                @if($subject?->is_household_head)
                                    <p class="mt-2 text-sm text-slate-500">This resident remains the household head during ordinary corrections.</p>
                                @else
                                    <label for="correction_set_as_household_head" class="mt-3 flex items-center gap-2 text-sm text-slate-700"><input id="correction_set_as_household_head" aria-invalid="{{ $errors->has('set_as_household_head') ? 'true' : 'false' }}" @if($errors->has('set_as_household_head')) aria-describedby="correction_set_as_household_head-error" @endif type="checkbox" name="set_as_household_head" value="1" @checked(old('set_as_household_head')) class="rounded border-slate-300">Set this resident as the household head</label>
                                    <x-input-error id="correction_set_as_household_head-error" :messages="$errors->get('set_as_household_head')" class="mt-2" />
                                    <p class="mt-1 text-sm text-slate-500">An explicit change requires reviewing the household's other members.</p>
                                @endif
                            </div>
                            <div>
                                <label for="correction_resident_status" class="block text-sm font-medium text-slate-700">Resident Status</label>
                                <select id="correction_resident_status" aria-invalid="{{ $errors->has('resident_status') ? 'true' : 'false' }}" @if($errors->has('resident_status')) aria-describedby="correction_resident_status-error" @endif name="resident_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    <option value="active" {{ old('resident_status', data_get($proposed, 'resident_status', $subject?->resident_status)) === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="deceased" {{ old('resident_status', data_get($proposed, 'resident_status', $subject?->resident_status)) === 'deceased' ? 'selected' : '' }}>Deceased</option>
                                    <option value="relocated" {{ old('resident_status', data_get($proposed, 'resident_status', $subject?->resident_status)) === 'relocated' ? 'selected' : '' }}>Relocated</option>
                                </select>
                                <x-input-error id="correction_resident_status-error" :messages="$errors->get('resident_status')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_moved_in_at" class="block text-sm font-medium text-slate-700">Moved In At</label>
                                <input id="correction_moved_in_at" aria-invalid="{{ $errors->has('moved_in_at') ? 'true' : 'false' }}" @if($errors->has('moved_in_at')) aria-describedby="correction_moved_in_at-error" @endif type="date" name="moved_in_at" value="{{ old('moved_in_at', data_get($proposed, 'moved_in_at', optional($subject?->moved_in_at)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_moved_in_at-error" :messages="$errors->get('moved_in_at')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_moved_out_at" class="block text-sm font-medium text-slate-700">Moved Out At</label>
                                <input id="correction_moved_out_at" aria-invalid="{{ $errors->has('moved_out_at') ? 'true' : 'false' }}" @if($errors->has('moved_out_at')) aria-describedby="correction_moved_out_at-error" @endif type="date" name="moved_out_at" value="{{ old('moved_out_at', data_get($proposed, 'moved_out_at', optional($subject?->moved_out_at)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_moved_out_at-error" :messages="$errors->get('moved_out_at')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_date_of_death" class="block text-sm font-medium text-slate-700">Date of Death</label>
                                <input id="correction_date_of_death" aria-invalid="{{ $errors->has('date_of_death') ? 'true' : 'false' }}" @if($errors->has('date_of_death')) aria-describedby="correction_date_of_death-error" @endif type="date" name="date_of_death" value="{{ old('date_of_death', data_get($proposed, 'date_of_death', optional($subject?->date_of_death)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_date_of_death-error" :messages="$errors->get('date_of_death')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2">
                                <input type="hidden" name="is_active" value="0">
                                <label for="correction_is_active" class="inline-flex items-center">
                                    <input id="correction_is_active" aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}" @if($errors->has('is_active')) aria-describedby="correction_is_active-error" @endif type="checkbox" name="is_active" value="1" {{ old('is_active', data_get($proposed, 'is_active', $subject?->is_active)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Keep resident record active</span>
                                </label>
                                <x-input-error id="correction_is_active-error" :messages="$errors->get('is_active')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2">
                                <label for="correction_status_notes" class="block text-sm font-medium text-slate-700">Status Notes</label>
                                <textarea id="correction_status_notes" aria-invalid="{{ $errors->has('status_notes') ? 'true' : 'false' }}" @if($errors->has('status_notes')) aria-describedby="correction_status_notes-error" @endif name="status_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('status_notes', data_get($proposed, 'status_notes', $subject?->status_notes)) }}</textarea>
                                <x-input-error id="correction_status_notes-error" :messages="$errors->get('status_notes')" class="mt-2" />
                            </div>
                            @include('secretary.partials.resident-profile-fields', ['profileValues' => $proposed,
                                'profilePrefix' => '', 'profileIndex' => null, 'changedOnly' => $proposed])
                        </div>
                    @else
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="correction_purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                                <select id="correction_purok_id" aria-invalid="{{ $errors->has('purok_id') ? 'true' : 'false' }}" @if($errors->has('purok_id')) aria-describedby="correction_purok_id-error" @endif name="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    @foreach($puroks as $purok)
                                        <option value="{{ $purok->id }}" {{ (string) old('purok_id', data_get($proposed, 'purok_id', $subject?->purok_id)) === (string) $purok->id ? 'selected' : '' }}>
                                            {{ $purok->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error id="correction_purok_id-error" :messages="$errors->get('purok_id')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_household_no" class="block text-sm font-medium text-slate-700">Household No.</label>
                                <input id="correction_household_no" aria-invalid="{{ $errors->has('household_no') ? 'true' : 'false' }}" @if($errors->has('household_no')) aria-describedby="correction_household_no-error" @endif type="text" name="household_no" value="{{ old('household_no', data_get($proposed, 'household_no', $subject?->household_no)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                <x-input-error id="correction_household_no-error" :messages="$errors->get('household_no')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2">
                                <label for="correction_household_address" class="block text-sm font-medium text-slate-700">Household Address</label>
                                <textarea id="correction_household_address" aria-invalid="{{ $errors->has('household_address') ? 'true' : 'false' }}" @if($errors->has('household_address')) aria-describedby="correction_household_address-error" @endif name="household_address" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>{{ old('household_address', data_get($proposed, 'household_address', $subject?->household_address)) }}</textarea>
                                <x-input-error id="correction_household_address-error" :messages="$errors->get('household_address')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_drinking_water_source" class="block text-sm font-medium text-slate-700">Water Source</label>
                                <input id="correction_drinking_water_source" aria-invalid="{{ $errors->has('drinking_water_source') ? 'true' : 'false' }}" @if($errors->has('drinking_water_source')) aria-describedby="correction_drinking_water_source-error" @endif type="text" name="drinking_water_source" value="{{ old('drinking_water_source', data_get($proposed, 'drinking_water_source', $subject?->drinking_water_source)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_drinking_water_source-error" :messages="$errors->get('drinking_water_source')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_sanitary_toilet_type" class="block text-sm font-medium text-slate-700">Toilet Type</label>
                                <input id="correction_sanitary_toilet_type" aria-invalid="{{ $errors->has('sanitary_toilet_type') ? 'true' : 'false' }}" @if($errors->has('sanitary_toilet_type')) aria-describedby="correction_sanitary_toilet_type-error" @endif type="text" name="sanitary_toilet_type" value="{{ old('sanitary_toilet_type', data_get($proposed, 'sanitary_toilet_type', $subject?->sanitary_toilet_type)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <x-input-error id="correction_sanitary_toilet_type-error" :messages="$errors->get('sanitary_toilet_type')" class="mt-2" />
                            </div>
                            <div>
                                <label for="correction_head_resident_id" class="block text-sm font-medium text-slate-700">Head of Household</label>
                                <x-searchable-record-select
                                    id="correction_head_resident_id" aria-invalid="{{ $errors->has('head_resident_id') ? 'true' : 'false' }}" :aria-describedby="$errors->has('head_resident_id') ? 'correction_head_resident_id-error' : null"
                                    name="head_resident_id"
                                    :options="$headResidentSearchOptions"
                                    :selected="old('head_resident_id', data_get($proposed, 'head_resident_id', $subject?->head_resident_id))"
                                    placeholder="Search resident name"
                                    empty-message="No resident matches your search."
                                />
                                <x-input-error id="correction_head_resident_id-error" :messages="$errors->get('head_resident_id')" class="mt-2" />
                            </div>
                            <div>
                                <input type="hidden" name="has_sanitary_toilet" value="0">
                                <label for="correction_has_sanitary_toilet" class="inline-flex items-center">
                                    <input id="correction_has_sanitary_toilet" aria-invalid="{{ $errors->has('has_sanitary_toilet') ? 'true' : 'false' }}" @if($errors->has('has_sanitary_toilet')) aria-describedby="correction_has_sanitary_toilet-error" @endif type="checkbox" name="has_sanitary_toilet" value="1" {{ old('has_sanitary_toilet', data_get($proposed, 'has_sanitary_toilet', $subject?->has_sanitary_toilet)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Has sanitary toilet</span>
                                </label>
                                <x-input-error id="correction_has_sanitary_toilet-error" :messages="$errors->get('has_sanitary_toilet')" class="mt-2" />
                            </div>
                            <div>
                                <input type="hidden" name="is_social_aid_beneficiary" value="0">
                                <label for="correction_is_social_aid_beneficiary" class="inline-flex items-center">
                                    <input id="correction_is_social_aid_beneficiary" aria-invalid="{{ $errors->has('is_social_aid_beneficiary') ? 'true' : 'false' }}" @if($errors->has('is_social_aid_beneficiary')) aria-describedby="correction_is_social_aid_beneficiary-error" @endif type="checkbox" name="is_social_aid_beneficiary" value="1" {{ old('is_social_aid_beneficiary', data_get($proposed, 'is_social_aid_beneficiary', $subject?->is_social_aid_beneficiary)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Social aid beneficiary</span>
                                </label>
                                <x-input-error id="correction_is_social_aid_beneficiary-error" :messages="$errors->get('is_social_aid_beneficiary')" class="mt-2" />
                            </div>
                            <div>
                                <input type="hidden" name="is_active" value="0">
                                <label for="correction_is_active" class="inline-flex items-center">
                                    <input id="correction_is_active" aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}" @if($errors->has('is_active')) aria-describedby="correction_is_active-error" @endif type="checkbox" name="is_active" value="1" {{ old('is_active', data_get($proposed, 'is_active', $subject?->is_active)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Keep household active</span>
                                </label>
                                <x-input-error id="correction_is_active-error" :messages="$errors->get('is_active')" class="mt-2" />
                            </div>
                        </div>
                    @endif

                    <div>
                        <label for="correction_review_notes" class="block text-sm font-medium text-slate-700">Secretary Review Notes</label>
                        <textarea id="correction_review_notes" aria-invalid="{{ $errors->has('review_notes') ? 'true' : 'false' }}" @if($errors->has('review_notes')) aria-describedby="correction_review_notes-error" @endif name="review_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('review_notes', $profileUpdateRequest->review_notes) }}</textarea>
                        <x-input-error id="correction_review_notes-error" :messages="$errors->get('review_notes')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-slate-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 border-transparent bg-emerald-600 text-white hover:bg-emerald-700">
                            Apply Approved Changes
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Request Reason</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ $profileUpdateRequest->request_reason ?: 'No reason provided.' }}</p>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Reject Instead</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Reject only if the requested correction is unsupported or inaccurate. If the field report is directionally correct, adjust the values in the approval form instead and apply the cleaned version.
                </p>

                <form action="{{ route('secretary.update-requests.reject', $profileUpdateRequest) }}" method="POST" class="mt-5">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="review_notes" value="">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-slate-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 border-transparent bg-rose-600 text-white hover:bg-rose-700">
                        Reject Correction Request
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
