@extends('layouts.admin')

@section('title', 'User Details - HealthLink Admin')
@section('header', 'User Details')

@section('actions')
    <div class="flex items-center space-x-2">
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
            Back
        </a>
        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
            Edit User
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- User Info Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">User Information</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-center mb-6">
                        <div class="h-24 w-24 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-2xl font-bold">
                            {{ substr($user->name, 0, 2) }}
                        </div>
                    </div>
                    
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Role</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if($user->role == 'admin') bg-red-100 text-red-800
                                    @elseif($user->role == 'mho') bg-purple-100 text-purple-800
                                    @elseif($user->role == 'phn') bg-indigo-100 text-indigo-800
                                    @elseif($user->role == 'secretary') bg-blue-100 text-blue-800
                                    @elseif($user->role == 'bns') bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $user->role_label }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @if($user->is_active)
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
                        @if($user->assignedBarangay)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Assigned Barangay</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $user->assignedBarangay->name }}</dd>
                            </div>
                        @endif
                        @if($user->assignedPurok)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Assigned Purok</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $user->assignedPurok->display_name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Joined</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('F d, Y h:i A') }}</dd>
                        </div>
                        @if($user->deleted_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Deleted At</dt>
                                <dd class="mt-1 text-sm text-red-600">{{ $user->deleted_at->format('F d, Y h:i A') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Activity</h3>
                </div>
                <div class="p-6">
                    @if($user->auditLogs->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($user->auditLogs->take(10) as $log)
                                <li class="py-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $log->event_description }}
                                            </p>
                                            <p class="text-sm text-gray-500 truncate">
                                                {{ $log->event_type_label }} · {{ $log->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $log->event_type_label }}
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">No activity recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection