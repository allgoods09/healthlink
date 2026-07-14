@extends('layouts.portal')

@section('title', 'PHN Resident Profile - HealthLink')
@section('header', 'Resident Profile')
@section('subheader', 'Municipal read-only resident view with field history, nutrition context, and one-click correction routing to the Secretary queue.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('phn.encounters.create', ['resident_id' => $resident->id]) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            New Walk-In Encounter
        </a>
        <a href="{{ route('phn.update-requests.create-resident', ['resident_id' => $resident->id]) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Resident Correction
        </a>
        <a href="{{ route('phn.update-requests.create-household', ['household_id' => $resident->household_id]) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Household Correction
        </a>
        <a href="{{ route('phn.residents.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Directory
        </a>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $resident->formal_name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $resident->sex }} · Age {{ $resident->age }} · {{ $resident->resident_status_label }}</p>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Identity</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">Resident Code</dt><dd class="text-slate-900">{{ $resident->official_resident_code ?: 'No resident code' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">PhilSys Card</dt><dd class="text-slate-900">{{ $resident->philsys_card_no ?: 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Birth Date</dt><dd class="text-slate-900">{{ $resident->birth_date?->format('F j, Y') }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Birth Place</dt><dd class="text-slate-900">{{ $resident->birth_place }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Civil Status</dt><dd class="text-slate-900">{{ $resident->civil_status }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Citizenship</dt><dd class="text-slate-900">{{ $resident->citizenship }}</dd></div>
                    </dl>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Location and Contact</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-medium text-slate-500">Barangay</dt><dd class="text-slate-900">{{ $resident->household?->purok?->barangay?->name ?? 'Unknown barangay' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Purok</dt><dd class="text-slate-900">{{ $resident->household?->purok?->display_name ?? 'Unknown purok' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Household</dt><dd class="text-slate-900">{{ $resident->household?->full_identifier ?? 'No household linked' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Head of Household</dt><dd class="text-slate-900">{{ $resident->household?->headResident?->formal_name ?? 'No head assigned' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Contact Number</dt><dd class="text-slate-900">{{ $resident->contact_number ?: 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Email Address</dt><dd class="text-slate-900">{{ $resident->email_address ?: 'N/A' }}</dd></div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Clinical and Nutrition Snapshot</h3>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Open Nutrition Flags</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($openNutritionFlagCount) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">PHN Encounters</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($resident->clinicalEncounters->count()) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 sm:col-span-2">
                        <p class="text-sm text-slate-500">Latest OPT+</p>
                        <p class="mt-2 text-sm text-slate-900">
                            @if($resident->latestOptMeasurement)
                                {{ $resident->latestOptMeasurement->measurement_date?->format('F j, Y') }}
                                · WFA {{ $resident->latestOptMeasurement->weight_for_age_status }}
                                · HFA {{ $resident->latestOptMeasurement->height_for_age_status }}
                                · WFL/H {{ $resident->latestOptMeasurement->weight_for_length_height_status }}
                            @else
                                No OPT+ measurement recorded yet.
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Socio-Economic Context</h3>
                </div>
                <div class="space-y-4 p-6 text-sm">
                    <div><p class="font-medium text-slate-500">Occupation</p><p class="mt-1 text-slate-900">{{ $resident->socioEconomicProfile?->occupation ?: 'N/A' }}</p></div>
                    <div><p class="font-medium text-slate-500">Employment Status</p><p class="mt-1 text-slate-900">{{ $resident->socioEconomicProfile?->employment_status ?: 'N/A' }}</p></div>
                    <div><p class="font-medium text-slate-500">Highest Education</p><p class="mt-1 text-slate-900">{{ $resident->socioEconomicProfile?->highest_education_level ?: 'N/A' }}</p></div>
                    <div><p class="font-medium text-slate-500">Disability Type</p><p class="mt-1 text-slate-900">{{ $resident->socioEconomicProfile?->disability_type ?: 'N/A' }}</p></div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Recent PHN Encounters</h3>
                    <p class="text-sm text-slate-500">Clinical assessments and walk-in encounters attached to this resident.</p>
                </div>
                @if($latestEncounter)
                    <a href="{{ route('phn.encounters.show', $latestEncounter) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Latest</a>
                @endif
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($resident->clinicalEncounters as $encounter)
                    <div class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $encounter->encountered_at?->format('F j, Y h:i A') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $encounter->encounter_source_label }} · {{ $encounter->clinical_status_label }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $encounter->working_impression ?: ($encounter->consultation_notes ?: 'No clinical note recorded.') }}</p>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No PHN encounter history exists for this resident yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Field Intake and Correction Trail</h3>
                    <p class="text-sm text-slate-500">BHW clinic intake and Secretary-bound correction requests linked to this resident.</p>
                </div>
                @if($latestTriage)
                    <a href="{{ route('phn.triage.show', $latestTriage) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Latest Triage</a>
                @endif
            </div>
            <div class="grid divide-y divide-slate-200 md:grid-cols-2 md:divide-x md:divide-y-0">
                <div class="p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Triage History</h4>
                    <div class="mt-4 space-y-3">
                        @forelse($resident->triageRecords as $triageRecord)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $triageRecord->measured_at?->format('M d, Y h:i A') }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $triageRecord->recordedBy?->name ?? 'Unknown BHW' }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $triageRecord->triage_notes ?: 'No BHW note recorded.' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No triage history recorded yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Correction Requests</h4>
                    <div class="mt-4 space-y-3">
                        @forelse($resident->profileUpdateRequests as $updateRequest)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $updateRequest->request_status_label }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $updateRequest->submittedBy?->name ?? 'Unknown sender' }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $updateRequest->request_reason }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No correction requests have been filed for this resident yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
