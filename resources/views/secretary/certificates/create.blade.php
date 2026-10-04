@extends('layouts.portal')

@section('title', 'Issue Certificate - HealthLink Secretary')
@section('header', 'Issue Barangay Certificate')
@section('subheader', 'Choose a certificate and recipient, then review before issuing.')

@section('actions')
    <x-record-action :href="route('secretary.certificates.index')" variant="back">Back to Log</x-record-action>
@endsection

@php
    $residentOptions = $residents->map(fn ($resident) => [
        'value' => (string) $resident->id, 'label' => $resident->formal_name, 'printedName' => $resident->formal_name,
        'description' => 'Household #'.$resident->household?->household_no.' · '.$resident->household?->purok?->display_name,
        'detail' => 'Resident Code: '.($resident->official_resident_code ?: 'Not assigned'),
        'search' => $resident->formal_name.' '.$resident->official_resident_code.' '.$resident->household?->household_no.' '.$resident->household?->purok?->display_name,
        'ranking' => [
            'kind' => 'resident', 'primaryIdentifier' => $resident->official_resident_code,
            'primaryName' => $resident->formal_name, 'nameAliases' => [$resident->full_name],
            'secondaryFields' => [$resident->household?->household_no, $resident->household?->purok?->display_name],
        ],
    ])->values()->all();
    $householdOptions = $households->map(fn ($household) => [
        'value' => (string) $household->id, 'label' => 'Household #'.$household->household_no,
        'printedName' => $household->headResident?->formal_name ?: 'Household #'.$household->household_no,
        'description' => $household->purok?->display_name,
        'detail' => 'Head: '.($household->headResident?->formal_name ?: 'No assigned head yet'),
        'search' => $household->household_no.' '.$household->household_address.' '.$household->purok?->display_name.' '.$household->headResident?->formal_name,
        'ranking' => [
            'kind' => 'household', 'primaryIdentifier' => $household->household_no,
            'primaryName' => 'Household #'.$household->household_no, 'purok' => $household->purok?->display_name,
            'secondaryFields' => [$household->household_address, $household->purok?->display_name, $household->headResident?->formal_name],
        ],
    ])->values()->all();
    $errorStep = $initialStep;
    $wizard = [
        'step' => $errorStep, 'certificateType' => old('certificate_type', ''),
        'issuedAt' => old('issued_at', $issuedAtLocal), 'recipientType' => old('recipient_type', 'resident'),
        'residentId' => old('resident_id', ''), 'householdId' => old('household_id', ''),
        'purpose' => old('purpose', ''), 'remarks' => old('remarks', ''),
        'usePrintedName' => (bool) old('use_printed_name', false), 'overrideName' => old('issued_to_name', ''),
        'officialSecretary' => $officialSecretary, 'residents' => $residentOptions, 'households' => $householdOptions,
    ];
    $steps = [1 => 'Certificate', 2 => 'Recipient', 3 => 'Details', 4 => 'Review & Issue'];
@endphp

@section('content')
    <div x-data="certificateWizard(@js($wizard))" @certificate-recipient-selected="selectRecipient($event.detail)" data-certificate-wizard data-certificate-start-step="{{ $errorStep }}">
        <noscript><p class="mb-4 text-sm text-amber-700">Enable JavaScript to use the certificate wizard and recipient search.</p></noscript>
        <nav aria-label="Certificate issuance steps" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach($steps as $number => $label)
                <div :aria-current="step === {{ $number }} ? 'step' : null"
                    :class="step === {{ $number }} ? 'border-tubigon bg-tubigon/5 text-tubigon' : (step > {{ $number }} ? 'border-teal-200 bg-teal-50 text-teal-800' : 'border-slate-200 bg-white text-slate-500')"
                    class="rounded-lg border px-4 py-3 text-sm font-semibold">
                    {{ $number }}. {{ $label }}
                    <span class="block text-xs font-normal" x-text="step === {{ $number }} ? 'Current step' : (step > {{ $number }} ? 'Completed' : 'Upcoming')"></span>
                </div>
            @endforeach
        </nav>
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 x-ref="stepHeading" tabindex="-1" class="text-xl font-semibold text-slate-900" x-text="['', '1. Certificate', '2. Recipient', '3. Details', '4. Review & Issue'][step]">Issue Certificate</h2>
            <form method="POST" action="{{ route('secretary.certificates.store') }}" @submit="submit($event)" novalidate data-confirm-skip data-filter-panel="false" class="mt-6 space-y-6">
                @csrf
                <input type="hidden" name="review_token" value="{{ $reviewToken }}">
                <div x-show="step === 1" class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="certificate_type" class="block text-sm font-medium text-slate-700">Certificate Type</label>
                        <select id="certificate_type" name="certificate_type" x-model="certificateType" required class="mt-2 block w-full rounded-lg border-slate-300">
                            <option value="">Select certificate type</option>
                            <option value="barangay_clearance">Barangay Clearance</option>
                            <option value="certificate_of_indigency">Certificate of Indigency</option>
                        </select>
                        <x-input-error :messages="$errors->get('certificate_type')" />
                    </div>
                    <div>
                        <label for="issued_at" class="block text-sm font-medium text-slate-700">Issued At (Philippine time)</label>
                        <input type="datetime-local" id="issued_at" name="issued_at" value="{{ old('issued_at', $issuedAtLocal) }}" x-model="issuedAt" required class="mt-2 block w-full rounded-lg border-slate-300">
                        <p class="mt-2 text-xs text-slate-500">Asia/Manila (UTC+8)</p>
                        <x-input-error :messages="$errors->get('issued_at')" />
                    </div>
                </div>
                <div x-show="step === 2" x-cloak class="space-y-5">
                    <fieldset><legend class="text-sm font-medium text-slate-700">Recipient Type</legend>
                        <div class="mt-2 flex flex-wrap gap-5">
                            @foreach(['resident' => 'Resident', 'household' => 'Household'] as $type => $label)
                                <label class="inline-flex items-center gap-2"><input type="radio" name="recipient_type" value="{{ $type }}" :checked="recipientType === '{{ $type }}'" @change="changeRecipientType('{{ $type }}')">{{ $label }}</label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('recipient_type')" />
                    </fieldset>
                    @foreach(['resident' => $residentOptions, 'household' => $householdOptions] as $type => $options)
                        <fieldset x-show="recipientType === '{{ $type }}'" :disabled="recipientType !== '{{ $type }}'">
                            <label for="{{ $type }}_id" class="block text-sm font-medium text-slate-700">Search / Select {{ ucfirst($type) }}</label>
                            <x-searchable-record-select :name="$type.'_id'" :id="$type.'_id'" :options="$options" :selected="old($type.'_id')"
                                :placeholder="'Search '.$type" x-init="$watch('selectedValue', value => $dispatch('certificate-recipient-selected', { type: '{{ $type }}', id: value }))"
                                @certificate-recipient-reset.window="selectedValue = ''; syncQueryToSelection(); syncValidity()" />
                            <x-input-error :messages="$errors->get($type.'_id')" />
                        </fieldset>
                    @endforeach
                    <div x-show="recipient" class="rounded-lg border border-slate-200 bg-slate-50 p-4" role="status">
                        <p class="font-semibold text-slate-900" x-text="recipient?.label"></p>
                        <p class="text-sm text-slate-600" x-text="recipientType === 'resident' ? 'Resident' : 'Household'"></p>
                        <p class="mt-2 text-sm text-slate-700" x-text="recipient?.description"></p>
                        <p class="text-sm text-slate-700" x-text="recipient?.detail"></p>
                    </div>
                </div>
                <div x-show="step === 3" x-cloak class="space-y-5">
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-slate-700">Purpose</label>
                        <textarea id="purpose" name="purpose" x-model="purpose" maxlength="255" required rows="3" class="mt-2 block w-full rounded-lg border-slate-300">{{ old('purpose') }}</textarea>
                        <p class="mt-1 text-xs text-slate-500" x-text="Array.from(purpose).length + ' / 255 characters'"></p>
                        <x-input-error :messages="$errors->get('purpose')" />
                    </div>
                    <div>
                        <label for="remarks" class="block text-sm font-medium text-slate-700">Remarks (Optional)</label>
                        <textarea id="remarks" name="remarks" x-model="remarks" maxlength="2000" rows="3" class="mt-2 block w-full rounded-lg border-slate-300">{{ old('remarks') }}</textarea>
                        <x-input-error :messages="$errors->get('remarks')" />
                    </div>
                    <div>
                        <input type="hidden" name="use_printed_name" value="0">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="use_printed_name" value="1" x-model="usePrintedName">Use a different printed recipient name</label>
                        <div x-show="usePrintedName" class="mt-3">
                            <label for="issued_to_name" class="block text-sm font-medium text-slate-700">Printed Recipient Name</label>
                            <input id="issued_to_name" name="issued_to_name" x-model="overrideName" :disabled="!usePrintedName" maxlength="255" class="mt-2 block w-full rounded-lg border-slate-300">
                            <x-input-error :messages="$errors->get('issued_to_name')" />
                        </div>
                    </div>
                </div>
                <div x-show="step === 4" x-cloak class="space-y-5">
                    <p class="text-sm font-semibold uppercase tracking-wider text-tubigon" x-text="certificateType === 'barangay_clearance' ? 'Barangay Clearance' : 'Certificate of Indigency'"></p>
                    <dl class="grid gap-5 sm:grid-cols-2">
                        @foreach(['Recipient' => 'recipient?.label', 'Recipient Type' => "recipientType === 'resident' ? 'Resident' : 'Household'", 'Location' => 'recipient?.description', 'Printed Name' => 'printedName', 'Purpose' => 'purpose', 'Remarks' => "remarks || 'None'", 'Issued At (Philippine time)' => 'localTimeLabel'] as $label => $expression)
                            <div class="min-w-0"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm font-medium text-slate-900" x-text="{{ $expression }}"></dd></div>
                        @endforeach
                        <div><dt class="text-sm text-slate-500">Official Barangay Secretary</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $officialSecretary ?: 'Not assigned' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Issued By</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $issuerName }}</dd></div>
                    </dl>
                    <p class="text-xs text-slate-500">The recipient and official Secretary are checked again when you issue.</p>
                    <x-input-error :messages="$errors->get('review_token')" />
                    <x-input-error :messages="$errors->get('signatory_name_at_issuance')" />
                </div>
                <div class="flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-5">
                    <x-record-action x-show="step > 1" @click="go(step - 1)" variant="back">Back</x-record-action>
                    <div class="ml-auto flex flex-wrap items-center gap-3">
                        <p x-show="!validStep(step)" class="text-xs text-slate-500" role="status">Complete the required fields to continue.</p>
                        <x-record-action x-show="step < 4" @click="go(step + 1)" x-bind:disabled="!validStep(step)" variant="edit"><span x-text="step === 3 ? 'Review' : 'Next'">Next</span></x-record-action>
                        <x-record-action x-show="step === 4" type="submit" data-certificate-issue x-bind:disabled="!validStep(4)" variant="add">Issue Certificate</x-record-action>
                    </div>
                </div>
            </form>
        </section>
    </div>
@endsection
