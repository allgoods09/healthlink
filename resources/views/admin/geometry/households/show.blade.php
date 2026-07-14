@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Household Details - HealthLink Admin')
@section('header', $pageHeader ?? 'Household Details')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        @if(\Illuminate\Support\Facades\Route::has($routePrefix.'.households.pdf'))
            <a href="{{ route($routePrefix.'.households.pdf', $household) }}" class="inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                Download PDF
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                Print Profile
            </button>
        @endif
        <a href="{{ route($routePrefix.'.residents.create', ['household_id' => $household->id, 'purok_id' => $household->purok_id, 'barangay_id' => $household->purok->barangay_id]) }}" class="inline-flex items-center rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
            Add Resident
        </a>
        <a href="{{ route($routePrefix.'.households.edit', $household) }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Edit
        </a>
        <a href="{{ route($routePrefix.'.households.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            Back
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-lg bg-white shadow lg:col-span-1">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Household Profile</h2>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Household Number</dt>
                        <dd class="text-sm text-gray-900">#{{ $household->household_no }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Barangay</dt>
                        <dd class="text-sm text-gray-900">{{ $household->purok->barangay->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Purok</dt>
                        <dd class="text-sm text-gray-900">{{ $household->purok->display_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Address</dt>
                        <dd class="text-sm text-gray-900">{{ $household->household_address }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Head of Household</dt>
                        <dd class="text-sm text-gray-900">{{ $household->headResident?->formal_name ?? 'Not yet assigned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Social Aid</dt>
                        <dd class="text-sm text-gray-900">{{ $household->is_social_aid_beneficiary ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="text-sm text-gray-900">{{ $household->is_active ? 'Active' : 'Inactive' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="rounded-lg bg-white shadow lg:col-span-2">
            <div class="border-b border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900">Residents</h2>
                <p class="mt-1 text-sm text-gray-500">People currently linked to this household.</p>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($household->residents as $resident)
                    <div class="flex items-start justify-between p-6">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $resident->full_name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $resident->relationship_to_head }} · {{ $resident->sex }} · Age {{ $resident->age }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ $resident->socioEconomicProfile?->occupation ?: 'No occupation recorded' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($household->head_resident_id === $resident->id)
                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                    Household Head
                                </span>
                            @endif
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $resident->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $resident->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <a href="{{ route($routePrefix.'.residents.show', $resident) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900">View</a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-sm text-gray-500">No residents have been linked to this household yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
