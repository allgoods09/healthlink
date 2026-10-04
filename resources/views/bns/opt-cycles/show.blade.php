@extends('layouts.portal')
@section('title', $cycle->title.' - HealthLink')
@section('header', $cycle->title)
@section('subheader', 'Find a child and record their measurements.')
@section('actions')
    <a href="{{ route('bns.opt-cycles.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Cycle History</a>
@endsection
@section('content')
    @include('bns.opt-cycles.errors')
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap justify-between gap-3 text-sm text-slate-600">
            <span>Cycle date: {{ $cycle->reference_date->format('F j, Y') }}</span>
            <span>{{ $cycle->status === 'completed' ? 'Completed' : 'In Progress' }}</span>
        </div>
        <div class="mt-4 flex flex-wrap gap-6 font-semibold">
            <span>Eligible Children: {{ $cycle->entries_count }}</span>
            <span class="text-emerald-700">Measured: {{ $cycle->measured_count }}</span>
            <span class="text-amber-700">Unmeasured: {{ $cycle->unmeasured_count }}</span>
            <span>{{ $cycle->coverage }}% measured</span>
        </div>
    </section>
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-5">
        <form method="GET" data-live-results-form="bns-opt-cycles-show">
            <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm font-medium">Find Child<input name="search" value="{{ request('search') }}" placeholder="Child's name" class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-3"></label>
                <label class="text-sm font-medium">Purok
                    <select name="purok_key" class="mt-2 block w-full rounded-xl border-slate-300 py-3">
                        <option value="">All puroks</option>
                        @foreach($puroks as $purok)
                            <option value="{{ $purok->purok_key }}" @selected((string) request('purok_key') === (string) $purok->purok_key)>{{ $purok->purok_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium">Status
                    <select name="measurement_status" class="mt-2 block w-full rounded-xl border-slate-300 py-3">
                        <option value="">All children</option>
                        <option value="measured" @selected(request('measurement_status') === 'measured')>Measured</option>
                        <option value="unmeasured" @selected(request('measurement_status') === 'unmeasured')>Unmeasured</option>
                    </select>
                </label>
                <div class="flex items-center gap-4">
                    <button class="rounded-xl bg-tubigon px-5 py-3 text-white">Find</button>
                    <a href="{{ route('bns.opt-cycles.show', $cycle) }}" class="text-sm text-slate-600">Clear</a>
                </div>
            </div>
            <details class="mt-4" @if(request('readiness') || request('sort') || request('direction')) open @endif>
                <summary class="cursor-pointer text-sm text-slate-500">More list options</summary>
                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                    <label class="text-sm">Sort by<select name="sort" class="mt-2 block w-full rounded-xl border-slate-300">
                        @foreach($sorts as $value => $label)<option value="{{ $value }}" @selected(request('sort', 'name') === $value)>{{ $label }}</option>@endforeach
                    </select></label>
                    <label class="text-sm">Order<select name="direction" class="mt-2 block w-full rounded-xl border-slate-300">
                        <option value="asc" @selected(request('direction', 'asc') === 'asc')>Ascending</option>
                        <option value="desc" @selected(request('direction') === 'desc')>Descending</option>
                    </select></label>
                    <label class="text-sm">Export information<select name="readiness" class="mt-2 block w-full rounded-xl border-slate-300">
                        <option value="">All children</option>
                        <option value="ready" @selected(request('readiness') === 'ready')>Complete information</option>
                        <option value="incomplete" @selected(request('readiness') === 'incomplete')>Incomplete information</option>
                    </select></label>
                </div>
            </details>
        </form>
    </section>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white" data-live-results="bns-opt-cycles-show">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>@foreach(['Child', 'Age', 'Purok', 'Mother/Caregiver', 'Weight', 'Height', 'Status', 'Action'] as $header)<th class="px-4 py-3">{{ $header }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($entries as $entry)
                    <tr>
                        <td class="px-4 py-4 font-semibold">{{ $entry->child_name }}</td>
                        <td class="px-4 py-4">{{ $entry->reference_age }} months</td>
                        <td class="px-4 py-4">{{ $entry->purok_name }}</td>
                        <td class="px-4 py-4">{{ $entry->caregiver_name ?: '-' }}</td>
                        <td class="px-4 py-4">{{ $entry->measurement ? $entry->measurement->weight_kg.' kg' : '-' }}</td>
                        <td class="px-4 py-4">{{ $entry->measurement ? $entry->measurement->height_cm.' cm' : '-' }}</td>
                        <td class="px-4 py-4 font-semibold {{ $entry->measurement ? 'text-emerald-700' : 'text-amber-700' }}">{{ $entry->measurement_status }}</td>
                        <td class="px-4 py-4"><a href="{{ route('bns.opt-cycles.entry', [$cycle, $entry]) }}" class="font-semibold text-tubigon">{{ $cycle->status === 'completed' ? 'View' : ($entry->measurement ? 'Edit' : 'Record') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-8 text-center text-slate-500">No children found. Try another name or clear the filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t p-4">{{ $entries->links() }}</div>
    </div>
    <details class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
        <summary class="cursor-pointer font-semibold text-slate-700">Export Records</summary>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">For checking records only. Official e-OPT Excel is not available yet.</p>
                @if($incompleteExportCount)
                    <p class="mt-2 text-sm text-amber-700">{{ $incompleteExportCount }} {{ $incompleteExportCount === 1 ? 'child has' : 'children have' }} incomplete export information. Measurements can still be recorded.</p>
                @endif
            </div>
            <x-export-dropdown route="bns.opt-cycles.export" :parameters="['optCycle' => $cycle->id]" :dataset="$cycle->title.' - Internal OPT+ Dataset'" />
        </div>
    </details>
    <details class="mt-4 rounded-2xl border border-slate-200 bg-white p-5" @if($errors->has('completion_note') || $errors->has('reopening_reason')) open @endif>
        <summary class="cursor-pointer font-semibold text-slate-700">{{ $cycle->status === 'completed' ? 'Reopen Cycle' : 'Finish Cycle' }}</summary>
        @if($cycle->status === 'in_progress')
            <p class="mt-3 text-sm text-slate-500">Finish when your work is done. Changes will need the cycle to be reopened.</p>
            <form method="POST" action="{{ route('bns.opt-cycles.complete', $cycle) }}" class="mt-4 space-y-4">
                @csrf
                <label class="block text-sm">Notes{{ $cycle->unmeasured_count ? ' (required)' : ' (optional)' }}<textarea name="completion_note" maxlength="1500" class="mt-2 block w-full rounded-xl border-slate-300">{{ old('completion_note') }}</textarea></label>
                @if($cycle->unmeasured_count)
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm_unmeasured" value="1" class="mt-1 rounded" @checked(old('confirm_unmeasured'))>Finish with {{ $cycle->unmeasured_count }} children still unmeasured. Explain why in the notes.</label>
                @endif
                <button class="rounded-xl bg-tubigon px-5 py-3 text-white">Complete Cycle</button>
            </form>
        @else
            <p class="mt-3 text-sm text-slate-600">Completed {{ $cycle->completed_at?->format('M j, Y') }}. {{ $cycle->completion_note }}</p>
            <form method="POST" action="{{ route('bns.opt-cycles.reopen', $cycle) }}" class="mt-4 space-y-4">
                @csrf
                <label class="block text-sm">Reason for Reopening<textarea name="reopening_reason" required maxlength="1500" class="mt-2 block w-full rounded-xl border-slate-300">{{ old('reopening_reason') }}</textarea></label>
                <button class="rounded-xl border border-slate-300 px-5 py-3">Reopen Cycle</button>
            </form>
        @endif
    </details>
@endsection
