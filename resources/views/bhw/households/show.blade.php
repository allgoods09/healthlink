@extends('layouts.portal')

@section('title', 'Household Detail - HealthLink')
@section('header', 'Household Detail')
@section('subheader', 'Read-only household profile for validation, triage lookup, and correction request submission.')

@section('actions')
    <a href="{{ route('bhw.update-requests.create-household', ['household_id' => $household->id]) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        Request Household Update
    </a>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $household->full_identifier }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $household->official_household_code ?? 'No household code yet' }}</p>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Purok</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $household->purok?->display_name ?? 'Unknown purok' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Head of Household</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $household->headResident?->formal_name ?? 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="grid gap-4 border-t border-slate-200 px-6 py-5 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Address</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $household->household_address }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Water / Toilet</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $household->drinking_water_source ?: 'Not set' }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            @if(is_null($household->has_sanitary_toilet))
                                Toilet access not set
                            @else
                                {{ $household->has_sanitary_toilet ? 'Has sanitary toilet' : 'No sanitary toilet' }}
                            @endif
                            @if($household->sanitary_toilet_type)
                                · {{ $household->sanitary_toilet_type }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Household Members</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($household->residents as $resident)
                        <div class="flex items-center justify-between gap-4 px-6 py-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $resident->formal_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $resident->relationship_to_head }} · {{ ucfirst($resident->resident_status) }}</p>
                            </div>
                            <a href="{{ route('bhw.residents.show', $resident) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open</a>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">This household has no linked residents yet.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Environmental Risk Snapshot</h3>
                </div>
                <div class="space-y-3 p-6 text-sm text-slate-700">
                    <p>Garbage Disposal: {{ $household->garbage_disposal_method_label }}</p>
                    <p>Backyard Garden: {{ is_null($household->has_backyard_garden) ? 'Not set' : ($household->has_backyard_garden ? 'Yes' : 'No') }}</p>
                    <p>Housing Materials: {{ $household->housing_material_type_label }}</p>
                    <p>Social Aid Beneficiary: {{ $household->is_social_aid_beneficiary ? 'Yes' : 'No' }}</p>
                </div>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Recent Triage Entries You Logged</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($recentTriage as $triage)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $triage->resident?->formal_name ?? 'Unknown resident' }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $triage->measured_at?->format('M d, Y h:i A') }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">No triage entries recorded for this household yet.</div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection
