@extends('layouts.portal')

@section('title', 'BNS Maternal Tracking - HealthLink')
@section('header', 'Maternal Tracking')
@section('subheader', 'Current pregnant and lactating profiles, maternal history entries, and the mother-linked infant feeding follow-up workflow.')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Pregnant</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($pregnantCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Lactating</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($lactatingCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
            <p class="text-sm text-slate-500">Current Scope</p>
            <p class="mt-2 text-sm leading-6 text-slate-700">
                BNS can toggle current maternal status on verified female residents, while history tables preserve prenatal checks, status changes, and delivery or postpartum updates for later review.
            </p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-lg font-semibold text-slate-900">Start or Update Tracking</h3>
                <p class="mt-1 text-sm text-slate-500">Pick a verified female resident to open her maternal profile and logging workspace.</p>
            </div>
            <div class="p-6">
                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <p class="font-semibold">Please review the maternal tracking details.</p>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('bns.maternal.profile.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Female Resident</label>
                        <select name="resident_id" id="resident_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">Select a resident</option>
                            @foreach($femaleResidents as $femaleResident)
                                <option value="{{ $femaleResident->id }}" @selected((string) old('resident_id') === (string) $femaleResident->id)>
                                    {{ $femaleResident->formal_name }} · {{ $femaleResident->household?->purok?->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input type="checkbox" name="is_currently_pregnant" value="1" @checked(old('is_currently_pregnant')) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                            <span class="text-sm text-slate-700">Currently Pregnant</span>
                        </label>
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input type="checkbox" name="is_currently_lactating" value="1" @checked(old('is_currently_lactating')) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                            <span class="text-sm text-slate-700">Currently Lactating</span>
                        </label>
                    </div>
                    <div>
                        <label for="expected_delivery_date" class="block text-sm font-medium text-slate-700">Expected Delivery Date</label>
                        <input type="date" name="expected_delivery_date" id="expected_delivery_date" value="{{ old('expected_delivery_date') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                    <div>
                        <label for="current_risk_notes" class="block text-sm font-medium text-slate-700">Current Risk Notes</label>
                        <textarea name="current_risk_notes" id="current_risk_notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('current_risk_notes') }}</textarea>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Save and Open Profile
                    </button>
                </form>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-lg font-semibold text-slate-900">Tracked Profiles</h3>
                <p class="mt-1 text-sm text-slate-500">Open a resident profile to log prenatal checks, breastfeeding follow-up, and status changes.</p>
            </div>
            <div class="p-6">
                <form method="GET" action="{{ route('bns.maternal.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Resident name or code" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                    <div>
                        <label for="current_status" class="block text-sm font-medium text-slate-700">Current Status</label>
                        <select name="current_status" id="current_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All tracked residents</option>
                            <option value="pregnant" @selected(request('current_status') === 'pregnant')>Pregnant</option>
                            <option value="lactating" @selected(request('current_status') === 'lactating')>Lactating</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                            Apply Filters
                        </button>
                        <a href="{{ route('bns.maternal.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($profiles as $profile)
                    <div class="flex items-center justify-between gap-4 px-6 py-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $profile->resident?->formal_name ?? 'Unknown resident' }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $profile->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $profile->status_summary }}</p>
                        </div>
                        <a href="{{ route('bns.maternal.show', $profile->resident_id) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open</a>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        No maternal profiles match the current filters yet.
                    </div>
                @endforelse
            </div>
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $profiles->links() }}
            </div>
        </section>
    </div>

    <div class="mt-6 rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h3 class="text-lg font-semibold text-slate-900">Recent Maternal History Entries</h3>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse($recentHistories as $history)
                <div class="px-6 py-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $history->resident?->formal_name ?? 'Unknown resident' }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $history->event_type_label }}
                        · {{ $history->event_date?->format('M d, Y') }}
                        · {{ $history->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                    </p>
                    @if($history->notes)
                        <p class="mt-2 text-sm text-slate-600">{{ $history->notes }}</p>
                    @endif
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    No maternal history entries have been logged yet.
                </div>
            @endforelse
        </div>
    </div>
@endsection
