@extends('layouts.portal')

@section('title', 'BNS Micronutrients - HealthLink')
@section('header', 'Micronutrient Logs')
@section('subheader', 'Record Vitamin A, Iron Drops, and Micronutrient Powder distribution for toddlers, pregnant women, and lactating mothers.')

@section('actions')
    <a href="{{ route('bns.micronutrients.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        Add Supplementation Log
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.micronutrients.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Resident or notes" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="supplement_type" class="block text-sm font-medium text-slate-700">Supplement</label>
                    <select name="supplement_type" id="supplement_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All supplements</option>
                        @foreach($supplementTypes as $supplementType => $label)
                            <option value="{{ $supplementType }}" @selected(request('supplement_type') === $supplementType)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="recipient_category" class="block text-sm font-medium text-slate-700">Recipient Category</label>
                    <select name="recipient_category" id="recipient_category" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All categories</option>
                        @foreach($recipientCategories as $recipientCategory => $label)
                            <option value="{{ $recipientCategory }}" @selected(request('recipient_category') === $recipientCategory)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('bns.micronutrients.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Supplement</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Dose</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Logged By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $log->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $log->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ $log->supplement_type_label }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $log->recipient_category_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ $log->dose_description ?: 'No dose description' }}</p>
                                @if($log->remarks)
                                    <p class="mt-1 text-xs text-slate-400">{{ $log->remarks }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $log->administered_on?->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $log->distributedBy?->name ?? 'Unknown user' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                No micronutrient logs match the current filters yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
