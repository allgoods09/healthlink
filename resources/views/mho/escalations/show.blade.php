@extends('layouts.portal')

@section('title', 'MHO Case Detail - HealthLink')
@section('header', 'Municipal Case Detail')
@section('subheader', 'Final review workspace with PHN intake context, nutrition flags, and the full escalation-to-resolution trail.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('mho.escalations.pdf', $clinicalEncounter) }}" class="inline-flex items-center rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
            Download PDF
        </a>
        @if($clinicalEncounter->mhoReview)
            <a href="{{ route('mho.reviews.edit', $clinicalEncounter) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                Edit Municipal Review
            </a>
        @else
            <a href="{{ route('mho.reviews.create', $clinicalEncounter) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                Finalize Review
            </a>
        @endif
        <a href="{{ route('mho.residents.show', $clinicalEncounter->resident) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Resident Profile
        </a>
        <a href="{{ route('mho.escalations.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Queue
        </a>
    </div>
@endsection

@section('content')
    @php
        $mhoReview = $clinicalEncounter->mhoReview;
    @endphp

    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $clinicalEncounter->resident?->formal_name ?? 'Unknown resident' }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $clinicalEncounter->resident?->household?->purok?->barangay?->name ?? 'Unknown barangay' }}
                    · {{ $clinicalEncounter->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                    · {{ $clinicalEncounter->encounter_source_label }}
                </p>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Escalation Summary</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">PHN Reviewer</dt><dd class="text-slate-900">{{ $clinicalEncounter->attendedBy?->name ?? 'Unknown PHN' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Encountered At</dt><dd class="text-slate-900">{{ $clinicalEncounter->encountered_at?->format('F j, Y h:i A') ?? 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Escalated At</dt><dd class="text-slate-900">{{ $clinicalEncounter->escalated_at?->format('F j, Y h:i A') ?? 'Not timestamped' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Clinical Status</dt><dd class="text-slate-900">{{ $clinicalEncounter->clinical_status_label }}</dd></div>
                    </dl>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Resident Context</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">Resident Code</dt><dd class="text-slate-900">{{ $clinicalEncounter->resident?->official_resident_code ?? 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Household</dt><dd class="text-slate-900">{{ $clinicalEncounter->household?->full_identifier ?? 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Head of Household</dt><dd class="text-slate-900">{{ $clinicalEncounter->resident?->household?->headResident?->formal_name ?? 'No head assigned' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Latest OPT+</dt><dd class="text-slate-900">{{ $clinicalEncounter->resident?->latestOptMeasurement?->measurement_date?->format('F j, Y') ?? 'No OPT+ record yet' }}</dd></div>
                    </dl>
                </div>
                <div class="md:col-span-2">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">PHN Consultation Notes</h3>
                    <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">{{ $clinicalEncounter->consultation_notes ?: 'No PHN consultation note captured.' }}</div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Working Impression</h3>
                    <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">{{ $clinicalEncounter->working_impression ?: 'No working impression recorded.' }}</div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Escalation Notes</h3>
                    <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">{{ $clinicalEncounter->escalation_notes ?: 'No PHN escalation note recorded.' }}</div>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Municipal Resolution</h3>
                </div>
                <div class="space-y-4 p-6 text-sm">
                    @if($mhoReview)
                        <div><p class="font-medium text-slate-500">Reviewed By</p><p class="mt-1 text-slate-900">{{ $mhoReview->reviewedBy?->name ?? 'Unknown MHO' }}</p></div>
                        <div><p class="font-medium text-slate-500">Reviewed At</p><p class="mt-1 text-slate-900">{{ $mhoReview->reviewed_at?->format('F j, Y h:i A') ?? 'N/A' }}</p></div>
                        <div><p class="font-medium text-slate-500">Final Disposition</p><p class="mt-1 text-slate-900">{{ $mhoReview->final_disposition ?: 'No disposition recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Final Assessment</p><p class="mt-1 text-slate-900">{{ $mhoReview->final_assessment ?: 'No final assessment recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Diagnostic Override</p><p class="mt-1 text-slate-900">{{ $mhoReview->diagnostic_override ?: 'No override recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Prescription Notes</p><p class="mt-1 text-slate-900">{{ $mhoReview->prescription_notes ?: 'No prescription note recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Referral Destination</p><p class="mt-1 text-slate-900">{{ $mhoReview->referral_destination ?: 'No referral destination recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Return Instructions</p><p class="mt-1 text-slate-900">{{ $mhoReview->return_instructions ?: 'No return instruction recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Resolution Notes</p><p class="mt-1 text-slate-900">{{ $mhoReview->resolution_notes ?: 'No resolution note recorded.' }}</p></div>
                    @else
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                            This case is still waiting for the MHO’s final decision. Use the municipal review flow to add the final assessment, prescriptions, disposition, and follow-up resolution.
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Follow-Up State</h3>
                </div>
                <div class="space-y-4 p-6 text-sm">
                    <div><p class="font-medium text-slate-500">Follow-Up Status</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->follow_up_status_label }}</p></div>
                    <div><p class="font-medium text-slate-500">Follow-Up Date</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->follow_up_date?->format('F j, Y') ?? 'No follow-up date set' }}</p></div>
                    <div><p class="font-medium text-slate-500">Follow-Up Notes</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->follow_up_notes ?: 'No follow-up note recorded.' }}</p></div>
                    <div><p class="font-medium text-slate-500">Case Closed At</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->closed_at?->format('F j, Y h:i A') ?? 'Still open' }}</p></div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Linked BHW Triage and PHN Orders</h3>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">BHW Triage Snapshot</h4>
                    @if($clinicalEncounter->triageRecord)
                        <div class="mt-4 space-y-3 text-sm text-slate-600">
                            <p>Recorded by <span class="font-semibold text-slate-900">{{ $clinicalEncounter->triageRecord?->recordedBy?->name ?? 'Unknown BHW' }}</span></p>
                            <p>Measured at <span class="font-semibold text-slate-900">{{ $clinicalEncounter->triageRecord?->measured_at?->format('F j, Y h:i A') ?? 'N/A' }}</span></p>
                            <p>BP <span class="font-semibold text-slate-900">{{ $clinicalEncounter->triageRecord?->bp_systolic && $clinicalEncounter->triageRecord?->bp_diastolic ? "{$clinicalEncounter->triageRecord->bp_systolic}/{$clinicalEncounter->triageRecord->bp_diastolic}" : 'N/A' }}</span></p>
                            <p>Temperature <span class="font-semibold text-slate-900">{{ $clinicalEncounter->triageRecord?->temperature_celsius ? "{$clinicalEncounter->triageRecord->temperature_celsius} C" : 'N/A' }}</span></p>
                            <p>Heart rate <span class="font-semibold text-slate-900">{{ $clinicalEncounter->triageRecord?->heart_rate ?: 'N/A' }}</span></p>
                            <p class="text-slate-900">{{ $clinicalEncounter->triageRecord?->triage_notes ?: 'No BHW triage note recorded.' }}</p>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500">This case was logged as a direct RHU walk-in without a linked BHW triage record.</p>
                    @endif
                </div>
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">PHN Treatment Notes</h4>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <p><span class="font-medium text-slate-500">Action Taken</span><br><span class="text-slate-900">{{ $clinicalEncounter->action_taken ?: 'No action recorded.' }}</span></p>
                        <p><span class="font-medium text-slate-500">Medicines Administered</span><br><span class="text-slate-900">{{ $clinicalEncounter->medicines_administered ?: 'No medicine recorded.' }}</span></p>
                        <p><span class="font-medium text-slate-500">Lifestyle Advice</span><br><span class="text-slate-900">{{ $clinicalEncounter->lifestyle_advice ?: 'No lifestyle advice recorded.' }}</span></p>
                        <p><span class="font-medium text-slate-500">Referral Notes</span><br><span class="text-slate-900">{{ $clinicalEncounter->referral_notes ?: 'No referral note recorded.' }}</span></p>
                        <p><span class="font-medium text-slate-500">Return Instructions</span><br><span class="text-slate-900">{{ $clinicalEncounter->return_instructions ?: 'No return instruction recorded.' }}</span></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Nutrition Flags and Related History</h3>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($clinicalEncounter->resident?->nutritionFlags ?? [] as $flag)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ ucfirst($flag->flag_status) }} flag</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $flag->flagged_at?->format('M d, Y h:i A') ?? 'No timestamp' }} · {{ $flag->flaggedBy?->name ?? 'Unknown BHW' }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $flag->flag_reason ?: 'No flag reason recorded.' }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No recent nutrition flags are attached to this resident.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Recent Encounter History</h3>
            <p class="text-sm text-slate-500">Previous PHN or municipal clinical touchpoints linked to this resident.</p>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse($recentResidentEncounters as $encounter)
                <div class="flex flex-col gap-3 px-6 py-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $encounter->encountered_at?->format('F j, Y h:i A') }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $encounter->attendedBy?->name ?? 'Unknown PHN' }}
                            · {{ $encounter->clinical_status_label }}
                        </p>
                        <p class="mt-2 text-sm text-slate-600">{{ $encounter->working_impression ?: ($encounter->consultation_notes ?: 'No note recorded.') }}</p>
                    </div>
                    @if($encounter->is_escalated_to_mho)
                        <a href="{{ route('mho.escalations.show', $encounter) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Case</a>
                    @endif
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    No earlier encounter history exists for this resident yet.
                </div>
            @endforelse
        </div>
    </div>
@endsection
