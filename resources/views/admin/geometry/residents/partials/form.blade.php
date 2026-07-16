<div class="grid grid-cols-1 gap-6 md:grid-cols-3">
    <div>
        <label for="barangay_id" class="block text-sm font-medium text-gray-700">Barangay</label>
        <select
            id="barangay_id"
            x-model="barangayId"
            @change="loadPuroks"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            required
        >
            <option value="">Select barangay</option>
            @foreach($barangays as $barangay)
                <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="purok_id" class="block text-sm font-medium text-gray-700">Purok</label>
        <select
            id="purok_id"
            x-model="purokId"
            @change="loadHouseholds"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            :disabled="puroks.length === 0"
            required
        >
            <option value="">Select purok</option>
            <template x-for="purok in puroks" :key="purok.id">
                <option :value="String(purok.id)" x-text="purok.purok_name ? `Purok ${purok.purok_number} - ${purok.purok_name}` : `Purok ${purok.purok_number}`"></option>
            </template>
        </select>
    </div>

    <div>
        <label for="household_id" class="block text-sm font-medium text-gray-700">Household</label>
        <div class="relative">
            <input type="hidden" name="household_id" x-model="householdId">
            <input
                type="text"
                id="household_id"
                x-ref="householdSearchInput"
                x-model="householdSearchQuery"
                :disabled="households.length === 0"
                :placeholder="households.length === 0 ? 'Select a purok with households first' : 'Search household number or address'"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('household_id') border-red-500 @enderror"
                autocomplete="off"
                @focus="openHouseholdSearch()"
                @input="handleHouseholdSearchInput()"
                @blur="closeHouseholdSearch()"
                @keydown.arrow-down.prevent="moveHouseholdSelection(1)"
                @keydown.arrow-up.prevent="moveHouseholdSelection(-1)"
                @keydown.enter.prevent="selectHighlightedHousehold()"
                @keydown.escape.prevent="householdSearchOpen = false"
                required
            >
            <div
                x-cloak
                x-show="householdSearchOpen"
                class="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60"
            >
                <div class="max-h-72 overflow-y-auto py-2">
                    <template x-if="filteredHouseholds.length === 0">
                        <div class="px-4 py-3 text-sm text-slate-500">No household matches your search.</div>
                    </template>
                    <template x-for="(household, index) in filteredHouseholds" :key="household.id">
                        <button
                            type="button"
                            class="block w-full px-4 py-3 text-left transition"
                            :class="index === highlightedHouseholdIndex ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50'"
                            @mousedown.prevent="selectHousehold(household)"
                        >
                            <span class="block text-sm font-medium" x-text="householdLabel(household)"></span>
                            <span class="mt-1 block text-xs text-slate-500" x-text="household.purok_name ? household.purok_name : 'Loaded household'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
        @error('household_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8">
    <h2 class="text-lg font-semibold text-gray-900">Personal Information</h2>
    <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label for="philsys_card_no" class="block text-sm font-medium text-gray-700">PhilSys Card Number</label>
            <input type="text" name="philsys_card_no" id="philsys_card_no" value="{{ old('philsys_card_no', $resident->philsys_card_no ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('philsys_card_no') border-red-500 @enderror">
            @error('philsys_card_no')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="relationship_to_head" class="block text-sm font-medium text-gray-700">Relationship to Household Head</label>
            <input type="text" name="relationship_to_head" id="relationship_to_head" value="{{ old('relationship_to_head', $resident->relationship_to_head ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('relationship_to_head') border-red-500 @enderror" required>
            @error('relationship_to_head')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="resident_status" class="block text-sm font-medium text-gray-700">Civil Registry Status</label>
            <select name="resident_status" id="resident_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('resident_status') border-red-500 @enderror">
                <option value="active" {{ old('resident_status', $resident->resident_status ?? 'active') === 'active' ? 'selected' : '' }}>Active Resident</option>
                <option value="deceased" {{ old('resident_status', $resident->resident_status ?? '') === 'deceased' ? 'selected' : '' }}>Deceased</option>
                <option value="relocated" {{ old('resident_status', $resident->resident_status ?? '') === 'relocated' ? 'selected' : '' }}>Relocated</option>
            </select>
            @error('resident_status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
            <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $resident->first_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('first_name') border-red-500 @enderror" required>
            @error('first_name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
            <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $resident->last_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('last_name') border-red-500 @enderror" required>
            @error('last_name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
            <input type="text" name="middle_name" id="middle_name" value="{{ old('middle_name', $resident->middle_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('middle_name') border-red-500 @enderror">
            @error('middle_name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="suffix" class="block text-sm font-medium text-gray-700">Suffix</label>
            <input type="text" name="suffix" id="suffix" value="{{ old('suffix', $resident->suffix ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('suffix') border-red-500 @enderror">
            @error('suffix')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="birth_date" class="block text-sm font-medium text-gray-700">Birth Date</label>
            <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date', optional($resident->birth_date ?? null)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('birth_date') border-red-500 @enderror" required>
            @error('birth_date')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="birth_place" class="block text-sm font-medium text-gray-700">Birth Place</label>
            <input type="text" name="birth_place" id="birth_place" value="{{ old('birth_place', $resident->birth_place ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('birth_place') border-red-500 @enderror" required>
            @error('birth_place')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="sex" class="block text-sm font-medium text-gray-700">Sex</label>
            <select name="sex" id="sex" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('sex') border-red-500 @enderror" required>
                <option value="">Select sex</option>
                <option value="Male" {{ old('sex', $resident->sex ?? '') === 'Male' ? 'selected' : '' }}>Male</option>
                <option value="Female" {{ old('sex', $resident->sex ?? '') === 'Female' ? 'selected' : '' }}>Female</option>
            </select>
            @error('sex')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="civil_status" class="block text-sm font-medium text-gray-700">Civil Status</label>
            <input type="text" name="civil_status" id="civil_status" value="{{ old('civil_status', $resident->civil_status ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('civil_status') border-red-500 @enderror" required>
            @error('civil_status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="citizenship" class="block text-sm font-medium text-gray-700">Citizenship</label>
            <input type="text" name="citizenship" id="citizenship" value="{{ old('citizenship', $resident->citizenship ?? 'Filipino') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('citizenship') border-red-500 @enderror" required>
            @error('citizenship')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="religion" class="block text-sm font-medium text-gray-700">Religion</label>
            <input type="text" name="religion" id="religion" value="{{ old('religion', $resident->religion ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('religion') border-red-500 @enderror">
            @error('religion')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
            <input type="text" name="contact_number" id="contact_number" value="{{ old('contact_number', $resident->contact_number ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('contact_number') border-red-500 @enderror">
            @error('contact_number')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email_address" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input type="email" name="email_address" id="email_address" value="{{ old('email_address', $resident->email_address ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('email_address') border-red-500 @enderror">
            @error('email_address')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="moved_in_at" class="block text-sm font-medium text-gray-700">Move-In Date</label>
            <input type="date" name="moved_in_at" id="moved_in_at" value="{{ old('moved_in_at', optional($resident->moved_in_at ?? null)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('moved_in_at') border-red-500 @enderror">
            @error('moved_in_at')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="moved_out_at" class="block text-sm font-medium text-gray-700">Move-Out Date</label>
            <input type="date" name="moved_out_at" id="moved_out_at" value="{{ old('moved_out_at', optional($resident->moved_out_at ?? null)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('moved_out_at') border-red-500 @enderror">
            @error('moved_out_at')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="date_of_death" class="block text-sm font-medium text-gray-700">Date of Death</label>
            <input type="date" name="date_of_death" id="date_of_death" value="{{ old('date_of_death', optional($resident->date_of_death ?? null)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('date_of_death') border-red-500 @enderror">
            @error('date_of_death')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="mt-8">
    <h2 class="text-lg font-semibold text-gray-900">Civil Notes</h2>
    <div class="mt-4">
        <label for="status_notes" class="block text-sm font-medium text-gray-700">Status Notes</label>
        <textarea name="status_notes" id="status_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('status_notes') border-red-500 @enderror">{{ old('status_notes', $resident->status_notes ?? '') }}</textarea>
        @error('status_notes')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8">
    <h2 class="text-lg font-semibold text-gray-900">Socio-Economic Profile</h2>
    <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label for="occupation" class="block text-sm font-medium text-gray-700">Occupation</label>
            <input type="text" name="occupation" id="occupation" value="{{ old('occupation', $resident->socioEconomicProfile->occupation ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('occupation') border-red-500 @enderror">
            @error('occupation')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="employment_status" class="block text-sm font-medium text-gray-700">Employment Status</label>
            <select name="employment_status" id="employment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('employment_status') border-red-500 @enderror">
                @foreach(['N/A', 'Employed', 'Unemployed'] as $option)
                    <option value="{{ $option }}" {{ old('employment_status', $resident->socioEconomicProfile->employment_status ?? 'N/A') === $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            </select>
            @error('employment_status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="highest_education_level" class="block text-sm font-medium text-gray-700">Highest Education Level</label>
            <select name="highest_education_level" id="highest_education_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('highest_education_level') border-red-500 @enderror">
                @foreach(['None', 'Elementary', 'High School', 'College', 'Post Grad', 'Vocational'] as $option)
                    <option value="{{ $option }}" {{ old('highest_education_level', $resident->socioEconomicProfile->highest_education_level ?? 'None') === $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            </select>
            @error('highest_education_level')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="education_status" class="block text-sm font-medium text-gray-700">Education Status</label>
            <select name="education_status" id="education_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('education_status') border-red-500 @enderror">
                @foreach(['N/A', 'Graduate', 'Undergraduate'] as $option)
                    <option value="{{ $option }}" {{ old('education_status', $resident->socioEconomicProfile->education_status ?? 'N/A') === $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            </select>
            @error('education_status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ethnicity" class="block text-sm font-medium text-gray-700">Ethnicity</label>
            <input type="text" name="ethnicity" id="ethnicity" value="{{ old('ethnicity', $resident->socioEconomicProfile->ethnicity ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('ethnicity') border-red-500 @enderror">
            @error('ethnicity')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="disability_type" class="block text-sm font-medium text-gray-700">Disability Type</label>
            <input type="text" name="disability_type" id="disability_type" value="{{ old('disability_type', $resident->socioEconomicProfile->disability_type ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('disability_type') border-red-500 @enderror">
            @error('disability_type')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        @php
            $flags = [
                'is_pwd' => 'PWD',
                'is_ofw' => 'OFW',
                'is_solo_parent' => 'Solo Parent',
                'is_osy' => 'Out-of-School Youth',
                'is_osc' => 'Out-of-School Child',
                'is_ip' => 'Indigenous Person',
            ];
        @endphp

        @foreach($flags as $field => $label)
            <label class="inline-flex items-center">
                <input type="checkbox" name="{{ $field }}" value="1" {{ old($field, $resident->socioEconomicProfile->{$field} ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
            </label>
        @endforeach

        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $resident->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
            <span class="ml-2 text-sm text-gray-700">Resident is active</span>
        </label>
    </div>
</div>
