@extends('layouts.admin')

@section('title', 'Archived Record Details - HealthLink Admin')
@section('header', 'Archived Record Details')

@section('actions')
    <a href="{{ route('admin.archive.index', request()->query()) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Archive
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Archive Info -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Archive Information</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Record</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $archivedRecord->display_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Original Table</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($archivedRecord->original_table) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Original ID</dt>
                        <dd class="mt-1 text-sm text-gray-900">#{{ $archivedRecord->original_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Archived By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $archivedRecord->archivedBy?->name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Archiving Reason</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $archivedRecord->archiving_reason ?? 'No reason provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            @if($archivedRecord->is_purged)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Purged
                                </span>
                                @if($archivedRecord->purged_at)
                                    <span class="ml-2 text-sm text-gray-500">on {{ $archivedRecord->purged_at->format('Y-m-d H:i') }}</span>
                                @endif
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Archived At</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $archivedRecord->created_at->format('F d, Y h:i:s A') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Data Snapshot -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Data Snapshot</h3>
            </div>
            <div class="p-6">
                <pre class="text-sm bg-gray-50 p-4 rounded-md overflow-x-auto">{{ json_encode($archivedRecord->data_snapshot, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    <!-- Actions -->
    @if(!$archivedRecord->is_purged)
        <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Actions</h3>
            </div>
            <div class="p-6 flex space-x-4">
                <form action="{{ route('admin.archive.restore', $archivedRecord) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to restore this record?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Restore Record
                    </button>
                </form>
                <x-destructive-confirm-modal
                    :action="route('admin.archive.purge', $archivedRecord)"
                    method="DELETE"
                    title="Purge Archived Record"
                    description="This permanently marks the archive as purged and keeps the action in the audit trail. Make sure no dependent archives still need this snapshot."
                    trigger-label="Purge Permanently"
                    trigger-class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                    confirmation-word="PURGE"
                    submit-label="Purge Record"
                    reason-label="Reason for permanent purge"
                    reason-placeholder="Explain why this archived record must be permanently purged."
                />
            </div>
        </div>
    @endif
@endsection
