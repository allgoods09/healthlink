@if($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <p class="font-semibold">Please review the triage details.</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    @if(! isset($triageRecord))
        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Resident Selection</h2>
            </div>
            <div class="p-6">
                <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Resident</label>
                <select name="resident_id" id="resident_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">Select a resident</option>
                    @foreach($residentOptions as $residentOption)
                        <option value="{{ $residentOption->id }}" @selected((string) old('resident_id', $selectedResident?->id) === (string) $residentOption->id)>
                            {{ $residentOption->formal_name }} · {{ $residentOption->household?->purok?->display_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Triage Measurements</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="measured_at" class="block text-sm font-medium text-slate-700">Measured At</label>
                <input type="datetime-local" name="measured_at" id="measured_at" value="{{ old('measured_at', isset($triageRecord) ? optional($triageRecord->measured_at)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">BP Systolic</label>
                    <input type="number" name="bp_systolic" value="{{ old('bp_systolic', $triageRecord->bp_systolic ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">BP Diastolic</label>
                    <input type="number" name="bp_diastolic" value="{{ old('bp_diastolic', $triageRecord->bp_diastolic ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Heart Rate</label>
                <input type="number" name="heart_rate" value="{{ old('heart_rate', $triageRecord->heart_rate ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Temperature (C)</label>
                <input type="number" step="0.1" name="temperature_celsius" value="{{ old('temperature_celsius', $triageRecord->temperature_celsius ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Respiratory Rate</label>
                <input type="number" name="respiratory_rate" value="{{ old('respiratory_rate', $triageRecord->respiratory_rate ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Blood Glucose (mg/dL)</label>
                <input type="number" step="0.1" name="blood_glucose_mg_dl" value="{{ old('blood_glucose_mg_dl', $triageRecord->blood_glucose_mg_dl ?? '') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Triage Notes</label>
                <textarea name="triage_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('triage_notes', $triageRecord->triage_notes ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
            Save Triage Entry
        </button>
        <a href="{{ route('bhw.triage.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Cancel
        </a>
    </div>
</form>
