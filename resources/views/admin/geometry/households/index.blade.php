@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Households - HealthLink Admin')
@section('header', $pageHeader ?? 'Household Management')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
    <a href="{{ route($routePrefix.'.households.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        Add Household
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-lg bg-white shadow">
        <div class="p-4">
            <form method="GET" action="{{ route($routePrefix.'.households.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-6">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Number or address">
                </div>

                <div>
                    <label for="barangay_id" class="block text-sm font-medium text-gray-700">Barangay</label>
                    <select name="barangay_id" id="barangay_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All barangays</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}" {{ request('barangay_id') == $barangay->id ? 'selected' : '' }}>
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="purok_id" class="block text-sm font-medium text-gray-700">Purok</label>
                    <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All puroks</option>
                        @foreach($puroks as $purok)
                            <option value="{{ $purok->id }}" {{ request('purok_id') == $purok->id ? 'selected' : '' }}>
                                {{ $purok->barangay->name }} - {{ $purok->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="social_aid" class="block text-sm font-medium text-gray-700">Social Aid</label>
                    <select name="social_aid" id="social_aid" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        <option value="yes" {{ request('social_aid') === 'yes' ? 'selected' : '' }}>Yes</option>
                        <option value="no" {{ request('social_aid') === 'no' ? 'selected' : '' }}>No</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
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

                <div class="md:col-span-6 flex items-center gap-2">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply Filters</button>
                    <a href="{{ route($routePrefix.'.households.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Household</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Residents</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Aid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lifecycle</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($households as $household)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">Household #{{ $household->household_no }}</div>
                                <div class="text-sm text-gray-500">{{ $household->household_address }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $household->purok->barangay->name }}<br>
                                <span class="text-gray-500">{{ $household->purok->display_name }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $household->residents_count }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $household->is_social_aid_beneficiary ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $household->is_social_aid_beneficiary ? 'Beneficiary' : 'None' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $household->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $household->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $household->trashed() ? 'Deleted' : 'Current' }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @if(!$household->trashed())
                                        <a href="{{ route($routePrefix.'.households.show', $household) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                        <a href="{{ route($routePrefix.'.households.edit', $household) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        <a href="{{ route($routePrefix.'.residents.create', ['household_id' => $household->id, 'purok_id' => $household->purok_id, 'barangay_id' => $household->purok->barangay_id]) }}" class="text-teal-600 hover:text-teal-900">Add Resident</a>
                                        <form action="{{ route($routePrefix.'.households.toggle-status', $household) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="{{ $household->is_active ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}">
                                                {{ $household->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        @if($canDelete ?? true)
                                            <form action="{{ route($routePrefix.'.households.destroy', $household) }}" method="POST" class="inline" onsubmit="return confirm('Delete this household and soft-delete all of its residents?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        @endif
                                    @else
                                        @if($canRestore ?? true)
                                            <form action="{{ route($routePrefix.'.households.restore', $household->id) }}" method="POST" class="inline">
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
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No households found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $households->links() }}
        </div>
    </div>
@endsection
