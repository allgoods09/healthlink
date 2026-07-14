@extends('layouts.portal')

@section('title', 'Add Micronutrient Log - HealthLink')
@section('header', 'Add Micronutrient Log')
@section('subheader', 'Record supplementation events for toddlers, pregnant women, and lactating mothers from the verified resident pool.')

@section('actions')
    <a href="{{ route('bns.micronutrients.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        Back to Logs
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-4xl rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Supplementation Details</h2>
            <p class="mt-1 text-sm text-slate-500">Recipient validation follows the current maternal profile and age-based child eligibility rules.</p>
        </div>
        <div class="p-6">
            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <p class="font-semibold">Please review the supplementation log details.</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('bns.micronutrients.store') }}" class="space-y-6">
                @csrf

                <div>
                    <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Resident</label>
                    <select name="resident_id" id="resident_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Select a resident</option>
                        @foreach($residentOptions as $residentOption)
                            <option value="{{ $residentOption->id }}" @selected((string) old('resident_id') === (string) $residentOption->id)>
                                {{ $residentOption->formal_name }} · {{ $residentOption->household?->purok?->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="supplement_type" class="block text-sm font-medium text-slate-700">Supplement Type</label>
                        <select name="supplement_type" id="supplement_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            @foreach($supplementTypes as $supplementType => $label)
                                <option value="{{ $supplementType }}" @selected(old('supplement_type') === $supplementType)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="recipient_category" class="block text-sm font-medium text-slate-700">Recipient Category</label>
                        <select name="recipient_category" id="recipient_category" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            @foreach($recipientCategories as $recipientCategory => $label)
                                <option value="{{ $recipientCategory }}" @selected(old('recipient_category') === $recipientCategory)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="administered_on" class="block text-sm font-medium text-slate-700">Administration Date</label>
                        <input type="date" name="administered_on" id="administered_on" value="{{ old('administered_on', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                    <div>
                        <label for="dose_description" class="block text-sm font-medium text-slate-700">Dose Description</label>
                        <input type="text" name="dose_description" id="dose_description" value="{{ old('dose_description') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="e.g. 1 capsule, 5 ml, 1 sachet">
                    </div>
                </div>

                <div>
                    <label for="remarks" class="block text-sm font-medium text-slate-700">Remarks</label>
                    <textarea name="remarks" id="remarks" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('remarks') }}</textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Save Supplementation Log
                    </button>
                    <a href="{{ route('bns.micronutrients.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
