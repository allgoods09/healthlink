@props(['options' => [], 'name' => '', 'residentId' => null])
<div class="relative" x-data="optCaregiverField({ options: @js($options), name: @js(old('caregiver_name', $name)), residentId: @js(old('caregiver_resident_id', $residentId)) })" @click.outside="isOpen = false">
    <input type="hidden" name="caregiver_resident_id" value="{{ old('caregiver_resident_id', $residentId) }}" x-model="residentId">
    <input id="caregiver-name" name="caregiver_name" type="text" maxlength="255"
        value="{{ old('caregiver_name', $name) }}" x-model="name" @input="input()"
        @focus="isOpen = matches.length > 0" @blur="isOpen = false" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
        @keydown.escape.prevent="isOpen = false"
        @keydown.enter="if (isOpen && highlightedIndex >= 0) { $event.preventDefault(); choose(matches[highlightedIndex]); }"
        role="combobox" aria-autocomplete="list" aria-controls="caregiver-matches" :aria-expanded="isOpen"
        :aria-activedescendant="isOpen && highlightedIndex >= 0 ? 'caregiver-option-' + highlightedIndex : null"
        autocomplete="off" placeholder="Type a name or choose a matching resident"
        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-3 focus:border-tubigon focus:ring-tubigon">
    <div id="caregiver-matches" role="listbox" x-cloak x-show="isOpen && matches.length" class="absolute z-30 mt-1 w-full rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
        <template x-for="(option, index) in matches" :key="option.value">
            <button type="button" role="option" tabindex="-1" :id="'caregiver-option-' + index" :aria-selected="index === highlightedIndex"
                @mousedown.prevent @click="choose(option)" :class="index === highlightedIndex ? 'bg-tubigon/10' : ''"
                class="block w-full rounded-lg px-4 py-3 text-left text-sm hover:bg-slate-50">
                <span class="block font-medium" x-text="option.label"></span>
                <span class="block text-xs text-slate-500" x-text="option.description"></span>
            </button>
        </template>
    </div>
</div>
