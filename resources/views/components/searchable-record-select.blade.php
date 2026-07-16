@props([
    'name',
    'id' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Search records',
    'emptyMessage' => 'No matching records found.',
    'disabled' => false,
    'required' => false,
])

@php
    use Illuminate\Support\Str;

    $fieldId = $id ?: (string) Str::of($name)->replace(['[', ']', '.'], '-')->trim('-');
    $hasError = $errors->has($name);
    $normalizedOptions = collect($options)
        ->map(fn ($option) => [
            'value' => (string) data_get($option, 'value', data_get($option, 'id')),
            'label' => (string) data_get($option, 'label', ''),
            'search' => (string) data_get($option, 'search', data_get($option, 'label', '')),
            'description' => (string) data_get($option, 'description', ''),
        ])
        ->filter(fn ($option) => $option['value'] !== '')
        ->values()
        ->all();
@endphp

<div
    x-data="searchableRecordSelect({
        options: @js($normalizedOptions),
        selected: @js(old($name, $selected)),
        placeholder: @js($placeholder),
        emptyMessage: @js($emptyMessage),
        required: @js((bool) $required),
        disabled: @js((bool) $disabled),
    })"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" x-model="selectedValue">

    <input
        {{ $attributes->merge([
            'type' => 'text',
            'id' => $fieldId,
            'class' => 'mt-1 block w-full rounded-xl border shadow-sm focus:border-tubigon focus:ring-tubigon ' . ($hasError ? 'border-red-500' : 'border-slate-300'),
        ]) }}
        x-ref="searchInput"
        x-model="query"
        x-bind:disabled="disabled"
        x-bind:placeholder="placeholder"
        autocomplete="off"
        @focus="openIfSearching()"
        @input="handleInput()"
        @blur="handleBlur()"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter.prevent="selectHighlighted()"
        @keydown.escape.prevent="isOpen = false"
    >

    <div
        x-cloak
        x-show="isOpen"
        class="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60"
    >
        <div class="max-h-72 overflow-y-auto py-2">
            <template x-if="filteredOptions.length === 0">
                <div class="px-4 py-3 text-sm text-slate-500" x-text="emptyMessage"></div>
            </template>

            <template x-for="(option, index) in filteredOptions" :key="option.value">
                <button
                    type="button"
                    class="block w-full px-4 py-3 text-left transition"
                    :class="index === highlightedIndex ? 'bg-tubigon/10 text-tubigon' : 'text-slate-700 hover:bg-slate-50'"
                    @mousedown.prevent="selectOption(option)"
                >
                    <span class="block text-sm font-medium" x-text="option.label"></span>
                    <template x-if="option.description">
                        <span class="mt-1 block text-xs text-slate-500" x-text="option.description"></span>
                    </template>
                </button>
            </template>
        </div>
    </div>
</div>
