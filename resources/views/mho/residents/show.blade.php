@extends('layouts.portal')

@section('title', 'MHO Resident Profile - HealthLink')
@section('header', 'Resident Profile')
@section('subheader', 'Municipal read-only resident profile with clinical timeline, household risk context, and nutrition-linked alerts.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        @if($latestEncounter && $latestEncounter->is_escalated_to_mho)
            <a href="{{ route('mho.escalations.show', $latestEncounter) }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                Latest Escalated Case
            </a>
        @endif
        <a href="{{ route('mho.residents.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
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
                        <div><dt class="font-medium text-slate-500">Birth Place</dt><dd class="text-slate-900">{{ $resident->birth_place ?: 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Civil Status</dt><dd class="text-slate-900">{{ $resident->civil_status ?: 'N/A' }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Citizenship</dt><dd class="text-slate-900">{{ $resident->citizenship ?: 'N/A' }}</dd></div>
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
                        <p class="text-sm text-slate-500">Clinical Encounters</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($resident->clinicalEncounters->count()) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Triage Records</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($resident->triageRecords->count()) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Latest Status</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $latestEncounter?->clinical_status_label ?? 'No encounter yet' }}</p>
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
                    <h3 class="text-lg font-semibold text-slate-900">Household Risk Context</h3>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2 text-sm">
                    <div><p class="font-medium text-slate-500">Drinking Water</p><p class="mt-1 text-slate-900">{{ $resident->household?->drinking_water_source ?: 'N/A' }}</p></div>
                    <div><p class="font-medium text-slate-500">Sanitary Toilet</p><p class="mt-1 text-slate-900">{{ $resident->household?->has_sanitary_toilet ? ($resident->household?->sanitary_toilet_type ?: 'Yes') : 'No' }}</p></div>
                    <div><p class="font-medium text-slate-500">Garbage Disposal</p><p class="mt-1 text-slate-900">{{ $resident->household?->garbage_disposal_method_label ?? 'N/A' }}</p></div>
                    <div><p class="font-medium text-slate-500">Backyard Garden</p><p class="mt-1 text-slate-900">{{ $resident->household?->has_backyard_garden ? 'Yes' : 'No' }}</p></div>
                    <div><p class="font-medium text-slate-500">Housing Materials</p><p class="mt-1 text-slate-900">{{ $resident->household?->housing_material_type_label ?? 'N/A' }}</p></div>
                    <div><p class="font-medium text-slate-500">Social Aid Beneficiary</p><p class="mt-1 text-slate-900">{{ $resident->household?->is_social_aid_beneficiary ? 'Yes' : 'No' }}</p></div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Clinical Timeline</h3>
                    <p class="text-sm text-slate-500">PHN encounters and municipal review outcomes attached to this resident.</p>
                </div>
                @if($latestEncounter && $latestEncounter->is_escalated_to_mho)
                    <a href="{{ route('mho.escalations.show', $latestEncounter) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Latest Case</a>
                @endif
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($resident->clinicalEncounters as $encounter)
                    <div class="px-6 py-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $encounter->encountered_at?->format('F j, Y h:i A') }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $encounter->encounter_source_label }} · {{ $encounter->clinical_status_label }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $encounter->working_impression ?: ($encounter->consultation_notes ?: 'No clinical note recorded.') }}</p>
                                @if($encounter->mhoReview)
                                    <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">
                                        MHO {{ $encounter->mhoReview?->reviewedBy?->name ?? 'Unknown' }}
                                        · {{ $encounter->mhoReview?->final_disposition ?: 'Reviewed' }}
                                    </p>
                                @endif
                            </div>
                            @if($encounter->is_escalated_to_mho)
                                <a href="{{ route('mho.escalations.show', $encounter) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No clinical encounter history exists for this resident yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="grid divide-y divide-slate-200 md:grid-cols-2 md:divide-x md:divide-y-0">
                <div class="p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Nutrition Flags</h4>
                    <div class="mt-4 space-y-3">
                        @forelse($resident->nutritionFlags as $flag)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ ucfirst($flag->flag_status) }} flag</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $flag->flagged_at?->format('M d, Y h:i A') ?? 'No timestamp' }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $flag->flag_reason ?: 'No reason recorded.' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No nutrition flags have been raised for this resident.</p>
                        @endforelse
                    </div>
                </div>
                <div class="p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Triage History</h4>
                    <div class="mt-4 space-y-3">
                        @forelse($resident->triageRecords as $triageRecord)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $triageRecord->measured_at?->format('M d, Y h:i A') }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $triageRecord->recordedBy?->name ?? 'Unknown BHW' }} · {{ $triageRecord->triage_status_label }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $triageRecord->triage_notes ?: 'No BHW note recorded.' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No triage history recorded yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
