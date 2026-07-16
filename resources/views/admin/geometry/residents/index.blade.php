@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Residents - HealthLink Admin')
@section('header', $pageHeader ?? 'Resident Management')

@php
    $routePrefix = $routePrefix ?? 'admin';
    $householdSearchOptions = $households->map(fn ($household) => [
        'value' => $household->id,
        'label' => $household->purok->barangay->name.' - '.$household->purok->display_name.' - #'.$household->household_no,
        'description' => $household->household_address ?: 'No household address',
        'search' => collect([
            $household->purok->barangay->name,
            $household->purok->display_name,
            $household->household_no ? 'household '.$household->household_no : null,
            $household->household_address,
            $household->headResident?->formal_name,
        ])->filter()->implode(' '),
    ])->values()->all();
@endphp

@section('actions')
    <a href="{{ route($routePrefix.'.residents.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        Add Resident
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-lg bg-white shadow">
        <div class="p-4">
            <form method="GET" action="{{ route($routePrefix.'.residents.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-8">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name or PhilSys ID">
                </div>

                <div>
                    <label for="barangay_id" class="block text-sm font-medium text-gray-700">Barangay</label>
                    <select name="barangay_id" id="barangay_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All barangays</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}" {{ request('barangay_id') == $barangay->id ? 'selected' : '' }}>{{ $barangay->name }}</option>
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
                    <label for="household_id" class="block text-sm font-medium text-gray-700">Household</label>
                    <x-searchable-record-select
                        name="household_id"
                        id="household_id"
                        :options="$householdSearchOptions"
                        :selected="request('household_id')"
                        placeholder="Search household number or address"
                        empty-message="No household matches your search."
                        class="rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>

                <div>
                    <label for="sex" class="block text-sm font-medium text-gray-700">Sex</label>
                    <select name="sex" id="sex" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        <option value="Male" {{ request('sex') === 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ request('sex') === 'Female' ? 'selected' : '' }}>Female</option>
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
                    <label for="resident_status" class="block text-sm font-medium text-gray-700">Civil Status</label>
                    <select name="resident_status" id="resident_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All statuses</option>
                        <option value="active" {{ request('resident_status') === 'active' ? 'selected' : '' }}>Active Resident</option>
                        <option value="deceased" {{ request('resident_status') === 'deceased' ? 'selected' : '' }}>Deceased</option>
                        <option value="relocated" {{ request('resident_status') === 'relocated' ? 'selected' : '' }}>Relocated</option>
                    </select>
                </div>

                <div>
                    <label for="age_group" class="block text-sm font-medium text-gray-700">Age Group</label>
                    <select name="age_group" id="age_group" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All groups</option>
                        <option value="minor" {{ request('age_group') === 'minor' ? 'selected' : '' }}>Minors (0-17)</option>
                        <option value="adult" {{ request('age_group') === 'adult' ? 'selected' : '' }}>Adults (18-59)</option>
                        <option value="senior" {{ request('age_group') === 'senior' ? 'selected' : '' }}>Seniors (60+)</option>
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
                    <a href="{{ route($routePrefix.'.residents.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Resident</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Household</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Profile</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lifecycle</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($residents as $resident)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ $resident->formal_name }}</div>
                                <div class="text-sm text-gray-500">{{ $resident->sex }} · Age {{ $resident->age }}</div>
                                <div class="text-sm text-gray-500">{{ $resident->philsys_card_no ?: 'No PhilSys ID' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $resident->household->purok->barangay->name }}<br>
                                <span class="text-gray-500">{{ $resident->household->purok->display_name }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                #{{ $resident->household->household_no }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $resident->is_household_head ? 'Household Head' : $resident->relationship_to_head }}<br>
                                <span class="text-gray-500">{{ $resident->socioEconomicProfile?->occupation ?: 'No occupation' }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $resident->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $resident->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div>{{ $resident->resident_status_label }}</div>
                                <div class="mt-1 text-xs text-gray-400">{{ $resident->trashed() ? 'Deleted' : 'Current record' }}</div>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @if(!$resident->trashed())
                                        <a href="{{ route($routePrefix.'.residents.show', $resident) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                        <a href="{{ route($routePrefix.'.residents.edit', $resident) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        @if($canRelocate ?? false)
                                            <a href="{{ route($routePrefix.'.residents.relocate.edit', $resident) }}" class="text-teal-600 hover:text-teal-900">Relocate</a>
                                        @endif
                                        <form action="{{ route($routePrefix.'.residents.toggle-status', $resident) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="{{ $resident->is_active ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}">
                                                {{ $resident->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        @if($canDelete ?? true)
                                            <form action="{{ route($routePrefix.'.residents.destroy', $resident) }}" method="POST" class="inline" onsubmit="return confirm('Delete this resident record?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        @endif
                                    @else
                                        @if($canRestore ?? true)
                                            <form action="{{ route($routePrefix.'.residents.restore', $resident->id) }}" method="POST" class="inline">
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
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No residents found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $residents->links() }}
        </div>
    </div>
@endsection
