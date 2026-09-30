@extends('layouts.portal')
@section('title', $entry->child_name.' - '.$cycle->title)
@section('header', $entry->child_name)
@section('subheader', $cycle->title.' - '.$entry->purok_name)
@section('actions')
    <a href="{{ route('bns.opt-cycles.show', $cycle) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Back to Child List</a>
@endsection
@section('content')
    @include('bns.opt-cycles.errors')
    <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-5">
            <p class="text-sm text-slate-600">{{ $entry->reference_age }} months at cycle date · {{ $entry->sex }} · Born {{ $entry->birth_date->format('M j, Y') }}</p>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold">{{ $entry->measurement_status }}</span>
        </div>
        @if($cycle->status === 'completed')
            <p class="text-sm text-slate-600">This cycle is completed. Reopen it to make changes.</p>
            <dl class="mt-6 grid grid-cols-2 gap-5 text-sm">
                @foreach(['Mother / Caregiver' => $entry->caregiver_name ?: '-', 'Measurement Date' => $entry->measurement?->measurement_date?->format('M j, Y') ?? '-', 'Weight' => $entry->measurement ? $entry->measurement->weight_kg.' kg' : '-', 'Height / Length' => $entry->measurement ? $entry->measurement->height_cm.' cm' : '-'] as $label => $value)
                    <div><dt class="text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $value }}</dd></div>
                @endforeach
            </dl>
        @else
            @php
                $profile = $canUpdateProfile ? $entry->resident?->childNutritionProfile : null;
                $caregiverName = $profile?->caregiver_name ?? $entry->caregiver_name;
                $caregiverId = $profile?->caregiver_name ? $profile->caregiver_resident_id : $entry->caregiver_resident_key;
            @endphp
            <form method="POST" action="{{ route('bns.opt-cycles.measure', [$cycle, $entry]) }}" class="space-y-6"
                x-data="optMeasurementForm({ dob: @js($entry->birth_date->toDateString()), date: @js(old('measurement_date', $entry->measurement?->measurement_date?->toDateString() ?? now()->toDateString())), posture: @js(old('measurement_posture', $defaultPosture)), recorded: @js((bool) $entry->measurement || old('measurement_posture') !== null) })"
                x-init="updateDefaultMethod()" data-confirm-skip>
                @csrf @method('PUT')
                @if($canUpdateProfile)
                    <div>
                        <label for="caregiver-name" class="block text-sm font-semibold">Mother / Caregiver</label>
                        <x-opt-caregiver-field :options="$caregivers->map(fn ($r) => ['value' => $r->id, 'label' => $r->full_name, 'description' => $r->household?->purok?->display_name])->all()" :name="$caregiverName" :resident-id="$caregiverId" />
                        <p class="mt-2 text-xs text-slate-500">Choose a matching name, or keep the name you typed.</p>
                    </div>
                @else
                    <p class="text-sm text-slate-600">Mother / Caregiver: {{ $entry->caregiver_name ?: '-' }}</p>
                @endif
                <div>
                    <label for="measurement-date" class="block text-sm font-semibold">Measurement Date</label>
                    <input id="measurement-date" type="date" name="measurement_date" required min="{{ $entry->birth_date->toDateString() }}" max="{{ now()->toDateString() }}"
                        x-model="date" @change="updateDefaultMethod()" value="{{ old('measurement_date', $entry->measurement?->measurement_date?->toDateString() ?? now()->toDateString()) }}"
                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-3">
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="weight" class="block text-sm font-semibold">Weight (kg)</label>
                        <input id="weight" name="weight_kg" type="number" required min="0.5" max="60" step="0.01" inputmode="decimal" value="{{ old('weight_kg', $entry->measurement?->weight_kg) }}" class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-3"></div>
                    <div><label for="height" class="block text-sm font-semibold">Height / Length (cm)</label>
                        <input id="height" name="height_cm" type="number" required min="30" max="140" step="0.01" inputmode="decimal" value="{{ old('height_cm', $entry->measurement?->height_cm) }}" class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-3"></div>
                </div>
                <details class="rounded-xl border border-slate-200 p-4" @if(old('remarks') || $errors->has('measurement_posture')) open @endif>
                    <summary class="cursor-pointer text-sm text-slate-600">Measurement method and optional notes</summary>
                    <p class="mt-3 text-xs text-slate-500">Normally measured lying down under 2 years, standing from 2 years. Change this if the child was measured differently; it affects the growth calculation.</p>
                    <label for="measurement-method" class="mt-4 block text-sm">Measured</label>
                    <select id="measurement-method" name="measurement_posture" x-model="posture" @change="methodChanged = true" class="mt-2 block w-full rounded-xl border-slate-300">
                        <option value="recumbent" @selected(old('measurement_posture', $defaultPosture) === 'recumbent')>Lying down</option>
                        <option value="standing" @selected(old('measurement_posture', $defaultPosture) === 'standing')>Standing</option>
                    </select>
                    <label for="measurement-notes" class="mt-4 block text-sm">Notes (optional)</label>
                    <textarea id="measurement-notes" name="remarks" maxlength="1500" rows="2" class="mt-2 block w-full rounded-xl border-slate-300">{{ old('remarks', $entry->measurement?->remarks) }}</textarea>
                </details>
                <button class="w-full rounded-xl bg-tubigon px-5 py-3 font-semibold text-white hover:bg-tubigon-hover sm:w-auto">Save Measurement</button>
            </form>
        @endif
        <details class="mt-7 border-t border-slate-100 pt-5">
            <summary class="cursor-pointer text-sm text-slate-500">More information</summary>
            <p class="mt-3 text-sm text-slate-600">{{ $entry->address }} · {{ $entry->resident_code }}</p>
            @if($entry->measurement)
                <p class="mt-3 text-sm text-slate-600">Measured {{ $entry->measurement->measurement_posture === 'recumbent' ? 'lying down' : 'standing' }}.</p>
                @if($entry->measurement->remarks)<p class="mt-2 text-sm text-slate-600">Notes: {{ $entry->measurement->remarks }}</p>@endif
                <p class="mt-3 text-sm text-slate-600">Growth reference: {{ $entry->measurement->assessment_error ? 'Unavailable for this measurement.' : implode(' / ', array_filter([$entry->measurement->weight_for_age_status, $entry->measurement->height_for_age_status, $entry->measurement->weight_for_length_height_status])) }}</p>
                <p class="mt-1 text-xs text-slate-500">For reference only, not official e-OPT results.</p>
            @endif
            <a href="{{ route('bns.opt-cycles.historical-information', [$cycle, $entry]) }}" class="mt-4 inline-block text-sm font-semibold text-tubigon">Edit Historical Information</a>
        </details>
    </section>
@endsection
