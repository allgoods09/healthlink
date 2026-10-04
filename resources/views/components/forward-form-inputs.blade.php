@props(['values', 'prefix' => ''])
@foreach($values as $key => $value)
    @php
        $name = $prefix === '' ? $key : $prefix.'['.$key.']';
    @endphp
    @if(is_array($value))
        <x-forward-form-inputs :values="$value" :prefix="$name" />
    @else
        <input type="hidden" name="{{ $name }}" value="{{ is_bool($value) ? (int) $value : ($value ?? '') }}">
    @endif
@endforeach
