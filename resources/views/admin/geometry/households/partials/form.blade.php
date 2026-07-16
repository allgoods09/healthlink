<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <div>
        <label for="barangay_id" class="block text-sm font-medium text-gray-700">Barangay</label>
        <select
            id="barangay_id"
            x-model="barangayId"
            @change="loadPuroks"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('purok_id') border-red-500 @enderror"
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
            name="purok_id"
            id="purok_id"
            x-model="purokId"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('purok_id') border-red-500 @enderror"
            :disabled="puroks.length === 0"
            required
        >
            <option value="">Select purok</option>
            <template x-for="purok in puroks" :key="purok.id">
                <option :value="String(purok.id)" x-text="purok.purok_name ? `Purok ${purok.purok_number} - ${purok.purok_name}` : `Purok ${purok.purok_number}`"></option>
            </template>
        </select>
        @error('purok_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="household_no" class="block text-sm font-medium text-gray-700">Household Number</label>
        <input
            type="text"
            name="household_no"
            id="household_no"
            value="{{ old('household_no', $household->household_no ?? '') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('household_no') border-red-500 @enderror"
            required
        >
        @error('household_no')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="head_resident_id" class="block text-sm font-medium text-gray-700">Head of Household</label>
        @php
            $headCandidates = $headCandidates ?? ($household->relationLoaded('residents') ? $household->residents : collect());
            $headResidentSearchOptions = $headCandidates->map(fn ($candidate) => [
                'value' => $candidate->id,
                'label' => $candidate->formal_name ?? $candidate->full_name,
                'search' => collect([
                    $candidate->formal_name ?? $candidate->full_name,
                    $candidate->official_resident_code,
                ])->filter()->implode(' '),
            ])->values()->all();
        @endphp
        <x-searchable-record-select
            name="head_resident_id"
            id="head_resident_id"
            :options="$headResidentSearchOptions"
            :selected="old('head_resident_id', $household->head_resident_id ?? '')"
            placeholder="{{ $headCandidates->isEmpty() ? 'Add residents first' : 'Search resident name' }}"
            empty-message="No resident matches your search."
            :disabled="$headCandidates->isEmpty()"
            class="rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
        />
        @error('head_resident_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @else
            <p class="mt-1 text-xs text-gray-500">A household can exist temporarily without a designated head.</p>
        @enderror
    </div>

    <div class="flex items-center gap-6 pt-6">
        <label class="inline-flex items-center">
            <input
                type="checkbox"
                name="is_social_aid_beneficiary"
                value="1"
                {{ old('is_social_aid_beneficiary', $household->is_social_aid_beneficiary ?? false) ? 'checked' : '' }}
                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
            >
            <span class="ml-2 text-sm text-gray-700">Social aid beneficiary</span>
        </label>

        <label class="inline-flex items-center">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                {{ old('is_active', $household->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
            >
            <span class="ml-2 text-sm text-gray-700">Active</span>
        </label>
    </div>
</div>

<div class="mt-6">
    <label for="household_address" class="block text-sm font-medium text-gray-700">Household Address</label>
    <textarea
        name="household_address"
        id="household_address"
        rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('household_address') border-red-500 @enderror"
        required
    >{{ old('household_address', $household->household_address ?? '') }}</textarea>
    @error('household_address')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
