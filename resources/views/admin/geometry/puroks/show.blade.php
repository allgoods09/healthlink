@extends('layouts.admin')

@section('title', 'Purok Details - HealthLink Admin')
@section('header', 'Purok Details')

@section('actions')
    <div class="flex items-center space-x-2">
        <a href="{{ route('admin.puroks.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
            Back
        </a>
        <a href="{{ route('admin.puroks.edit', $purok) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
            Edit Purok
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Purok Info Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Purok Information</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Purok</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $purok->display_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Barangay</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $purok->barangay->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @if($purok->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Inactive
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total Households</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $totalHouseholds ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total Residents</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $totalResidents ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Assigned BHWs</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $purok->assignedUsers->where('role', 'bhw')->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $purok->created_at->format('F d, Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Households List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">Households</h3>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-900">
                        + Add Household
                    </a>
                </div>
                <div class="p-6">
                    @if($purok->households->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($purok->households as $household)
                                <li class="py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Household #{{ $household->household_no }}</p>
                                            <p class="text-sm text-gray-500">
                                                {{ $household->household_address }} · 
                                                Residents: {{ $household->residents->count() }}
                                                @if($household->is_social_aid_beneficiary)
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Social Aid
                                                    </span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $household->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $household->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            <a href="#" class="text-blue-600 hover:text-blue-900 text-sm">View</a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">No households found in this purok.</p>
                    @endif
                </div>
            </div>

            <!-- Assigned BHWs -->
            <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Assigned BHWs</h3>
                </div>
                <div class="p-6">
                    @php
                        $bhws = $purok->assignedUsers->where('role', 'bhw');
                    @endphp
                    @if($bhws->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($bhws as $bhw)
                                <li class="py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $bhw->name }}</p>
                                            <p class="text-sm text-gray-500">{{ $bhw->email }}</p>
                                        </div>
                                        <a href="{{ route('admin.users.show', $bhw) }}" class="text-blue-600 hover:text-blue-900 text-sm">View</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">No BHWs assigned to this purok.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection