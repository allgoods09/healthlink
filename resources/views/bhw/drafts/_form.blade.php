@php
    $residentRows = old('residents');

    if (! $residentRows) {
        $residentRows = isset($draft)
            ? $draft->residentDrafts->map(fn ($residentDraft) => [
                'philsys_card_no' => $residentDraft->philsys_card_no,
                'last_name' => $residentDraft->last_name,
                'first_name' => $residentDraft->first_name,
                'middle_name' => $residentDraft->middle_name,
                'suffix' => $residentDraft->suffix,
                'birth_date' => optional($residentDraft->birth_date)->format('Y-m-d'),
                'birth_place' => $residentDraft->birth_place,
                'sex' => $residentDraft->sex,
                'civil_status' => $residentDraft->civil_status,
                'citizenship' => $residentDraft->citizenship,
                'religion' => $residentDraft->religion,
                'contact_number' => $residentDraft->contact_number,
                'email_address' => $residentDraft->email_address,
                'relationship_to_head' => $residentDraft->relationship_to_head,
                'is_household_head_candidate' => $residentDraft->is_household_head_candidate,
                'draft_notes' => $residentDraft->draft_notes,
            ])->values()->all()
            : [[
                'philsys_card_no' => null,
                'last_name' => null,
                'first_name' => null,
                'middle_name' => null,
                'suffix' => null,
                'birth_date' => null,
                'birth_place' => null,
                'sex' => 'Female',
                'civil_status' => 'Single',
                'citizenship' => 'Filipino',
                'religion' => null,
                'contact_number' => null,
                'email_address' => null,
                'relationship_to_head' => null,
                'is_household_head_candidate' => false,
                'draft_notes' => null,
            ]];
    }
@endphp

@if($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <p class="font-semibold">Please review the field draft details.</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" x-data="{ rows: {{ Js::from($residentRows) }} }" class="space-y-8">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Household Draft Details</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    @foreach($puroks as $purok)
                        <option value="{{ $purok->id }}" @selected((string) old('purok_id', $draft->purok_id ?? $defaultPurokId ?? null) === (string) $purok->id)>{{ $purok->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="household_address" class="block text-sm font-medium text-slate-700">Household Address</label>
                <input type="text" name="household_address" id="household_address" value="{{ old('household_address', $draft->household_address ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label for="drinking_water_source" class="block text-sm font-medium text-slate-700">Drinking Water Source</label>
                <input type="text" name="drinking_water_source" id="drinking_water_source" value="{{ old('drinking_water_source', $draft->drinking_water_source ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label for="sanitary_toilet_type" class="block text-sm font-medium text-slate-700">Sanitary Toilet Type</label>
                <input type="text" name="sanitary_toilet_type" id="sanitary_toilet_type" value="{{ old('sanitary_toilet_type', $draft->sanitary_toilet_type ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label for="garbage_disposal_method" class="block text-sm font-medium text-slate-700">Garbage Disposal Method</label>
                <select name="garbage_disposal_method" id="garbage_disposal_method" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">Select a method</option>
                    @foreach($garbageDisposalMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('garbage_disposal_method', $draft->garbage_disposal_method ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="housing_material_type" class="block text-sm font-medium text-slate-700">Housing Materials</label>
                <select name="housing_material_type" id="housing_material_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">Select a type</option>
                    @foreach($housingMaterialTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('housing_material_type', $draft->housing_material_type ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="hidden" name="has_sanitary_toilet" value="0">
                        <input type="checkbox" name="has_sanitary_toilet" value="1" @checked(old('has_sanitary_toilet', $draft->has_sanitary_toilet ?? false)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                        <span class="text-sm text-slate-700">Has Sanitary Toilet</span>
                    </label>
                    <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="hidden" name="has_backyard_garden" value="0">
                        <input type="checkbox" name="has_backyard_garden" value="1" @checked(old('has_backyard_garden', $draft->has_backyard_garden ?? false)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                        <span class="text-sm text-slate-700">Has Backyard Garden</span>
                    </label>
                    <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="hidden" name="is_social_aid_beneficiary" value="0">
                        <input type="checkbox" name="is_social_aid_beneficiary" value="1" @checked(old('is_social_aid_beneficiary', $draft->is_social_aid_beneficiary ?? false)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                        <span class="text-sm text-slate-700">Social Aid Beneficiary</span>
                    </label>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Resident Drafts</h2>
                <p class="mt-1 text-sm text-slate-500">Add the people found during the household survey before sending the package for verification.</p>
            </div>
            <button type="button" @click="rows.push({ philsys_card_no: '', last_name: '', first_name: '', middle_name: '', suffix: '', birth_date: '', birth_place: '', sex: 'Female', civil_status: 'Single', citizenship: 'Filipino', religion: '', contact_number: '', email_address: '', relationship_to_head: '', is_household_head_candidate: false, draft_notes: '' })" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
                Add Resident
            </button>
        </div>
        <div class="space-y-6 p-6">
            <template x-for="(row, index) in rows" :key="index">
                <div class="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500" x-text="'Resident ' + (index + 1)"></h3>
                        <button type="button" @click="rows.splice(index, 1)" x-show="rows.length > 1" class="text-sm font-medium text-rose-600 hover:text-rose-800">Remove</button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">PhilSys Card No.</label>
                            <input type="text" x-model="row.philsys_card_no" :name="'residents[' + index + '][philsys_card_no]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Relationship to Head</label>
                            <input type="text" x-model="row.relationship_to_head" :name="'residents[' + index + '][relationship_to_head]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Last Name</label>
                            <input type="text" x-model="row.last_name" :name="'residents[' + index + '][last_name]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">First Name</label>
                            <input type="text" x-model="row.first_name" :name="'residents[' + index + '][first_name]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Middle Name</label>
                            <input type="text" x-model="row.middle_name" :name="'residents[' + index + '][middle_name]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Suffix</label>
                            <input type="text" x-model="row.suffix" :name="'residents[' + index + '][suffix]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Birth Date</label>
                            <input type="date" x-model="row.birth_date" :name="'residents[' + index + '][birth_date]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Birth Place</label>
                            <input type="text" x-model="row.birth_place" :name="'residents[' + index + '][birth_place]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Sex</label>
                            <select x-model="row.sex" :name="'residents[' + index + '][sex]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Civil Status</label>
                            <input type="text" x-model="row.civil_status" :name="'residents[' + index + '][civil_status]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Citizenship</label>
                            <input type="text" x-model="row.citizenship" :name="'residents[' + index + '][citizenship]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Religion</label>
                            <input type="text" x-model="row.religion" :name="'residents[' + index + '][religion]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Contact Number</label>
                            <input type="text" x-model="row.contact_number" :name="'residents[' + index + '][contact_number]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Email Address</label>
                            <input type="email" x-model="row.email_address" :name="'residents[' + index + '][email_address]'" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">Field Notes</label>
                            <textarea x-model="row.draft_notes" :name="'residents[' + index + '][draft_notes]'" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <input type="checkbox" x-model="row.is_household_head_candidate" :name="'residents[' + index + '][is_household_head_candidate]'" value="1" class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                <span class="text-sm text-slate-700">Suggested Head of Household</span>
                            </label>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
            Save Draft Package
        </button>
        <a href="{{ route('bhw.drafts.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Cancel
        </a>
    </div>
</form>
