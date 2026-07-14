@extends('layouts.portal')

@section('title', 'Log OPT+ Measurement - HealthLink')
@section('header', 'Log OPT+ Measurement')
@section('subheader', 'Record official anthropometric measurements against verified child profiles in your barangay.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.watchlist.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            View TCL
        </a>
        <a href="{{ route('bns.opt-measurements.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Measurements
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Measurement Entry</h2>
                <p class="mt-1 text-sm text-slate-500">WHO-based nutritional statuses are computed automatically after save.</p>
            </div>

            <div class="p-6">
                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <p class="font-semibold">Please review the measurement details.</p>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($campaignPeriods->isEmpty())
                    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p class="font-semibold">Create an OPT+ campaign first.</p>
                        <p class="mt-1">This form needs a campaign period so measurements are attached to the correct field cycle.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('bns.opt-measurements.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Child Profile</label>
                        <select name="resident_id" id="resident_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">Select a verified child</option>
                            @foreach($residentOptions as $residentOption)
                                <option value="{{ $residentOption->id }}" @selected((string) old('resident_id', $selectedResident?->id) === (string) $residentOption->id)>
                                    {{ $residentOption->formal_name }} · {{ $residentOption->household?->purok?->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="campaign_period_id" class="block text-sm font-medium text-slate-700">OPT+ Campaign Period</label>
                        <select name="campaign_period_id" id="campaign_period_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">Select a campaign period</option>
                            @foreach($campaignPeriods as $campaignPeriod)
                                <option value="{{ $campaignPeriod->id }}" @selected((string) old('campaign_period_id', $activeCampaign?->id) === (string) $campaignPeriod->id)>
                                    {{ $campaignPeriod->name }}{{ $campaignPeriod->is_active ? ' · Active' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="measurement_date" class="block text-sm font-medium text-slate-700">Measurement Date</label>
                            <input type="date" name="measurement_date" id="measurement_date" value="{{ old('measurement_date', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label for="measurement_posture" class="block text-sm font-medium text-slate-700">Measurement Posture</label>
                            <select name="measurement_posture" id="measurement_posture" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <option value="{{ \App\Models\OptMeasurement::POSTURE_RECUMBENT }}" @selected(old('measurement_posture', \App\Models\OptMeasurement::POSTURE_RECUMBENT) === \App\Models\OptMeasurement::POSTURE_RECUMBENT)>Recumbent Length</option>
                                <option value="{{ \App\Models\OptMeasurement::POSTURE_STANDING }}" @selected(old('measurement_posture') === \App\Models\OptMeasurement::POSTURE_STANDING)>Standing Height</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="weight_kg" class="block text-sm font-medium text-slate-700">Weight (kg)</label>
                            <input type="number" step="0.01" min="0.5" max="60" name="weight_kg" id="weight_kg" value="{{ old('weight_kg') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="e.g. 8.45">
                        </div>
                        <div>
                            <label for="height_cm" class="block text-sm font-medium text-slate-700">Length / Height (cm)</label>
                            <input type="number" step="0.01" min="30" max="140" name="height_cm" id="height_cm" value="{{ old('height_cm') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="e.g. 72.10">
                        </div>
                    </div>

                    <div>
                        <label for="remarks" class="block text-sm font-medium text-slate-700">Remarks</label>
                        <textarea name="remarks" id="remarks" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="Optional notes from the measurement session or household visit.">{{ old('remarks') }}</textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover" @disabled($campaignPeriods->isEmpty())>
                            Save Measurement
                        </button>
                        <a href="{{ route('bns.opt-measurements.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">WHO Measurement Notes</h3>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                    <li>Children under 24 months are assessed on recumbent length tables.</li>
                    <li>If posture and age do not match, the WHO conversion rule of 0.7 cm is applied automatically.</li>
                    <li>Weight-for-age, height-for-age, and weight-for-length/height statuses are computed at save time from the embedded WHO tables.</li>
                </ul>
            </section>

            <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Resident Scope</h3>
                @if($selectedResident)
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">{{ $selectedResident->formal_name }}</p>
                        <p class="mt-1">{{ $selectedResident->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $selectedResident->official_resident_code }}</p>
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-600">
                        Only verified children from your assigned barangay who are within official OPT+ under-five scope appear in this form.
                    </p>
                @endif
            </section>
        </aside>
    </div>
@endsection
