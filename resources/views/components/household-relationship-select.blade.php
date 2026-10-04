@props(['name' => 'relationship_to_head', 'current' => null, 'selected' => null, 'review' => false])
@php
    $groups = \App\Support\HouseholdRelationships::groups();
    $legacy = $current && ! in_array($current, \App\Support\HouseholdRelationships::choices(), true)
        && (! $review || ! \App\Support\HouseholdRelationships::isHead($current));
    $value = $selected ?? $current;
@endphp
<select name="{{ $name }}" {{ $attributes->class(['mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500']) }} required>
    <option value="">Choose relationship</option>
    @if($legacy)
        <option value="{{ $current }}" @selected($value === $current)>Current value: {{ $current }}</option>
    @endif
    @foreach($groups as $group => $choices)
        <optgroup label="{{ $group }}">
            @foreach($choices as $choice)
                <option value="{{ $choice }}" @selected($value === $choice)>{{ $choice }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
