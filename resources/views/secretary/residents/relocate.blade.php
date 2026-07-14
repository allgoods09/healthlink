@extends('layouts.portal')

@section('title', 'Relocate Resident - HealthLink Secretary')
@section('header', 'Relocate Resident')
@section('subheader', 'Move a resident into an existing household or create a new household in another purok, while keeping barangay scope and household head rules intact.')

@section('actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('secretary.residents.show', $resident) }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            View Resident
        </a>
        <a href="{{ route('secretary.residents.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            Back
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Current Resident</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $resident->formal_name }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $resident->sex }} · Age {{ $resident->age }} · {{ $resident->resident_status_label }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Current Assignment</p>
                <p class="mt-2 text-sm font-semibold text-slate-900">
                    Household #{{ $resident->household->household_no }} · {{ $resident->household->purok->display_name }}
                </p>
                <p class="mt-1 text-sm text-slate-600">{{ $resident->relationship_to_head }}</p>
                @if($resident->is_household_head)
                    <p class="mt-2 text-sm text-amber-700">This resident is the current household head. Moving them will clear the old household head assignment.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-6">
            <form
                method="POST"
                action="{{ route('secretary.residents.relocate.update', $resident) }}"
                x-data="residentRelocation(
                    '{{ route('secretary.residents.households-by-purok') }}',
                    '{{ old('destination', 'existing_household') }}',
                    '{{ old('target_purok_id', $selectedTargetPurokId) }}',
                    '{{ old('target_household_id', $selectedTargetHouseholdId) }}',
                    @js($existingHouseholds->map(fn ($household) => ['id' => $household->id, 'household_no' => $household->household_no, 'household_address' => $household->household_address])->values())
                )"
            >
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Destination Type</label>
                        <div class="mt-2 flex flex-col gap-3 md:flex-row">
                            <label class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-3">
                                <input type="radio" x-model="destination" name="destination" value="existing_household" class="border-slate-300 text-tubigon focus:ring-tubigon">
                                <span class="ml-3 text-sm text-slate-700">Move into an existing household</span>
                            </label>
                            <label class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-3">
                                <input type="radio" x-model="destination" name="destination" value="new_household" class="border-slate-300 text-tubigon focus:ring-tubigon">
                                <span class="ml-3 text-sm text-slate-700">Create a new household during relocation</span>
                            </label>
                        </div>
                        @error('destination')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="target_purok_id" class="block text-sm font-medium text-slate-700">Target Purok</label>
                        <select
                            name="target_purok_id"
                            id="target_purok_id"
                            x-model="targetPurokId"
                            @change="loadHouseholds"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('target_purok_id') border-red-500 @enderror"
                            required
                        >
                            <option value="">Select target purok</option>
                            @foreach($puroks as $purok)
                                <option value="{{ $purok->id }}">{{ $purok->display_name }}</option>
                            @endforeach
                        </select>
                        @error('target_purok_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="destination === 'existing_household'">
                        <label for="target_household_id" class="block text-sm font-medium text-slate-700">Target Household</label>
                        <select
                            name="target_household_id"
                            id="target_household_id"
                            x-model="targetHouseholdId"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('target_household_id') border-red-500 @enderror"
                            :disabled="households.length === 0"
                        >
                            <option value="">Select household</option>
                            <template x-for="household in households" :key="household.id">
                                <option :value="String(household.id)" x-text="`#${household.household_no} - ${household.household_address}`"></option>
                            </template>
                        </select>
                        @error('target_household_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="destination === 'new_household'">
                        <label for="new_household_no" class="block text-sm font-medium text-slate-700">New Household Number</label>
                        <input type="text" name="new_household_no" id="new_household_no" value="{{ old('new_household_no') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('new_household_no') border-red-500 @enderror">
                        @error('new_household_no')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2" x-show="destination === 'new_household'">
                        <label for="new_household_address" class="block text-sm font-medium text-slate-700">New Household Address</label>
                        <textarea name="new_household_address" id="new_household_address" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('new_household_address') border-red-500 @enderror">{{ old('new_household_address') }}</textarea>
                        @error('new_household_address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="set_as_household_head" value="1" {{ old('set_as_household_head') ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon focus:ring-tubigon">
                                <span class="ml-3 text-sm font-medium text-slate-700">Set this resident as the target household head</span>
                            </label>
                            <p class="mt-2 text-sm text-slate-500">If unchecked, a relationship to the existing household head is required instead.</p>
                        </div>
                        @error('set_as_household_head')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="relationship_to_head" class="block text-sm font-medium text-slate-700">Relationship to Target Household Head</label>
                        <input type="text" name="relationship_to_head" id="relationship_to_head" value="{{ old('relationship_to_head', $resident->relationship_to_head) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('relationship_to_head') border-red-500 @enderror">
                        @error('relationship_to_head')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="moved_in_at" class="block text-sm font-medium text-slate-700">Move-In Date</label>
                        <input type="date" name="moved_in_at" id="moved_in_at" value="{{ old('moved_in_at', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('moved_in_at') border-red-500 @enderror">
                        @error('moved_in_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="status_notes" class="block text-sm font-medium text-slate-700">Relocation Notes</label>
                        <textarea name="status_notes" id="status_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('status_notes') border-red-500 @enderror">{{ old('status_notes', $resident->status_notes) }}</textarea>
                        @error('status_notes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="destination === 'new_household'" class="md:col-span-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="new_household_social_aid" value="1" {{ old('new_household_social_aid') ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon focus:ring-tubigon">
                            <span class="ml-3 text-sm text-slate-700">Mark the new household as a social aid beneficiary</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Relocate Resident
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function residentRelocation(householdEndpoint, initialDestination, initialPurok, initialHousehold, initialHouseholds) {
            return {
                householdEndpoint,
                destination: initialDestination || 'existing_household',
                targetPurokId: initialPurok || '',
                targetHouseholdId: initialHousehold || '',
                households: initialHouseholds || [],
                init() {
                    if (this.targetPurokId && this.destination === 'existing_household' && this.households.length === 0) {
                        this.loadHouseholds();
                    }
                },
                async loadHouseholds() {
                    if (!this.targetPurokId) {
                        this.households = [];
                        this.targetHouseholdId = '';
                        return;
                    }

                    const response = await fetch(`${this.householdEndpoint}?purok_id=${this.targetPurokId}`);
                    this.households = await response.json();

                    if (!this.households.find((household) => String(household.id) === String(this.targetHouseholdId))) {
                        this.targetHouseholdId = '';
                    }
                },
            };
        }
    </script>
@endpush
