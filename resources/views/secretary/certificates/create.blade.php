@extends('layouts.portal')

@section('title', 'Issue Certificate - HealthLink Secretary')
@section('header', 'Issue Barangay Certificate')
@section('subheader', 'Create an official certificate record tied to a verified active resident or household inside your assigned barangay.')

@section('actions')
    <a href="{{ route('secretary.certificates.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
        Back to Log
    </a>
@endsection

@section('content')
    @php
        $residentSearchOptions = $residents->map(fn ($resident) => [
            'value' => $resident->id,
            'label' => $resident->formal_name,
            'description' => 'Household #'.$resident->household?->household_no.' · '.($resident->household?->purok?->display_name ?? 'Unknown purok'),
            'search' => collect([
                $resident->formal_name,
                $resident->official_resident_code,
                $resident->household?->household_no ? 'household '.$resident->household->household_no : null,
                $resident->household?->purok?->display_name,
            ])->filter()->implode(' '),
        ])->values()->all();
        $householdSearchOptions = $households->map(fn ($household) => [
            'value' => $household->id,
            'label' => 'Household #'.$household->household_no,
            'description' => ($household->purok?->display_name ?? 'Unknown purok').' · '.($household->headResident?->formal_name ?: 'No assigned head yet'),
            'search' => collect([
                $household->household_no ? 'household '.$household->household_no : null,
                $household->household_address,
                $household->purok?->display_name,
                $household->headResident?->formal_name,
            ])->filter()->implode(' '),
        ])->values()->all();
    @endphp

    <div class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-6">
            <form method="POST" action="{{ route('secretary.certificates.store') }}" x-data="{ recipientType: '{{ old('recipient_type', 'resident') }}' }">
                @csrf

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="certificate_type" class="block text-sm font-medium text-slate-700">Certificate Type</label>
                        <select name="certificate_type" id="certificate_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('certificate_type') border-red-500 @enderror" required>
                            <option value="">Select certificate type</option>
                            <option value="barangay_clearance" {{ old('certificate_type') === 'barangay_clearance' ? 'selected' : '' }}>Barangay Clearance</option>
                            <option value="certificate_of_indigency" {{ old('certificate_type') === 'certificate_of_indigency' ? 'selected' : '' }}>Certificate of Indigency</option>
                        </select>
                        @error('certificate_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="issued_at" class="block text-sm font-medium text-slate-700">Issued At</label>
                        <input type="datetime-local" name="issued_at" id="issued_at" value="{{ old('issued_at', now()->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('issued_at') border-red-500 @enderror" required>
                        @error('issued_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Recipient Type</label>
                        <div class="mt-2 flex flex-col gap-3 md:flex-row">
                            <label class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-3">
                                <input type="radio" name="recipient_type" value="resident" x-model="recipientType" class="border-slate-300 text-tubigon focus:ring-tubigon">
                                <span class="ml-3 text-sm text-slate-700">Resident</span>
                            </label>
                            <label class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-3">
                                <input type="radio" name="recipient_type" value="household" x-model="recipientType" class="border-slate-300 text-tubigon focus:ring-tubigon">
                                <span class="ml-3 text-sm text-slate-700">Household</span>
                            </label>
                        </div>
                        @error('recipient_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="recipientType === 'resident'" x-cloak class="md:col-span-2">
                        <label for="resident_id" class="block text-sm font-medium text-slate-700">Resident</label>
                        <x-searchable-record-select
                            name="resident_id"
                            id="resident_id"
                            :options="$residentSearchOptions"
                            :selected="old('resident_id')"
                            placeholder="Search resident name"
                            empty-message="No resident matches your search."
                        />
                        @error('resident_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="recipientType === 'household'" x-cloak class="md:col-span-2">
                        <label for="household_id" class="block text-sm font-medium text-slate-700">Household</label>
                        <x-searchable-record-select
                            name="household_id"
                            id="household_id"
                            :options="$householdSearchOptions"
                            :selected="old('household_id')"
                            placeholder="Search household number or head name"
                            empty-message="No household matches your search."
                        />
                        @error('household_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="issued_to_name" class="block text-sm font-medium text-slate-700">Issued To Name (Optional Override)</label>
                        <input type="text" name="issued_to_name" id="issued_to_name" value="{{ old('issued_to_name') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('issued_to_name') border-red-500 @enderror" placeholder="Leave blank to use the selected resident or household default">
                        @error('issued_to_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="purpose" class="block text-sm font-medium text-slate-700">Purpose</label>
                        <textarea name="purpose" id="purpose" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('purpose') border-red-500 @enderror" required>{{ old('purpose') }}</textarea>
                        @error('purpose')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="remarks" class="block text-sm font-medium text-slate-700">Remarks</label>
                        <textarea name="remarks" id="remarks" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon @error('remarks') border-red-500 @enderror">{{ old('remarks') }}</textarea>
                        @error('remarks')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Issue Certificate
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
