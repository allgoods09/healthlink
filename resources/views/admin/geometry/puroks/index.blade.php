@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Puroks - HealthLink Admin')
@section('header', $pageHeader ?? 'Purok Management')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
    <a href="{{ route($routePrefix.'.puroks.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        Add Purok
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-lg bg-white shadow">
        <div class="p-4">
            <form method="GET" action="{{ route($routePrefix.'.puroks.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Purok number or name">
                </div>

                <div>
                    <label for="barangay_id" class="block text-sm font-medium text-gray-700">Barangay</label>
                    <select name="barangay_id" id="barangay_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All barangays</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}" {{ (string) request('barangay_id') === (string) $barangay->id ? 'selected' : '' }}>
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
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
                    <a href="{{ route($routePrefix.'.puroks.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Purok</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Barangay</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Households</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">BHWs</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($puroks as $purok)
                        <tr class="{{ $purok->trashed() ? 'bg-gray-50' : '' }}">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ $purok->display_name }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $purok->barangay->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $purok->households_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $purok->assigned_users_count }}</td>
                            <td class="px-6 py-4 text-sm">
                                <div>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $purok->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ $purok->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">{{ $purok->trashed() ? 'Deleted' : 'Current' }}</div>
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    @if(!$purok->trashed())
                                        <a href="{{ route($routePrefix.'.puroks.show', $purok) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                        <a href="{{ route($routePrefix.'.puroks.edit', $purok) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        <form action="{{ route($routePrefix.'.puroks.toggle-status', $purok) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="{{ $purok->is_active ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}">
                                                {{ $purok->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        @if($canDelete ?? true)
                                            <form action="{{ route($routePrefix.'.puroks.destroy', $purok) }}" method="POST" class="inline" onsubmit="return confirm('Delete this purok? Existing households and assignments must already be cleared.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        @endif
                                    @else
                                        @if($canRestore ?? true)
                                            <form action="{{ route($routePrefix.'.puroks.restore', $purok) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-green-600 hover:text-green-900">Restore</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No puroks found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $puroks->links() }}
        </div>
    </div>
@endsection
