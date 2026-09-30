@extends('layouts.portal')
@section('title', 'OPT+ Cycle History - HealthLink')
@section('header', 'OPT+ Cycle History')
@section('subheader', 'Open a cycle to record children\'s measurements.')
@section('actions')
    <a href="{{ route('bns.opt-cycles.create') }}" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-semibold text-white">Add New OPT+ Cycle</a>
@endsection
@section('content')
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <label class="text-sm text-slate-700">Year<input name="year" type="number" value="{{ request('year') }}" class="mt-1 block rounded-xl border-slate-300" placeholder="All years"></label>
            <label class="text-sm text-slate-700">Status<select name="status" class="mt-1 block rounded-xl border-slate-300"><option value="">All statuses</option><option value="in_progress" @selected(request('status') === 'in_progress')>In Progress</option><option value="completed" @selected(request('status') === 'completed')>Completed</option></select></label>
            <button class="rounded-xl bg-tubigon px-4 py-2 text-white">Filter</button>
            <a href="{{ route('bns.opt-cycles.index') }}" class="text-sm text-slate-600">Reset</a>
        </form>
        <x-export-dropdown route="bns.opt-cycles.export-history" dataset="Internal OPT+ Cycle History" />
    </div>
    <div class="grid gap-5 lg:grid-cols-2">
        @forelse($cycles as $cycle)
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-3"><h2 class="text-xl font-semibold text-slate-900">{{ $cycle->title }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ $cycle->status === 'completed' ? 'Completed' : 'In Progress' }}</span></div>
                <p class="mt-2 text-sm text-slate-500">Cycle date: {{ $cycle->reference_date->format('F j, Y') }}</p>
                <dl class="my-5 grid grid-cols-3 gap-3">
                    <div><dt class="text-sm text-slate-500">Eligible Children</dt><dd class="text-2xl font-semibold">{{ $cycle->entries_count }}</dd></div>
                    <div><dt class="text-sm text-slate-500">Measured</dt><dd class="text-2xl font-semibold text-emerald-700">{{ $cycle->measured_count }}</dd></div>
                    <div><dt class="text-sm text-slate-500">Unmeasured</dt><dd class="text-2xl font-semibold text-amber-700">{{ $cycle->unmeasured_count }}</dd></div>
                </dl>
                <progress max="100" value="{{ $cycle->coverage }}" class="h-2 w-full accent-emerald-600" aria-label="Measurement coverage"></progress>
                <div class="mt-3 flex justify-between"><span class="text-sm text-slate-600">{{ $cycle->coverage }}% measured</span><a href="{{ route('bns.opt-cycles.show', $cycle) }}" class="font-semibold text-tubigon">Open Cycle</a></div>
            </article>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-8 text-slate-500">No cycles found. Add a cycle to prepare the child list.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $cycles->links() }}</div>
    <p class="mt-6 text-sm text-slate-500"><a class="underline" href="{{ route('bns.opt-measurements.index') }}">View older measurement records</a></p>
@endsection
