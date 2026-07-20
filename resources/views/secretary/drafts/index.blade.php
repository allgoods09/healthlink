@extends('layouts.portal')

@section('title', 'Field Draft Queue - HealthLink')
@section('header', 'Field Draft Queue')
@section('subheader', 'Review pending household draft packages from the field, edit details before approval, and promote clean records into the verified registry.')

@section('actions')
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('secretary.drafts.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Reference, address, resident" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
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
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="{{ \App\Models\HouseholdDraft::STATUS_PENDING }}" {{ request('status') === \App\Models\HouseholdDraft::STATUS_PENDING ? 'selected' : '' }}>Pending</option>
                        <option value="{{ \App\Models\HouseholdDraft::STATUS_APPROVED }}" {{ request('status') === \App\Models\HouseholdDraft::STATUS_APPROVED ? 'selected' : '' }}>Approved</option>
                        <option value="{{ \App\Models\HouseholdDraft::STATUS_REJECTED }}" {{ request('status') === \App\Models\HouseholdDraft::STATUS_REJECTED ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('secretary.drafts.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Draft Package</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Residents</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Submitted By</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($drafts as $draft)
                        <tr class="{{ $draft->draft_status === \App\Models\HouseholdDraft::STATUS_PENDING ? 'bg-amber-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $draft->draft_reference_code }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $draft->household_address }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $draft->created_at->format('M d, Y h:i A') }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $draft->purok?->display_name ?? 'No draft purok selected' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ number_format($draft->resident_drafts_count) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $draft->submittedBy?->name ?? 'Unknown user' }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $draft->draft_status === \App\Models\HouseholdDraft::STATUS_APPROVED ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $draft->draft_status === \App\Models\HouseholdDraft::STATUS_PENDING ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $draft->draft_status === \App\Models\HouseholdDraft::STATUS_REJECTED ? 'bg-rose-100 text-rose-800' : '' }}">
                                    {{ $draft->draft_status_label }}
                                </span>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('secretary.drafts.show', $draft) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                                    @if($draft->draft_status === \App\Models\HouseholdDraft::STATUS_PENDING)
                                        <a href="{{ route('secretary.drafts.edit', $draft) }}" class="text-indigo-600 hover:text-indigo-800">Review</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No field draft packages matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $drafts->links() }}
        </div>
    </div>
@endsection
