@extends('layouts.admin')

@section('title', 'Data Archive - HealthLink Admin')
@section('header', 'Data Archive Management')

@section('actions')
    <a href="{{ route('admin.archive.create') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Archive Record
    </a>
@endsection

@section('content')
    <!-- Info Box -->
    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-sm text-yellow-700">
            <strong>Note:</strong> Archived records are soft-deleted from their original tables and stored here for historical reference. 
            You can restore them or purge them permanently from the system.
        </p>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-lg shadow">
        <div class="p-4">
            <form method="GET" action="{{ route('admin.archive.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                           placeholder="Table or reason...">
                </div>

                <!-- Table -->
                <div>
                    <label for="table" class="block text-sm font-medium text-gray-700">Original Table</label>
                    <select name="table" id="table" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Tables</option>
                        @foreach($tables as $table)
                            <option value="{{ $table }}" {{ request('table') == $table ? 'selected' : '' }}>
                                {{ ucfirst($table) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Purged -->
                <div>
                    <label for="purged" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="purged" id="purged" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Status</option>
                        <option value="false" {{ request('purged') == 'false' ? 'selected' : '' }}>Active</option>
                        <option value="true" {{ request('purged') == 'true' ? 'selected' : '' }}>Purged</option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>

                <!-- Date To -->
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>

                <!-- Actions -->
                <div class="flex items-end space-x-2 md:col-span-5">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.archive.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Archive Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Record</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Original Table</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archived By</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archived At</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($archives as $archive)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $archive->display_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ ucfirst($archive->original_table) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $archive->archivedBy?->name ?? 'Unknown' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate">
                                {{ $archive->archiving_reason ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($archive->is_purged)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Purged
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $archive->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="{{ route('admin.archive.show', $archive) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                    @if(!$archive->is_purged)
                                        <form action="{{ route('admin.archive.restore', $archive) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to restore this record?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-green-600 hover:text-green-900">Restore</button>
                                        </form>
                                        <x-destructive-confirm-modal
                                            :action="route('admin.archive.purge', $archive)"
                                            method="DELETE"
                                            title="Purge Archived Record"
                                            description="This permanently purges the selected archive entry from the active archive set."
                                            trigger-label="Purge"
                                            trigger-class="text-red-600 hover:text-red-900"
                                            confirmation-word="PURGE"
                                            submit-label="Purge Record"
                                            reason-label="Reason for permanent purge"
                                            reason-placeholder="Explain why this archive entry is being permanently purged."
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                No archived records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $archives->links() }}
        </div>
    </div>
@endsection
