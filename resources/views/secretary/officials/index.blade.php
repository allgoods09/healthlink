@extends('layouts.portal')

@section('title', 'Official Barangay Roster - HealthLink Secretary')
@section('header', 'Official Barangay Roster')

@section('content')
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 p-6">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Official Barangay Roster</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $barangay->name }}</p>
            </div>
            <x-record-action :href="route('secretary.officials.edit')" variant="edit">Edit Roster</x-record-action>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="px-6 py-3">Position</th>
                        <th scope="col" class="px-6 py-3">Current Official</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    @foreach($officials as $official)
                        <tr>
                            <th scope="row" class="px-6 py-4 text-left font-medium text-slate-900">{{ $official->official_title }}</th>
                            <td class="px-6 py-4 text-slate-600">
                                {{ $official->role_key === \App\Models\BarangayOfficial::ROLE_BARANGAY_SECRETARY ? ($resolvedSecretaryName ?? 'Not assigned') : ($official->official_name ?? 'Not assigned') }}
                                @if($secretaryLinked && $official->role_key === \App\Models\BarangayOfficial::ROLE_BARANGAY_SECRETARY)
                                    <span class="ml-2 text-xs text-slate-500">System-linked</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
