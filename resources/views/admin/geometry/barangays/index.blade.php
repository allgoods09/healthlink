@extends('layouts.admin')

@section('title', 'Barangays - HealthLink Admin')
@section('header', 'Barangay Management')

@section('actions')
    <a href="{{ route('admin.barangays.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        Add Barangay
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-lg bg-white shadow">
        <div class="p-4">
            <form method="GET" action="{{ route('admin.barangays.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name or PSGC code">
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div>
                    <label for="lifecycle" class="block text-sm font-medium text-gray-700">Lifecycle</label>
                    <select name="lifecycle" id="lifecycle" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Current</option>
                        <option value="all" {{ request('lifecycle') === 'all' ? 'selected' : '' }}>All</option>
                        <option value="deleted" {{ request('lifecycle') === 'deleted' ? 'selected' : '' }}>Deleted Only</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply Filters</button>
                    <a href="{{ route('admin.barangays.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Barangay</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">PSGC Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Puroks</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Users</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($barangays as $barangay)
                        <tr class="{{ $barangay->trashed() ? 'bg-gray-50' : '' }}">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ $barangay->name }}</div>
                                <div class="text-sm text-gray-500">{{ $barangay->full_address }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $barangay->psgc_code }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $barangay->puroks_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $barangay->assigned_users_count }}</td>
                            <td class="px-6 py-4 text-sm">
                                <div>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $barangay->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ $barangay->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">{{ $barangay->trashed() ? 'Deleted' : 'Current' }}</div>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    @if(!$barangay->trashed())
                                        <a href="{{ route('admin.barangays.show', $barangay) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                        <a href="{{ route('admin.barangays.edit', $barangay) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        <form action="{{ route('admin.barangays.toggle-status', $barangay) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="{{ $barangay->is_active ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}">
                                                {{ $barangay->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.barangays.destroy', $barangay) }}" method="POST" class="inline" onsubmit="return confirm('Delete this barangay? Existing puroks and assignments must already be cleared.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.barangays.restore', $barangay) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-green-600 hover:text-green-900">Restore</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No barangays found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $barangays->links() }}
        </div>
    </div>
@endsection
