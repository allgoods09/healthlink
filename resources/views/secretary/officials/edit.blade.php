@extends('layouts.portal')

@section('title', 'Edit Roster - HealthLink Secretary')
@section('header', 'Edit Roster')

@section('content')
    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Edit Roster</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $barangay->name }}. Leave a name blank if the position is not assigned.</p>
            </div>
            <x-record-action :href="route('secretary.officials.index')" variant="back">Back</x-record-action>
        </div>

        <form method="POST" action="{{ route('secretary.documents.officials.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')
            <x-input-error :messages="$errors->get('officials')" />

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($officials as $official)
                    @php($linked = $secretaryLinked && $official->role_key === \App\Models\BarangayOfficial::ROLE_BARANGAY_SECRETARY)
                    <div>
                        <label for="official_{{ $official->role_key }}" class="block text-sm font-medium text-slate-700">{{ $official->official_title }}</label>
                        <input
                            id="official_{{ $official->role_key }}"
                            name="officials[{{ $official->role_key }}]"
                            type="text"
                            maxlength="150"
                            value="{{ $linked ? $resolvedSecretaryName : old('officials.'.$official->role_key, $official->official_name) }}"
                            @if($linked) disabled aria-describedby="linked_secretary_help" @endif
                            @if($errors->has('officials.'.$official->role_key)) aria-invalid="true" aria-describedby="error_{{ $official->role_key }}" @endif
                            class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <x-input-error :messages="$errors->get('officials.'.$official->role_key)" :id="'error_'.$official->role_key" class="mt-2" />
                        @if($linked)
                            <p id="linked_secretary_help" class="mt-2 text-xs text-slate-500">This barangay already has an active Secretary user assigned. Their account name is automatically treated as the official Barangay Secretary.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap justify-end gap-3">
                <x-record-action :href="route('secretary.officials.index')" variant="back">Cancel</x-record-action>
                <x-record-action type="submit" variant="edit">Save Officials</x-record-action>
            </div>
        </form>
    </section>
@endsection
