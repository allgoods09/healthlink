@extends('layouts.admin')

@section('title', 'Audit Log Details - HealthLink Admin')
@section('header', 'Audit Log Details')

@section('actions')
    <a href="{{ route('admin.audit.index', request()->query()) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Logs
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Event Details -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Event Details</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Event Type</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                @if(in_array($auditLog->event_type, ['login', 'logout'])) bg-blue-100 text-blue-800
                                @elseif($auditLog->event_type == 'failed_login') bg-red-100 text-red-800
                                @elseif(in_array($auditLog->event_type, ['created', 'restored'])) bg-green-100 text-green-800
                                @elseif(in_array($auditLog->event_type, ['updated', 'status_toggled'])) bg-yellow-100 text-yellow-800
                                @elseif(in_array($auditLog->event_type, ['deleted', 'force_deleted'])) bg-red-100 text-red-800
                                @elseif($auditLog->event_type == 'synced') bg-purple-100 text-purple-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ $auditLog->event_type_label }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $auditLog->event_description }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">User</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $auditLog->actor_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $auditLog->ip_address ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">User Agent</dt>
                        <dd class="mt-1 text-sm text-gray-900 break-all">{{ $auditLog->user_agent ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Timestamp</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $auditLog->created_at->format('F d, Y h:i:s A') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Data Changes -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Data Changes</h3>
            </div>
            <div class="p-6">
                @if($auditLog->model_type)
                    <div class="mb-4">
                        <dt class="text-sm font-medium text-gray-500">Model</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ class_basename($auditLog->model_type) }} #{{ $auditLog->model_id }}</dd>
                    </div>
                @endif

                @if($auditLog->old_values || $auditLog->new_values)
                    <div class="space-y-4">
                        @if($auditLog->old_values)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Old Values</dt>
                                <dd class="mt-1">
                                    <pre class="text-sm bg-gray-50 p-3 rounded-md overflow-x-auto">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT) }}</pre>
                                </dd>
                            </div>
                        @endif
                        @if($auditLog->new_values)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">New Values</dt>
                                <dd class="mt-1">
                                    <pre class="text-sm bg-gray-50 p-3 rounded-md overflow-x-auto">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT) }}</pre>
                                </dd>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-500">No data changes recorded for this event.</p>
                @endif

                @if($auditLog->metadata)
                    <div class="mt-4">
                        <dt class="text-sm font-medium text-gray-500">Additional Metadata</dt>
                        <dd class="mt-1">
                            <pre class="text-sm bg-gray-50 p-3 rounded-md overflow-x-auto">{{ json_encode($auditLog->metadata, JSON_PRETTY_PRINT) }}</pre>
                        </dd>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection