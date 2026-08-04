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
            @include('households.partials.profile-details', ['household' => $household])

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
