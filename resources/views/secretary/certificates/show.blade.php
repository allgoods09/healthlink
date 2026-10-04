@extends('layouts.portal')

@section('title', 'Certificate Details - HealthLink Secretary')
@section('header', 'Certificate Details')
@section('subheader', 'Official issuance snapshot with recipient, purpose, and export-ready barangay record details.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <x-record-action :href="route('secretary.certificates.pdf', $certificate)" variant="document">Download PDF</x-record-action>
        <x-record-action :href="route('secretary.certificates.create')" variant="add">Issue Another</x-record-action>
        <x-record-action :href="route('secretary.certificates.index')" variant="back">Back to Log</x-record-action>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">{{ $certificate->certificate_type_label }}</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $certificate->certificate_no }}</h2>
            </div>
            <div class="grid gap-5 p-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Issued To</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ $certificate->issued_to_name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Recipient Type</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ $certificate->recipient_type_label }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Issued At (Philippine time)</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ $certificate->issued_at?->copy()->timezone('Asia/Manila')->format('F d, Y h:i A') }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Issued By</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ $certificate->issuedBy?->name ?? 'System' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Purpose</p>
                    <p class="mt-1 text-base text-slate-700">{{ $certificate->purpose }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Remarks</p>
                    <p class="mt-1 text-base text-slate-700">{{ $certificate->remarks ?: 'No remarks provided.' }}</p>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Barangay Record</p>
                <h3 class="mt-3 text-xl font-semibold tracking-tight text-slate-900">{{ $certificate->barangay?->name }}</h3>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    {{ $certificate->barangay?->municipality }}, {{ $certificate->barangay?->province }}
                </p>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Linked Recipient</p>

                @if($certificate->resident)
                    <div class="mt-3">
                        <p class="text-base font-semibold text-slate-900">{{ $certificate->resident->formal_name }}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            Household #{{ $certificate->resident->household?->household_no }} · {{ $certificate->resident->household?->purok?->display_name }}
                        </p>
                        <a href="{{ route('secretary.residents.show', $certificate->resident) }}" class="mt-3 inline-flex text-sm font-medium text-tubigon hover:text-tubigon-hover">
                            View resident record
                        </a>
                    </div>
                @elseif($certificate->household)
                    <div class="mt-3">
                        <p class="text-base font-semibold text-slate-900">Household #{{ $certificate->household->household_no }}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $certificate->household->purok?->display_name }} · {{ $certificate->household->headResident?->formal_name ?: 'No assigned head yet' }}
                        </p>
                        <a href="{{ route('secretary.households.show', $certificate->household) }}" class="mt-3 inline-flex text-sm font-medium text-tubigon hover:text-tubigon-hover">
                            View household record
                        </a>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500">This certificate is stored without a linked active record.</p>
                @endif
            </div>
        </section>
    </div>
@endsection
