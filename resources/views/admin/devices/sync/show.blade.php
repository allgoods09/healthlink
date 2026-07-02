@extends('layouts.admin')

@section('title', 'Sync Log Details - HealthLink Admin')
@section('header', 'Sync Log Details')

@section('actions')
    <a href="{{ route('admin.sync-logs.index', request()->query()) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Logs
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Sync Details -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Sync Details</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">BHW</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $syncLog->user?->name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Device</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $syncLog->device_name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Device Model</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $syncLog->device_model ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">App Version</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $syncLog->app_version ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Network Type</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $syncLog->network_type ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $syncLog->ip_address ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                @if($syncLog->status == 'success') bg-green-100 text-green-800
                                @elseif($syncLog->status == 'failed') bg-red-100 text-red-800
                                @else bg-yellow-100 text-yellow-800
                                @endif">
                                {{ ucfirst($syncLog->status) }}
                            </span>
                        </dd>
                    </div>
                    @if($syncLog->error_message)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Error Message</dt>
                            <dd class="mt-1 text-sm text-red-600">{{ $syncLog->error_message }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Timestamp</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $syncLog->created_at->format('F d, Y h:i:s A') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Sync Metrics -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Sync Metrics</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Records Synced</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($syncLog->records_synced) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payload Size</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $syncLog->formatted_payload_size }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Sync Duration</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $syncLog->formatted_duration }}</dd>
                    </div>
                    @if($syncLog->sync_metadata)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Additional Metadata</dt>
                            <dd class="mt-1">
                                <pre class="text-sm bg-gray-50 p-3 rounded-md overflow-x-auto">{{ json_encode($syncLog->sync_metadata, JSON_PRETTY_PRINT) }}</pre>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
@endsection