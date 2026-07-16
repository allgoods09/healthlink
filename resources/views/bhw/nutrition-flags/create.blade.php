@extends('layouts.portal')

@section('title', 'New Nutrition Flag - HealthLink')
@section('header', 'New Nutrition Flag')
@section('subheader', 'Flag an at-risk child for BNS assessment using the verified resident pool.')

@section('content')
    @php
        $residentSearchOptions = $residentOptions->map(fn ($residentOption) => [
            'value' => $residentOption->id,
            'label' => $residentOption->formal_name,
            'description' => $residentOption->household?->purok?->display_name ?? 'Unknown purok',
            'search' => collect([
                $residentOption->formal_name,
                $residentOption->official_resident_code,
                $residentOption->household?->purok?->display_name,
            ])->filter()->implode(' '),
        ])->values()->all();
    @endphp

    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold">Please review the nutrition flag details.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('bhw.nutrition-flags.store') }}" class="space-y-6">
        @csrf
        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Flag Details</h2>
            </div>
            <div class="grid gap-6 p-6">
                <div>
                    <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Child</label>
                    <x-searchable-record-select
                        name="resident_id"
                        id="resident_id"
                        :options="$residentSearchOptions"
                        :selected="old('resident_id', $selectedResident?->id)"
                        placeholder="Search child name"
                        empty-message="No verified child matches your search."
                        required
                    />
                </div>
                <div>
                    <label for="flag_reason" class="block text-sm font-medium text-slate-700">Reason for Referral</label>
                    <textarea name="flag_reason" id="flag_reason" rows="5" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('flag_reason') }}</textarea>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">Submit Nutrition Flag</button>
            <a href="{{ route('bhw.nutrition-flags.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">Cancel</a>
        </div>
    </form>
@endsection
