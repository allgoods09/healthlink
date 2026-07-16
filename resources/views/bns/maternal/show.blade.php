@extends('layouts.portal')

@section('title', 'Maternal Profile Detail - HealthLink')
@section('header', 'Maternal Profile Detail')
@section('subheader', 'Manage current maternal status, log prenatal or postpartum history, and record infant feeding follow-up tied to this mother.')

@section('actions')
    <a href="{{ route('bns.maternal.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        Back to Maternal Tracking
    </a>
@endsection

@section('content')
    @php
        $infantResidentSearchOptions = $infantResidents->map(fn ($infantResident) => [
            'value' => $infantResident->id,
            'label' => $infantResident->formal_name,
            'description' => $infantResident->household?->purok?->display_name ?? 'Unknown purok',
            'search' => collect([
                $infantResident->formal_name,
                $infantResident->official_resident_code,
                $infantResident->household?->purok?->display_name,
            ])->filter()->implode(' '),
        ])->values()->all();
    @endphp

    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $resident->formal_name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $resident->household?->purok?->display_name ?? 'Unknown purok' }} · {{ $resident->official_resident_code }}</p>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Current Status</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $profile->status_summary }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Expected Delivery</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $profile->expected_delivery_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                </div>
                @if($profile->current_risk_notes)
                    <div class="border-t border-slate-200 px-6 py-5 text-sm leading-6 text-slate-700">
                        {{ $profile->current_risk_notes }}
                    </div>
                @endif
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Maternal History</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($histories as $history)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $history->event_type_label }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $history->event_date?->format('M d, Y') }}
                                @if($history->gestational_age_weeks)
                                    · {{ $history->gestational_age_weeks }} week(s)
                                @endif
                                @if($history->weight_kg)
                                    · {{ $history->weight_kg }} kg
                                @endif
                            </p>
                            @if($history->notes)
                                <p class="mt-2 text-sm text-slate-600">{{ $history->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">
                            No maternal history entries have been recorded yet.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Infant Feeding Follow-up</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($infantFeedingLogs as $feedingLog)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $feedingLog->resident?->formal_name ?? 'Unknown child' }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $feedingLog->feeding_method_label }}
                                · {{ $feedingLog->observed_on?->format('M d, Y') }}
                                · {{ $feedingLog->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                            </p>
                            @if($feedingLog->notes)
                                <p class="mt-2 text-sm text-slate-600">{{ $feedingLog->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">
                            No infant feeding entries are linked to this maternal profile yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Update Current Status</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('bns.maternal.profile.update', $resident) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input type="checkbox" name="is_currently_pregnant" value="1" @checked(old('is_currently_pregnant', $profile->is_currently_pregnant)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                <span class="text-sm text-slate-700">Currently Pregnant</span>
                            </label>
                            <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input type="checkbox" name="is_currently_lactating" value="1" @checked(old('is_currently_lactating', $profile->is_currently_lactating)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                <span class="text-sm text-slate-700">Currently Lactating</span>
                            </label>
                        </div>
                        <div>
                            <label for="expected_delivery_date" class="block text-sm font-medium text-slate-700">Expected Delivery Date</label>
                            <input type="date" name="expected_delivery_date" id="expected_delivery_date" value="{{ old('expected_delivery_date', optional($profile->expected_delivery_date)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label for="current_risk_notes" class="block text-sm font-medium text-slate-700">Current Risk Notes</label>
                            <textarea name="current_risk_notes" id="current_risk_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('current_risk_notes', $profile->current_risk_notes) }}</textarea>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                            Save Current Status
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Add Maternal History</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('bns.maternal.histories.store', $resident) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="event_type" class="block text-sm font-medium text-slate-700">Event Type</label>
                            <select name="event_type" id="event_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                @foreach($historyEventTypes as $eventType => $label)
                                    <option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="event_date" class="block text-sm font-medium text-slate-700">Event Date</label>
                                <input type="date" name="event_date" id="event_date" value="{{ old('event_date', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label for="gestational_age_weeks" class="block text-sm font-medium text-slate-700">Gestational Age</label>
                                <input type="number" name="gestational_age_weeks" id="gestational_age_weeks" min="1" max="45" value="{{ old('gestational_age_weeks') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                        </div>
                        <div>
                            <label for="weight_kg" class="block text-sm font-medium text-slate-700">Weight (kg)</label>
                            <input type="number" step="0.01" min="0.5" max="250" name="weight_kg" id="weight_kg" value="{{ old('weight_kg') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div>
                            <label for="history_notes" class="block text-sm font-medium text-slate-700">Notes</label>
                            <textarea name="notes" id="history_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                            Add History Entry
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Add Infant Feeding Log</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('bns.maternal.infant-feeding.store', $resident) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Child</label>
                            <x-searchable-record-select
                                name="resident_id"
                                id="resident_id"
                                :options="$infantResidentSearchOptions"
                                :selected="old('resident_id')"
                                placeholder="Search child name"
                                empty-message="No eligible child matches your search."
                                required
                            />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="observed_on" class="block text-sm font-medium text-slate-700">Observation Date</label>
                                <input type="date" name="observed_on" id="observed_on" value="{{ old('observed_on', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label for="feeding_method" class="block text-sm font-medium text-slate-700">Feeding Method</label>
                                <select name="feeding_method" id="feeding_method" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    @foreach($feedingMethods as $feedingMethod => $label)
                                        <option value="{{ $feedingMethod }}" @selected(old('feeding_method') === $feedingMethod)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="feeding_notes" class="block text-sm font-medium text-slate-700">Notes</label>
                            <textarea name="notes" id="feeding_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                            Add Infant Feeding Log
                        </button>
                    </form>
                </div>
            </section>
        </aside>
    </div>
@endsection
