@extends('layouts.portal')

@section('title', 'Certificate Log - HealthLink Secretary')
@section('header', 'Civil Document & Certification Requests')
@section('subheader', 'Issue, review, and export barangay clearances and certificates of indigency tied to verified household and resident records.')

@section('actions')
    <a href="{{ route('secretary.certificates.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        Issue Certificate
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('secretary.certificates.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-6">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="Certificate no., recipient, purpose">
                </div>

                <div>
                    <label for="certificate_type" class="block text-sm font-medium text-slate-700">Certificate Type</label>
                    <select name="certificate_type" id="certificate_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All types</option>
                        <option value="barangay_clearance" {{ request('certificate_type') === 'barangay_clearance' ? 'selected' : '' }}>Barangay Clearance</option>
                        <option value="certificate_of_indigency" {{ request('certificate_type') === 'certificate_of_indigency' ? 'selected' : '' }}>Certificate of Indigency</option>
                    </select>
                </div>

                <div>
                    <label for="recipient_type" class="block text-sm font-medium text-slate-700">Recipient Type</label>
                    <select name="recipient_type" id="recipient_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All recipients</option>
                        <option value="resident" {{ request('recipient_type') === 'resident' ? 'selected' : '' }}>Resident</option>
                        <option value="household" {{ request('recipient_type') === 'household' ? 'selected' : '' }}>Household</option>
                    </select>
                </div>

                <div>
                    <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                    <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Entire barangay</option>
                        @foreach($puroks as $purok)
                            <option value="{{ $purok->id }}" {{ (string) request('purok_id') === (string) $purok->id ? 'selected' : '' }}>{{ $purok->display_name }}</option>
                        @endforeach
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

                <div class="md:col-span-6 flex gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filters</button>
                    <a href="{{ route('secretary.certificates.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Certificate</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Recipient</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Purpose</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Issued</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($certificates as $certificate)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $certificate->certificate_no }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $certificate->certificate_type_label }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <div class="font-medium text-slate-900">{{ $certificate->issued_to_name }}</div>
                                <div class="mt-1 text-slate-500">{{ $certificate->recipient_type_label }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $certificate->resident?->household?->purok?->display_name ?? $certificate->household?->purok?->display_name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($certificate->purpose, 60) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <div>{{ $certificate->issued_at?->format('M d, Y') }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $certificate->issuedBy?->name ?? 'System' }}</div>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('secretary.certificates.show', $certificate) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                    <a href="{{ route('secretary.certificates.pdf', $certificate) }}" class="text-rose-600 hover:text-rose-900">PDF</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No certificate records match the current filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $certificates->links() }}
        </div>
    </div>
@endsection
