@extends('layouts.portal')

@php
    $routePrefix = $routePrefix ?? 'bhw';
@endphp

@section('title', $pageTitle ?? 'Household Correction Request - HealthLink')
@section('header', $pageHeader ?? 'Household Correction Request')
@section('subheader', $pageSubheader ?? 'Submit household profile corrections for Secretary review, including environmental risk indicators.')

@section('actions')
    <a href="{{ route($routePrefix.'.update-requests.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        Back to Tracking
    </a>
@endsection

@section('content')
    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold">Please review the household correction details.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route($routePrefix.'.update-requests.store-household') }}" class="space-y-6">
        @csrf
        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Household Selection</h2>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <label for="subject_id" class="block text-sm font-medium text-slate-700">Verified Household</label>
                    <select name="subject_id" id="subject_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Select a household</option>
                        @foreach($householdOptions as $householdOption)
                            <option value="{{ $householdOption->id }}" @selected((string) old('subject_id', $selectedHousehold?->id) === (string) $householdOption->id)>
                                {{ $householdOption->full_identifier }} · {{ $householdOption->purok?->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="purok_id" class="block text-sm font-medium text-slate-700">Proposed Purok</label>
                    <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        @foreach($puroks as $purok)
                            <option value="{{ $purok->id }}" @selected((string) old('purok_id', $selectedHousehold?->purok_id) === (string) $purok->id)>{{ $purok->display_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Proposed Household Details</h2>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div><label class="block text-sm font-medium text-slate-700">Household Number</label><input type="text" name="household_no" value="{{ old('household_no', $selectedHousehold?->household_no) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Head of Household</label><select name="head_resident_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"><option value="">No head selected</option>@foreach(($selectedHousehold?->residents ?? collect()) as $resident)<option value="{{ $resident->id }}" @selected((string) old('head_resident_id', $selectedHousehold?->head_resident_id) === (string) $resident->id)>{{ $resident->formal_name }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-slate-700">Household Address</label><input type="text" name="household_address" value="{{ old('household_address', $selectedHousehold?->household_address) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Drinking Water Source</label><input type="text" name="drinking_water_source" value="{{ old('drinking_water_source', $selectedHousehold?->drinking_water_source) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Sanitary Toilet Type</label><input type="text" name="sanitary_toilet_type" value="{{ old('sanitary_toilet_type', $selectedHousehold?->sanitary_toilet_type) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Garbage Disposal Method</label><select name="garbage_disposal_method" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"><option value="">Select a method</option>@foreach($garbageDisposalMethods as $value => $label)<option value="{{ $value }}" @selected(old('garbage_disposal_method', $selectedHousehold?->garbage_disposal_method) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium text-slate-700">Housing Materials</label><select name="housing_material_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"><option value="">Select a type</option>@foreach($housingMaterialTypes as $value => $label)<option value="{{ $value }}" @selected(old('housing_material_type', $selectedHousehold?->housing_material_type) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="md:col-span-2">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><input type="hidden" name="has_sanitary_toilet" value="0"><input type="checkbox" name="has_sanitary_toilet" value="1" @checked(old('has_sanitary_toilet', $selectedHousehold?->has_sanitary_toilet)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon"><span class="text-sm text-slate-700">Has Sanitary Toilet</span></label>
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><input type="hidden" name="has_backyard_garden" value="0"><input type="checkbox" name="has_backyard_garden" value="1" @checked(old('has_backyard_garden', $selectedHousehold?->has_backyard_garden)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon"><span class="text-sm text-slate-700">Has Backyard Garden</span></label>
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><input type="hidden" name="is_social_aid_beneficiary" value="0"><input type="checkbox" name="is_social_aid_beneficiary" value="1" @checked(old('is_social_aid_beneficiary', $selectedHousehold?->is_social_aid_beneficiary)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon"><span class="text-sm text-slate-700">Social Aid Beneficiary</span></label>
                    </div>
                </div>
                <div class="md:col-span-2"><label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $selectedHousehold?->is_active ?? true)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon"><span class="text-sm text-slate-700">Keep household active in the verified registry</span></label></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-slate-700">Reason for Request</label><textarea name="request_reason" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('request_reason') }}</textarea></div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">Submit Household Correction</button>
            <a href="{{ route($routePrefix.'.update-requests.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">Cancel</a>
        </div>
    </form>
@endsection
