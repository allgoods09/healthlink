@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Mobile Devices - HealthLink Admin')
@section('header', $pageHeader ?? 'Mobile Device Management')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
@endsection

@section('content')
    @if(session('issued_token'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-medium text-emerald-900">New token issued for {{ session('issued_user_name') }} / {{ session('issued_device_name') }}</p>
            <p class="mt-2 text-xs text-emerald-800">Copy this now. The full token is only shown once.</p>
            <div class="mt-3 overflow-x-auto rounded-md bg-white p-3 font-mono text-xs text-gray-800">{{ session('issued_token') }}</div>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Issued Tokens</dt>
            <dd class="mt-2 text-2xl font-bold text-gray-900">{{ $devices->count() }}</dd>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Stale Tokens</dt>
            <dd class="mt-2 text-2xl font-bold text-rose-600">{{ $devices->where('is_stale', true)->count() }}</dd>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Active Recently</dt>
            <dd class="mt-2 text-2xl font-bold text-emerald-600">{{ $devices->where('is_stale', false)->count() }}</dd>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-lg bg-white shadow {{ ($canIssue ?? true) ? 'xl:col-span-2' : 'xl:col-span-3' }}">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900">Device Inventory</h2>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600">
                    {{ ($canIssue ?? true)
                        ? 'Admins can issue, review, revoke, and export every mobile device token currently allowed to sync with HealthLink.'
                        : 'This inventory is limited to BHW accounts in your assigned barangay. You can review token health and revoke access when needed.' }}
                </p>

                <form method="GET" action="{{ route($routePrefix.'.devices.index') }}" class="mt-4 flex flex-col gap-3 md:flex-row">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search BHW name or email" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Search</button>
                    <a href="{{ route($routePrefix.'.devices.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Reset</a>
                </form>
            </div>
        </div>

        @if($canIssue ?? true)
            <div class="rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-medium text-gray-900">Issue Device Token</h2>
                </div>
                <div class="p-6">
                    <form action="{{ route($routePrefix.'.devices.issue') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700">BHW Account</label>
                            <select name="user_id" id="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select a BHW</option>
                                @foreach($bhws as $bhw)
                                    <option value="{{ $bhw->id }}" {{ old('user_id') == $bhw->id ? 'selected' : '' }}>
                                        {{ $bhw->name }} - {{ $bhw->assignment_label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="device_name" class="block text-sm font-medium text-gray-700">Device Name</label>
                            <input type="text" name="device_name" id="device_name" value="{{ old('device_name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="e.g. BHW Tablet 1">
                            @error('device_name')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Issue Token
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">BHW</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Device</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assignment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Usage Health</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Last Used</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($devices as $device)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ $device['user']->name }}</div>
                                <div class="text-sm text-gray-500">{{ $device['user']->email }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $device['device_name'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $device['user']->assignment_label }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $device['is_stale'] ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $device['is_stale'] ? 'Needs Review' : 'Healthy' }}
                                </span>
                                <div class="mt-1 text-xs text-gray-500">{{ $device['stale_reason'] }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $device['last_used'] ? $device['last_used']->diffForHumans() : 'Never' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $device['created_at']->format('M d, Y h:i A') }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <form action="{{ route($routePrefix.'.devices.revoke', $device['token']->id) }}" method="POST" class="inline" onsubmit="return confirm('Revoke this device token? The BHW will lose sync access immediately.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-900">Revoke</button>
                                    </form>

                                    <form action="{{ route($routePrefix.'.devices.revoke-all', $device['user']) }}" method="POST" class="inline" onsubmit="return confirm('Revoke every device token for this BHW?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-amber-600 hover:text-amber-900">Revoke All</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No mobile devices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
