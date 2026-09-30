@extends('layouts.portal')
@section('title', 'Edit Historical Information - '.$entry->child_name)
@section('header', 'Edit Historical Information')
@section('subheader', $entry->child_name.' - '.$cycle->title)
@section('content')
    @include('bns.opt-cycles.errors')
    <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6">
        @if($cycle->status === 'completed')
            <p class="text-sm text-slate-600">Reopen this cycle before changing its historical information.</p>
        @else
            <p class="mb-6 text-sm text-slate-600">Only correct information recorded incorrectly for this cycle. This does not change the child's current caregiver or other cycles.</p>
            <form method="POST" action="{{ route('bns.opt-cycles.historical-information.update', [$cycle, $entry]) }}" class="space-y-5">
                @csrf @method('PUT')
                <div><label for="caregiver-name" class="block text-sm font-semibold">Mother / Caregiver for This Cycle</label>
                    <x-opt-caregiver-field :options="$caregivers->map(fn ($r) => ['value' => $r->id, 'label' => $r->full_name])->all()" :name="$entry->caregiver_name" :resident-id="$entry->caregiver_resident_key" /></div>
                <label class="block text-sm font-semibold">IP Membership
                    <select name="ip_membership" class="mt-2 block w-full rounded-xl border-slate-300">
                        @foreach(['unknown' => 'Unknown', 'yes' => 'Yes', 'no' => 'No'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('ip_membership', $entry->ip_membership === null ? 'unknown' : ($entry->ip_membership ? 'yes' : 'no')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold">Reason for Correction<textarea name="correction_reason" required maxlength="1500" rows="3" class="mt-2 block w-full rounded-xl border-slate-300">{{ old('correction_reason') }}</textarea></label>
                <button class="rounded-xl bg-tubigon px-5 py-3 text-white">Save Historical Correction</button>
            </form>
        @endif
        <a href="{{ route('bns.opt-cycles.entry', [$cycle, $entry]) }}" class="mt-5 inline-block text-sm text-slate-600">Back to Measurement</a>
    </section>
@endsection
