@extends('layouts.portal')

@section('title', 'Field Draft Details - HealthLink')
@section('header', 'Field Draft Details')
@section('subheader', $householdDraft->target_household_id ? 'Review new residents before adding them to an existing verified household.' : 'Review the field submission before it becomes part of the verified civil registry.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        @if($householdDraft->draft_status === \App\Models\HouseholdDraft::STATUS_PENDING)
            <x-record-action :href="route('secretary.drafts.edit', $householdDraft)" variant="edit">
                Review & Approve
            </x-record-action>
        @endif
        <x-record-action :href="route('secretary.drafts.index')" variant="back">
            Back to Queue
        </x-record-action>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Package Overview</h3>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Reference Code</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->draft_reference_code }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Status</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->draft_status_label }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Draft Purok</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->purok?->display_name ?? 'No draft purok selected' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">{{ $householdDraft->target_household_id ? 'Existing Verified Household' : 'Proposed Household No.' }}</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->target_household_id ? '#'.$householdDraft->targetHousehold?->household_no : ($householdDraft->proposed_household_no ?: 'To be assigned at verification') }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Submitted By</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->submittedBy?->name ?? 'Unknown user' }}</p>
                </div>
                @if(!$householdDraft->target_household_id)
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Household Address</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->household_address }}</p>
                </div>
                @endif
                <div>
                    <p class="text-sm font-medium text-slate-500">Water Source</p>
                    <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->drinking_water_source ?: 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Sanitary Toilet</p>
                    <p class="mt-1 text-sm text-slate-900">
                        @if(is_null($householdDraft->has_sanitary_toilet))
                            N/A
                        @else
                            {{ $householdDraft->has_sanitary_toilet ? 'Yes' : 'No' }}
                        @endif
                        @if($householdDraft->sanitary_toilet_type)
                            · {{ $householdDraft->sanitary_toilet_type }}
                        @endif
                    </p>
                </div>
                @if($householdDraft->verification_notes)
                    <div class="md:col-span-2">
                        <p class="text-sm font-medium text-slate-500">Review Notes</p>
                        <p class="mt-1 text-sm text-slate-900">{{ $householdDraft->verification_notes }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Residents in Package</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($householdDraft->residentDrafts->count()) }}</p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Submitted</p>
                <p class="mt-3 text-sm font-semibold text-slate-900">{{ $householdDraft->created_at->format('F d, Y h:i A') }}</p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Verified Household</p>
                <p class="mt-3 text-sm font-semibold text-slate-900">
                    {{ $householdDraft->approvedHousehold?->household_no ? '#'.$householdDraft->approvedHousehold->household_no : ($householdDraft->targetHousehold?->household_no ? '#'.$householdDraft->targetHousehold->household_no : 'Not yet created') }}
                </p>
            </div>
        </section>
    </div>

    <section class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Resident Drafts</h3>
        </div>
        <div class="divide-y divide-slate-200">
            @foreach($householdDraft->residentDrafts as $residentDraft)
                <div class="px-6 py-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $residentDraft->formal_name }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $residentDraft->sex }} · {{ $residentDraft->birth_date?->format('M d, Y') }} · {{ $residentDraft->civil_status }}
                            </p>
                            <p class="mt-2 text-sm text-slate-600">{{ $residentDraft->relationship_to_head }}</p>
                            @if($residentDraft->draft_notes)
                                <p class="mt-2 text-sm text-slate-600">{{ $residentDraft->draft_notes }}</p>
                            @endif
                        </div>
                        <div class="text-sm text-slate-500">
                            {{ $residentDraft->approved_resident_id ? 'Approved to verified resident' : match ($householdDraft->draft_status) {
                                \App\Models\HouseholdDraft::STATUS_REJECTED => 'Rejected',
                                \App\Models\HouseholdDraft::STATUS_APPROVED => 'Approved',
                                default => 'Still pending review',
                            } }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
