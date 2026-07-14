@php
    $isEdit = (bool) $mhoReview->exists;
@endphp

@if($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <p class="font-semibold">Please review the municipal decision details below.</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('mho.reviews.update', $clinicalEncounter) : route('mho.reviews.store', $clinicalEncounter) }}" class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Escalated Case Context</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <p class="text-sm font-medium text-slate-700">Resident</p>
                <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    {{ $clinicalEncounter->resident?->formal_name ?? 'Unknown resident' }}
                </div>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-700">Barangay and Purok</p>
                <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    {{ $clinicalEncounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                    · {{ $clinicalEncounter->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                </div>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-700">PHN Reviewer</p>
                <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    {{ $clinicalEncounter->attendedBy?->name ?? 'Unknown PHN' }}
                </div>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-700">Escalated At</p>
                <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    {{ $clinicalEncounter->escalated_at?->format('F j, Y h:i A') ?? 'Not timestamped' }}
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-sm text-slate-600">
            <span class="font-medium text-slate-700">PHN Working Impression:</span>
            {{ $clinicalEncounter->working_impression ?: 'No working impression recorded.' }}
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Municipal Clinical Decision</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="reviewed_at" class="block text-sm font-medium text-slate-700">Reviewed At</label>
                <input type="datetime-local" id="reviewed_at" name="reviewed_at" value="{{ old('reviewed_at', optional($mhoReview->reviewed_at)->format('Y-m-d\\TH:i')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div>
                <label for="final_disposition" class="block text-sm font-medium text-slate-700">Final Disposition</label>
                <input type="text" id="final_disposition" name="final_disposition" value="{{ old('final_disposition', $mhoReview->final_disposition) }}" placeholder="Discharged, admitted, referred, etc." class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div class="md:col-span-2">
                <label for="final_assessment" class="block text-sm font-medium text-slate-700">Final Assessment</label>
                <textarea id="final_assessment" name="final_assessment" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('final_assessment', $mhoReview->final_assessment) }}</textarea>
            </div>
            <div>
                <label for="diagnostic_override" class="block text-sm font-medium text-slate-700">Diagnostic Override</label>
                <textarea id="diagnostic_override" name="diagnostic_override" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('diagnostic_override', $mhoReview->diagnostic_override) }}</textarea>
            </div>
            <div>
                <label for="resolution_notes" class="block text-sm font-medium text-slate-700">Resolution Notes</label>
                <textarea id="resolution_notes" name="resolution_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('resolution_notes', $mhoReview->resolution_notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Prescriptions, Referrals, and Return Instructions</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="prescription_notes" class="block text-sm font-medium text-slate-700">Prescription Notes</label>
                <textarea id="prescription_notes" name="prescription_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('prescription_notes', $mhoReview->prescription_notes) }}</textarea>
            </div>
            <div>
                <label for="referral_destination" class="block text-sm font-medium text-slate-700">Referral Destination</label>
                <input type="text" id="referral_destination" name="referral_destination" value="{{ old('referral_destination', $mhoReview->referral_destination) }}" placeholder="Hospital, specialist, provincial unit, etc." class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div class="md:col-span-2">
                <label for="return_instructions" class="block text-sm font-medium text-slate-700">Return Instructions</label>
                <textarea id="return_instructions" name="return_instructions" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('return_instructions', $mhoReview->return_instructions) }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Follow-Up Resolution</h2>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="follow_up_status" class="block text-sm font-medium text-slate-700">Follow-Up Status</label>
                <select id="follow_up_status" name="follow_up_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    <option value="">No follow-up required / close case</option>
                    <option value="due" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'due')>Due</option>
                    <option value="rescheduled" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'rescheduled')>Rescheduled</option>
                    <option value="completed" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'completed')>Completed</option>
                    <option value="missed" @selected(old('follow_up_status', $clinicalEncounter->follow_up_status) === 'missed')>Missed</option>
                </select>
            </div>
            <div>
                <label for="follow_up_date" class="block text-sm font-medium text-slate-700">Follow-Up Date</label>
                <input type="date" id="follow_up_date" name="follow_up_date" value="{{ old('follow_up_date', optional($clinicalEncounter->follow_up_date)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            </div>
            <div class="md:col-span-2">
                <label for="follow_up_notes" class="block text-sm font-medium text-slate-700">Follow-Up Notes</label>
                <textarea id="follow_up_notes" name="follow_up_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('follow_up_notes', $clinicalEncounter->follow_up_notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
            {{ $isEdit ? 'Update Municipal Review' : 'Save Municipal Review' }}
        </button>
        <a href="{{ route('mho.escalations.show', $clinicalEncounter) }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Cancel
        </a>
    </div>
</form>
