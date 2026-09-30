@extends('layouts.portal')
@section('title', 'Add OPT+ Cycle - HealthLink')
@section('header', 'Add New OPT+ Cycle')
@section('subheader', 'Choose the year and round to prepare the child list.')
@section('content')
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="mb-5 text-sm text-slate-600">Children currently registered in your barangay who are under 5 on the cycle date will be included.</p>
        @include('bns.opt-cycles.errors')
        <form method="POST" action="{{ route('bns.opt-cycles.store') }}" class="space-y-5" x-data="{ year: {{ (int) old('year', now()->year) }}, round: @js(old('round', now()->month >= 7 ? 'july' : 'january')), months: @js($roundMonths), reference: @js(old('reference_date', now()->month >= 7 ? now()->startOfYear()->addMonths(6)->toDateString() : now()->startOfYear()->toDateString())), resetReference() { this.reference = this.year + '-' + String(this.months[this.round]).padStart(2, '0') + '-01' } }" x-init="$watch('year', () => resetReference()); $watch('round', () => resetReference())">
            @csrf
            <label class="block text-sm font-medium">Year<input name="year" type="number" min="1900" max="2100" x-model="year" required class="mt-1 block w-full rounded-xl border-slate-300"></label>
            <label class="block text-sm font-medium">Round<select name="round" x-model="round" required class="mt-1 block w-full rounded-xl border-slate-300">@foreach($rounds as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label class="block text-sm font-medium">Cycle Date<input name="reference_date" type="date" x-model="reference" required max="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-xl border-slate-300"></label>
            <p class="text-xs text-slate-500">Use a date in the selected January or July round. The year and date cannot be changed after creating the cycle.</p>
            <div class="flex gap-4"><button class="rounded-xl bg-tubigon px-5 py-2 text-white">Create Cycle</button><a href="{{ route('bns.opt-cycles.index') }}" class="py-2 text-slate-600">Cancel</a></div>
        </form>
    </div>
@endsection
