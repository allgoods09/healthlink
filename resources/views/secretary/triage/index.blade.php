@extends('layouts.portal')

@section('title', 'Pending Triage Queue - HealthLink')
@section('header', 'Pending Triage Queue')
@section('subheader', 'Read-only secretary oversight for barangay triage records that are waiting for PHN or MHO clinical consumption.')

@section('actions')
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('secretary.triage.index') }}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                    <div>
                        <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Resident or note" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                    <div>
                        <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                        <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All puroks</option>
                            @foreach($puroks as $purok)
                                <option value="{{ $purok->id }}" {{ (string) request('purok_id') === (string) $purok->id ? 'selected' : '' }}>
                                    {{ $purok->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="recorded_by_user_id" class="block text-sm font-medium text-slate-700">Recorded By</label>
                        <select name="recorded_by_user_id" id="recorded_by_user_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All BHWs</option>
                            @foreach($frontlineUsers as $frontlineUser)
                                <option value="{{ $frontlineUser->id }}" {{ (string) request('recorded_by_user_id') === (string) $frontlineUser->id ? 'selected' : '' }}>
                                    {{ $frontlineUser->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All statuses</option>
                            <option value="{{ \App\Models\TriageRecord::STATUS_PENDING }}" {{ request('status') === \App\Models\TriageRecord::STATUS_PENDING ? 'selected' : '' }}>Pending</option>
                            <option value="{{ \App\Models\TriageRecord::STATUS_REVIEWED }}" {{ request('status') === \App\Models\TriageRecord::STATUS_REVIEWED ? 'selected' : '' }}>Reviewed</option>
                            <option value="{{ \App\Models\TriageRecord::STATUS_CLOSED }}" {{ request('status') === \App\Models\TriageRecord::STATUS_CLOSED ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-slate-700">Date From</label>
                        <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-slate-700">Date To</label>
                        <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('secretary.triage.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Resident</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Vitals Snapshot</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($triageRecords as $triageRecord)
                        <tr class="{{ $triageRecord->triage_status === \App\Models\TriageRecord::STATUS_PENDING ? 'bg-amber-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $triageRecord->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $triageRecord->measured_at?->format('M d, Y h:i A') }}</p>
                                <p class="mt-1 text-xs text-slate-400">Recorded by {{ $triageRecord->recordedBy?->name ?? 'Unknown BHW' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $triageRecord->purok?->display_name ?? 'Unknown purok' }} · Household #{{ $triageRecord->household?->household_no ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                BP: {{ $triageRecord->bp_systolic && $triageRecord->bp_diastolic ? $triageRecord->bp_systolic.'/'.$triageRecord->bp_diastolic : 'N/A' }}<br>
                                Temp: {{ $triageRecord->temperature_celsius ? $triageRecord->temperature_celsius.' C' : 'N/A' }}<br>
                                Glucose: {{ $triageRecord->blood_glucose_mg_dl ? $triageRecord->blood_glucose_mg_dl.' mg/dL' : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $triageRecord->triage_status === \App\Models\TriageRecord::STATUS_CLOSED ? 'bg-slate-200 text-slate-700' : '' }}
                                    {{ $triageRecord->triage_status === \App\Models\TriageRecord::STATUS_REVIEWED ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $triageRecord->triage_status === \App\Models\TriageRecord::STATUS_PENDING ? 'bg-amber-100 text-amber-800' : '' }}">
                                    {{ $triageRecord->triage_status_label }}
                                </span>
                                <p class="mt-2 text-xs text-slate-500">{{ $triageRecord->consumedBy?->name ?? 'Waiting for PHN/MHO consumer' }}</p>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('secretary.triage.show', $triageRecord) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">No triage records matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $triageRecords->links() }}
        </div>
    </div>
@endsection
