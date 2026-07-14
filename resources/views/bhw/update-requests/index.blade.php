@extends('layouts.portal')

@php
    $routePrefix = $routePrefix ?? 'bhw';
@endphp

@section('title', $pageTitle ?? 'BHW Update Requests - HealthLink')
@section('header', $pageHeader ?? 'Update Requests')
@section('subheader', $pageSubheader ?? 'Track resident and household correction requests you submitted for Secretary review.')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route($routePrefix.'.update-requests.create-resident') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Resident Correction
        </a>
        <a href="{{ route($routePrefix.'.update-requests.create-household') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Household Correction
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route($routePrefix.'.update-requests.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label for="subject_type" class="block text-sm font-medium text-slate-700">Subject</label>
                    <select name="subject_type" id="subject_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All subjects</option>
                        <option value="resident" @selected(request('subject_type') === 'resident')>Resident</option>
                        <option value="household" @selected(request('subject_type') === 'household')>Household</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filters</button>
                    <a href="{{ route($routePrefix.'.update-requests.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($updateRequests as $updateRequest)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $updateRequest->subject_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $updateRequest->subject_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($updateRequest->request_reason, 110) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $updateRequest->request_status_label }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route($routePrefix.'.update-requests.show', $updateRequest) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">No correction requests have been submitted yet.</td>
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
