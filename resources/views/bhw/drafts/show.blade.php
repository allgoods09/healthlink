@extends('layouts.portal')

@section('title', 'Field Draft Detail - HealthLink')
@section('header', 'Field Draft Detail')
@section('subheader', 'Review the submitted household package and its Secretary verification status.')

@section('actions')
    @if($draft->draft_status === \App\Models\HouseholdDraft::STATUS_PENDING)
        <a href="{{ route('bhw.drafts.edit', $draft) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Edit Draft
        </a>
    @endif
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $draft->draft_reference_code }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $draft->household_address }}</p>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Purok</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $draft->purok?->display_name ?? 'No purok selected' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Status</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $draft->draft_status_label }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Resident Drafts</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @foreach($draft->residentDrafts as $residentDraft)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $residentDraft->formal_name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $residentDraft->relationship_to_head }} · {{ optional($residentDraft->birth_date)->format('M d, Y') }}</p>
                            @if($residentDraft->draft_notes)
                                <p class="mt-2 text-sm text-slate-600">{{ $residentDraft->draft_notes }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Environmental Snapshot</h3>
                </div>
                <div class="space-y-3 p-6 text-sm text-slate-700">
                    <p>Water Source: {{ $draft->drinking_water_source ?: 'Not set' }}</p>
                    <p>
                        Toilet: 
                        @if(is_null($draft->has_sanitary_toilet))
                            Not set
                        @else
                            {{ $draft->has_sanitary_toilet ? 'Has sanitary toilet' : 'No sanitary toilet' }}
                        @endif
                        @if($draft->sanitary_toilet_type)
                            · {{ $draft->sanitary_toilet_type }}
                        @endif
                    </p>
                    <p>Garbage Disposal: {{ $draft->garbage_disposal_method_label }}</p>
                    <p>Backyard Garden: {{ is_null($draft->has_backyard_garden) ? 'Not set' : ($draft->has_backyard_garden ? 'Yes' : 'No') }}</p>
                    <p>Housing Materials: {{ $draft->housing_material_type_label }}</p>
                </div>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Secretary Review</h3>
                </div>
                <div class="space-y-3 p-6 text-sm text-slate-700">
                    <p>Reviewed By: {{ $draft->reviewedBy?->name ?? 'Not reviewed yet' }}</p>
                    <p>Approved Household: {{ $draft->approvedHousehold?->full_identifier ?? 'Not approved yet' }}</p>
                    <p>Notes: {{ $draft->verification_notes ?: 'No review notes yet.' }}</p>
                </div>
            </section>
        </aside>
    </div>
@endsection
