@extends('layouts.portal')

@section('title', 'Correction Requests - HealthLink')
@section('header', 'Correction Requests')
@section('subheader', 'Review BHW-submitted household and resident correction requests, compare current versus proposed values, and apply secretary-approved changes safely.')

@section('actions')
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('secretary.update-requests.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Subject or reason" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>

                <div>
                    <label for="subject_type" class="block text-sm font-medium text-slate-700">Subject Type</label>
                    <select name="subject_type" id="subject_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All types</option>
                        <option value="{{ \App\Models\ProfileUpdateRequest::SUBJECT_RESIDENT }}" {{ request('subject_type') === \App\Models\ProfileUpdateRequest::SUBJECT_RESIDENT ? 'selected' : '' }}>Resident</option>
                        <option value="{{ \App\Models\ProfileUpdateRequest::SUBJECT_HOUSEHOLD }}" {{ request('subject_type') === \App\Models\ProfileUpdateRequest::SUBJECT_HOUSEHOLD ? 'selected' : '' }}>Household</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="{{ \App\Models\ProfileUpdateRequest::STATUS_PENDING }}" {{ request('status') === \App\Models\ProfileUpdateRequest::STATUS_PENDING ? 'selected' : '' }}>Pending</option>
                        <option value="{{ \App\Models\ProfileUpdateRequest::STATUS_APPROVED }}" {{ request('status') === \App\Models\ProfileUpdateRequest::STATUS_APPROVED ? 'selected' : '' }}>Approved</option>
                        <option value="{{ \App\Models\ProfileUpdateRequest::STATUS_REJECTED }}" {{ request('status') === \App\Models\ProfileUpdateRequest::STATUS_REJECTED ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('secretary.update-requests.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Requested By</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($updateRequests as $updateRequest)
                        <tr class="{{ $updateRequest->request_status === \App\Models\ProfileUpdateRequest::STATUS_PENDING ? 'bg-amber-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $updateRequest->subject_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $updateRequest->subject_label }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $updateRequest->created_at->format('M d, Y h:i A') }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $updateRequest->submittedBy?->name ?? 'Unknown user' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($updateRequest->request_reason ?: 'No reason provided.', 90) }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $updateRequest->request_status === \App\Models\ProfileUpdateRequest::STATUS_APPROVED ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $updateRequest->request_status === \App\Models\ProfileUpdateRequest::STATUS_PENDING ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $updateRequest->request_status === \App\Models\ProfileUpdateRequest::STATUS_REJECTED ? 'bg-rose-100 text-rose-800' : '' }}">
                                    {{ $updateRequest->request_status_label }}
                                </span>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('secretary.update-requests.show', $updateRequest) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                                    @if($updateRequest->request_status === \App\Models\ProfileUpdateRequest::STATUS_PENDING)
                                        <a href="{{ route('secretary.update-requests.edit', $updateRequest) }}" class="text-indigo-600 hover:text-indigo-800">Review</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">No correction requests matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $updateRequests->links() }}
        </div>
    </div>
@endsection
