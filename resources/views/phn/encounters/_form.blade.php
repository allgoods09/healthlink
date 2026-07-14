@php
    $isEdit = (bool) $clinicalEncounter->exists;
@endphp

@if($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <p class="font-semibold">Please review the encounter details below.</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('phn.encounters.update', $clinicalEncounter) : route('phn.encounters.store') }}" class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Encounter Intake</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            @if($isEdit)
                <div>
                    <p class="text-sm font-medium text-slate-700">Resident</p>
                    <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        {{ $selectedResident?->formal_name ?? 'Unknown resident' }}
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-700">Source</p>
                    <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        {{ $selectedTriage ? 'BHW Triage Intake' : 'Direct Walk-In' }}
                    </div>
                </div>
            @else
                <div>
                    <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Resident</label>
                    <select id="resident_id" name="resident_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Select a resident</option>
                        @foreach($residentOptions as $residentOption)
                            <option value="{{ $residentOption->id }}" @selected((string) old('resident_id', $selectedResident?->id) === (string) $residentOption->id)>
                                {{ $residentOption->formal_name }} · {{ $residentOption->household?->purok?->barangay?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="triage_record_id" class="block text-sm font-medium text-slate-700">Link Pending Triage (Optional)</label>
                    <select id="triage_record_id" name="triage_record_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Direct walk-in / no triage attached</option>
                        @foreach($triageOptions as $triageOption)
                            <option value="{{ $triageOption->id }}" @selected((string) old('triage_record_id', $selectedTriage?->id) === (string) $triageOption->id)>
                                {{ $triageOption->resident?->formal_name ?? 'Unknown resident' }} · {{ $triageOption->measured_at?->format('M d h:i A') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="encountered_at" class="block text-sm font-medium text-slate-700">Encountered At</label>
                <input type="datetime-local" id="encountered_at" name="encountered_at" value="{{ old('encountered_at', optional($clinicalEncounter->encountered_at)->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label for="disposition" class="block text-sm font-medium text-slate-700">Disposition</label>
                <input type="text" id="disposition" name="disposition" value="{{ old('disposition', $clinicalEncounter->disposition) }}" placeholder="Returned home, referred, observed, etc." class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
        </div>

        @if($selectedResident)
            <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-sm text-slate-600">
                <span class="font-medium text-slate-700">{{ $selectedResident->formal_name }}</span>
                · {{ $selectedResident->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                · {{ $selectedResident->household?->purok?->display_name ?? 'Unknown purok' }}
                @if($selectedResident->latestOptMeasurement)
                    · Latest OPT+ {{ $selectedResident->latestOptMeasurement->measurement_date?->format('M d, Y') }}
                @endif
            </div>
        @endif
    </div>

    @if($selectedTriage)
        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Linked BHW Triage Snapshot</h2>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-700">Vitals</p>
                    <p class="mt-2 text-sm text-slate-600">
                        BP: {{ $selectedTriage->bp_systolic && $selectedTriage->bp_diastolic ? "{$selectedTriage->bp_systolic}/{$selectedTriage->bp_diastolic}" : 'N/A' }}<br>
                        Temp: {{ $selectedTriage->temperature_celsius ? "{$selectedTriage->temperature_celsius} C" : 'N/A' }}<br>
                        HR: {{ $selectedTriage->heart_rate ?: 'N/A' }}<br>
                        RR: {{ $selectedTriage->respiratory_rate ?: 'N/A' }}<br>
                        Glucose: {{ $selectedTriage->blood_glucose_mg_dl ? "{$selectedTriage->blood_glucose_mg_dl} mg/dL" : 'N/A' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-700">BHW Notes</p>
                    <p class="mt-2 text-sm text-slate-600">{{ $selectedTriage->triage_notes ?: 'No triage note captured.' }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Clinical Notes and Assessment</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="consultation_notes" class="block text-sm font-medium text-slate-700">Consultation Notes</label>
                <textarea id="consultation_notes" name="consultation_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('consultation_notes', $clinicalEncounter->consultation_notes) }}</textarea>
            </div>
            <div>
                <label for="working_impression" class="block text-sm font-medium text-slate-700">Working Impression / Assessment</label>
                <textarea id="working_impression" name="working_impression" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('working_impression', $clinicalEncounter->working_impression) }}</textarea>
            </div>
            <div>
                <label for="action_taken" class="block text-sm font-medium text-slate-700">Action Taken</label>
                <textarea id="action_taken" name="action_taken" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('action_taken', $clinicalEncounter->action_taken) }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Treatment and Orders</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="medicines_administered" class="block text-sm font-medium text-slate-700">Medicines Administered</label>
                <textarea id="medicines_administered" name="medicines_administered" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('medicines_administered', $clinicalEncounter->medicines_administered) }}</textarea>
            </div>
            <div>
                <label for="lifestyle_advice" class="block text-sm font-medium text-slate-700">Lifestyle Advice</label>
                <textarea id="lifestyle_advice" name="lifestyle_advice" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('lifestyle_advice', $clinicalEncounter->lifestyle_advice) }}</textarea>
            </div>
            <div>
                <label for="referral_notes" class="block text-sm font-medium text-slate-700">Referral Notes</label>
                <textarea id="referral_notes" name="referral_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('referral_notes', $clinicalEncounter->referral_notes) }}</textarea>
            </div>
            <div>
                <label for="return_instructions" class="block text-sm font-medium text-slate-700">Return Instructions</label>
                <textarea id="return_instructions" name="return_instructions" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('return_instructions', $clinicalEncounter->return_instructions) }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Follow-Up and Escalation</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="follow_up_date" class="block text-sm font-medium text-slate-700">Follow-Up Date</label>
                <input type="date" id="follow_up_date" name="follow_up_date" value="{{ old('follow_up_date', optional($clinicalEncounter->follow_up_date)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label for="follow_up_status" class="block text-sm font-medium text-slate-700">Follow-Up Status</label>
                <select id="follow_up_status" name="follow_up_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">No follow-up required</option>
                    <option value="due" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'due')>Due</option>
                    <option value="rescheduled" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'rescheduled')>Rescheduled</option>
                    <option value="completed" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'completed')>Completed</option>
                    <option value="missed" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'missed')>Missed</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="follow_up_notes" class="block text-sm font-medium text-slate-700">Follow-Up Notes</label>
                <textarea id="follow_up_notes" name="follow_up_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('follow_up_notes', $clinicalEncounter->follow_up_notes) }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="hidden" name="is_escalated_to_mho" value="0">
                    <input type="checkbox" name="is_escalated_to_mho" value="1" @checked(old('is_escalated_to_mho', $clinicalEncounter->is_escalated_to_mho)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                    <span class="text-sm text-slate-700">Escalate this case to the MHO</span>
                </label>
            </div>
            <div class="md:col-span-2">
                <label for="escalation_notes" class="block text-sm font-medium text-slate-700">Escalation Notes</label>
                <textarea id="escalation_notes" name="escalation_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('escalation_notes', $clinicalEncounter->escalation_notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
            {{ $isEdit ? 'Update Encounter' : 'Save Encounter' }}
        </button>
        <a href="{{ $isEdit ? route('phn.encounters.show', $clinicalEncounter) : route('phn.encounters.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Cancel
        </a>
    </div>
</form>
