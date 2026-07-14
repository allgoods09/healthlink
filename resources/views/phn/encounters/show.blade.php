@extends('layouts.portal')

@section('title', 'PHN Encounter Detail - HealthLink')
@section('header', 'Clinical Encounter Detail')
@section('subheader', 'Full municipal consultation view with linked field intake, treatment notes, follow-up state, and escalation readiness.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('phn.encounters.pdf', $clinicalEncounter) }}" class="inline-flex items-center rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
            Download PDF
        </a>
        <a href="{{ route('phn.encounters.edit', $clinicalEncounter) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Edit Encounter
        </a>
        <a href="{{ route('phn.residents.show', $clinicalEncounter->resident) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Resident Profile
        </a>
        <a href="{{ route('phn.encounters.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Log
        </a>
    </div>
@endsection

@section('content')
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
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Encounter Summary</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">PHN Reviewer</dt><dd class="text-slate-900">{{ $clinicalEncounter->attendedBy?->name ?? 'Unknown PHN' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Encountered At</dt><dd class="text-slate-900">{{ $clinicalEncounter->encountered_at?->format('F j, Y h:i A') }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Clinical Status</dt><dd class="text-slate-900">{{ $clinicalEncounter->clinical_status_label }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Disposition</dt><dd class="text-slate-900">{{ $clinicalEncounter->disposition ?: 'N/A' }}</dd></div>
                    </dl>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Resident Context</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">Resident Code</dt><dd class="text-slate-900">{{ $clinicalEncounter->resident?->official_resident_code ?? 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Household</dt><dd class="text-slate-900">{{ $clinicalEncounter->household?->full_identifier ?? 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Head of Household</dt><dd class="text-slate-900">{{ $clinicalEncounter->resident?->household?->headResident?->formal_name ?? 'No head assigned' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Latest OPT+</dt><dd class="text-slate-900">{{ $clinicalEncounter->resident?->latestOptMeasurement?->measurement_date?->format('F j, Y') ?? 'No measurement yet' }}</dd></div>
                    </dl>
                </div>
                <div class="md:col-span-2">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Consultation Notes</h3>
                    <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">{{ $clinicalEncounter->consultation_notes ?: 'No consultation note captured.' }}</div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Working Impression</h3>
                    <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">{{ $clinicalEncounter->working_impression ?: 'No assessment recorded.' }}</div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Action Taken</h3>
                    <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">{{ $clinicalEncounter->action_taken ?: 'No action record yet.' }}</div>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            @if($clinicalEncounter->triageRecord)
                <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-slate-900">Linked BHW Triage</h3>
                    </div>
                    <div class="space-y-4 p-6 text-sm">
                        <div>
                            <p class="font-medium text-slate-500">Recorded By</p>
                            <p class="mt-1 text-slate-900">{{ $clinicalEncounter->triageRecord?->recordedBy?->name ?? 'Unknown BHW' }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-slate-500">Measured At</p>
                            <p class="mt-1 text-slate-900">{{ $clinicalEncounter->triageRecord?->measured_at?->format('F j, Y h:i A') ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-slate-500">Triage Notes</p>
                            <p class="mt-1 text-slate-900">{{ $clinicalEncounter->triageRecord?->triage_notes ?: 'No BHW note captured.' }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Treatment and Orders</h3>
                </div>
                <div class="space-y-4 p-6 text-sm">
                    <div><p class="font-medium text-slate-500">Medicines Administered</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->medicines_administered ?: 'No medicine recorded.' }}</p></div>
                    <div><p class="font-medium text-slate-500">Lifestyle Advice</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->lifestyle_advice ?: 'No lifestyle advice recorded.' }}</p></div>
                    <div><p class="font-medium text-slate-500">Referral Notes</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->referral_notes ?: 'No referral note recorded.' }}</p></div>
                    <div><p class="font-medium text-slate-500">Return Instructions</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->return_instructions ?: 'No return instruction recorded.' }}</p></div>
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Follow-Up and Escalation</h3>
                </div>
                <div class="space-y-4 p-6 text-sm">
                    <div><p class="font-medium text-slate-500">Follow-Up Status</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->follow_up_status_label }}</p></div>
                    <div><p class="font-medium text-slate-500">Follow-Up Date</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->follow_up_date?->format('F j, Y') ?? 'No follow-up date set' }}</p></div>
                    <div><p class="font-medium text-slate-500">Follow-Up Notes</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->follow_up_notes ?: 'No follow-up note recorded.' }}</p></div>
                    <div><p class="font-medium text-slate-500">Escalated to MHO</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->is_escalated_to_mho ? 'Yes' : 'No' }}</p></div>
                    <div><p class="font-medium text-slate-500">Escalation Notes</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->escalation_notes ?: 'No escalation note recorded.' }}</p></div>
                </div>
            </div>

            @if($clinicalEncounter->mhoReview)
                <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-slate-900">Municipal Review Outcome</h3>
                    </div>
                    <div class="space-y-4 p-6 text-sm">
                        <div><p class="font-medium text-slate-500">Reviewed By</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->mhoReview?->reviewedBy?->name ?? 'Unknown MHO' }}</p></div>
                        <div><p class="font-medium text-slate-500">Reviewed At</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->mhoReview?->reviewed_at?->format('F j, Y h:i A') ?? 'N/A' }}</p></div>
                        <div><p class="font-medium text-slate-500">Final Disposition</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->mhoReview?->final_disposition ?: 'No disposition recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Final Assessment</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->mhoReview?->final_assessment ?: 'No municipal assessment recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Diagnostic Override</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->mhoReview?->diagnostic_override ?: 'No override recorded.' }}</p></div>
                        <div><p class="font-medium text-slate-500">Prescription Notes</p><p class="mt-1 text-slate-900">{{ $clinicalEncounter->mhoReview?->prescription_notes ?: 'No prescription note recorded.' }}</p></div>
                    </div>
                </div>
            @endif

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Recent Encounter History</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($recentResidentEncounters as $encounter)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $encounter->encountered_at?->format('F j, Y h:i A') }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $encounter->attendedBy?->name ?? 'Unknown PHN' }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $encounter->working_impression ?: ($encounter->consultation_notes ?: 'No note recorded.') }}</p>
                            @if($encounter->mhoReview)
                                <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">
                                    MHO {{ $encounter->mhoReview?->reviewedBy?->name ?? 'Unknown' }}
                                    · {{ $encounter->mhoReview?->final_disposition ?: 'Reviewed' }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">
                            No earlier PHN encounter history exists for this resident yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
