@extends('layouts.admin')

@section('title', 'Barangay Details - HealthLink Admin')
@section('header', 'Barangay Details')

@section('actions')
    <div class="flex items-center space-x-2">
        <a href="{{ route('admin.barangays.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
            Back
        </a>
        <a href="{{ route('admin.barangays.edit', $barangay) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
            Edit Barangay
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Barangay Info Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Barangay Information</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $barangay->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">PSGC Code</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $barangay->psgc_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Location</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $barangay->full_address }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @if($barangay->is_active)
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
                            <dt class="text-sm font-medium text-gray-500">Total Puroks</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $barangay->puroks->count() }}</dd>
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
                            <dt class="text-sm font-medium text-gray-500">Assigned Users</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $barangay->assignedUsers->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $barangay->created_at->format('F d, Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Puroks List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">Puroks</h3>
                    <a href="{{ route('admin.puroks.create') }}?barangay_id={{ $barangay->id }}" class="text-sm text-blue-600 hover:text-blue-900">
                        + Add Purok
                    </a>
                </div>
                <div class="p-6">
                    @if($barangay->puroks->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($barangay->puroks as $purok)
                                <li class="py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $purok->display_name }}</p>
                                            <p class="text-sm text-gray-500">
                                                Households: {{ $purok->households->count() }} · Residents: {{ $purok->total_residents ?? 0 }}
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $purok->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $purok->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            <a href="{{ route('admin.puroks.show', $purok) }}" class="text-blue-600 hover:text-blue-900 text-sm">View</a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">No puroks found in this barangay.</p>
                    @endif
                </div>
            </div>

            <!-- Assigned Users -->
            <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Assigned Users</h3>
                </div>
                <div class="p-6">
                    @if($barangay->assignedUsers->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($barangay->assignedUsers as $user)
                                <li class="py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                            <p class="text-sm text-gray-500">{{ $user->email }} · {{ $user->role_label }}</p>
                                        </div>
                                        <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 hover:text-blue-900 text-sm">View</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">No users assigned to this barangay.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection