@extends('layouts.admin')

@section('title', 'Rate Limits - HealthLink Admin')
@section('header', 'Rate Limit Configuration')

@section('content')
    <!-- Info Box -->
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm text-blue-700">
            <strong>Note:</strong> Rate limits protect your API from abuse and ensure fair usage. 
            Adjust these settings carefully based on your system's capacity.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Rate Limit Settings -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Rate Limit Settings</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.rate-limits.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div>
                            <label for="rate_limit_attempts" class="block text-sm font-medium text-gray-700">Admin Helper API Limit</label>
                            <input type="number" name="rate_limit_attempts" id="rate_limit_attempts" 
                                   value="{{ old('rate_limit_attempts', $settings['rate_limit_attempts'] ?? 60) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   min="1" max="1000">
                            <p class="mt-1 text-xs text-gray-500">Maximum AJAX/helper requests allowed for admin-only API endpoints per window</p>
                            @error('rate_limit_attempts')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="rate_limit_decay_minutes" class="block text-sm font-medium text-gray-700">Time Window (Minutes)</label>
                            <input type="number" name="rate_limit_decay_minutes" id="rate_limit_decay_minutes" 
                                   value="{{ old('rate_limit_decay_minutes', $settings['rate_limit_decay_minutes'] ?? 1) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   min="1" max="60">
                            <p class="mt-1 text-xs text-gray-500">Time window for rate limit tracking</p>
                            @error('rate_limit_decay_minutes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="api_rate_limit_global" class="block text-sm font-medium text-gray-700">Mobile API Limit</label>
                            <input type="number" name="api_rate_limit_global" id="api_rate_limit_global" 
                                   value="{{ old('api_rate_limit_global', $settings['api_rate_limit_global'] ?? 60) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   min="1" max="5000">
                            <p class="mt-1 text-xs text-gray-500">Maximum authenticated mobile API requests per window</p>
                            @error('api_rate_limit_global')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="api_rate_limit_auth" class="block text-sm font-medium text-gray-700">Login Attempt Limit</label>
                            <input type="number" name="api_rate_limit_auth" id="api_rate_limit_auth" 
                                   value="{{ old('api_rate_limit_auth', $settings['api_rate_limit_auth'] ?? 10) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   min="1" max="100">
                            <p class="mt-1 text-xs text-gray-500">Maximum failed login attempts before the authentication throttle blocks access</p>
                            @error('api_rate_limit_auth')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sync_batch_size" class="block text-sm font-medium text-gray-700">Sync Batch Size</label>
                            <input type="number" name="sync_batch_size" id="sync_batch_size" 
                                   value="{{ old('sync_batch_size', $settings['sync_batch_size'] ?? 100) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   min="10" max="500">
                            <p class="mt-1 text-xs text-gray-500">Maximum records per sync request from mobile</p>
                            @error('sync_batch_size')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="backup_retention_days" class="block text-sm font-medium text-gray-700">Backup Retention (Days)</label>
                            <input type="number" name="backup_retention_days" id="backup_retention_days" 
                                   value="{{ old('backup_retention_days', $settings['backup_retention_days'] ?? 30) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   min="1" max="365">
                            <p class="mt-1 text-xs text-gray-500">How many days to keep backup files</p>
                            @error('backup_retention_days')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset Rate Limits -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Reset Rate Limits</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">
                    Reset rate limits for a specific IP, user, or all users. This is useful when legitimate users get locked out.
                </p>

                <form method="POST" action="{{ route('admin.rate-limits.reset') }}">
                    @csrf

                    <div class="space-y-4">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Reset Type</label>
                            <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="ip">By IP Address</option>
                                <option value="user">By User ID</option>
                                <option value="all">All Users (Global Reset)</option>
                            </select>
                        </div>

                        <div id="identifier-field">
                            <label for="identifier" class="block text-sm font-medium text-gray-700">Identifier</label>
                            <input type="text" name="identifier" id="identifier" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   placeholder="e.g., 192.168.1.1 or user@email.com">
                            <p class="mt-1 text-xs text-gray-500">IP address or User ID (leave empty for all)</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-yellow-600 rounded-md hover:bg-yellow-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Reset Rate Limits
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Current Rate Limit Status -->
    <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Current Rate Limit Status</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="bg-gray-50 p-4 rounded-md text-center">
                    <dt class="text-sm font-medium text-gray-500">Admin Helper API</dt>
                    <dd class="text-2xl font-bold text-gray-900">{{ $settings['rate_limit_attempts'] ?? 60 }} / {{ $settings['rate_limit_decay_minutes'] ?? 1 }} min</dd>
                </div>
                <div class="bg-gray-50 p-4 rounded-md text-center">
                    <dt class="text-sm font-medium text-gray-500">Mobile API</dt>
                    <dd class="text-2xl font-bold text-gray-900">{{ $settings['api_rate_limit_global'] ?? 60 }} / {{ $settings['rate_limit_decay_minutes'] ?? 1 }} min</dd>
                </div>
                <div class="bg-gray-50 p-4 rounded-md text-center">
                    <dt class="text-sm font-medium text-gray-500">Login Attempts</dt>
                    <dd class="text-2xl font-bold text-gray-900">{{ $settings['api_rate_limit_auth'] ?? 10 }} / {{ $settings['rate_limit_decay_minutes'] ?? 1 }} min</dd>
                </div>
                <div class="bg-gray-50 p-4 rounded-md text-center">
                    <dt class="text-sm font-medium text-gray-500">Sync Batch Size</dt>
                    <dd class="text-2xl font-bold text-gray-900">{{ $settings['sync_batch_size'] ?? 100 }} records</dd>
                </div>
                <div class="bg-gray-50 p-4 rounded-md text-center">
                    <dt class="text-sm font-medium text-gray-500">Backup Retention</dt>
                    <dd class="text-2xl font-bold text-gray-900">{{ $settings['backup_retention_days'] ?? 30 }} days</dd>
                </div>
                <div class="bg-gray-50 p-4 rounded-md text-center">
                    <dt class="text-sm font-medium text-gray-500">Tracked / Blocked Keys</dt>
                    <dd class="text-2xl font-bold text-gray-900">{{ count($trackedKeys ?? []) }} / {{ count($blockedKeys ?? []) }}</dd>
                </div>
            </div>

            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                <p class="text-sm text-yellow-700">
                    <strong>Note:</strong> Changes to rate limits take effect immediately after saving.
                </p>
            </div>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Currently Blocked Keys</h3>
        </div>
        <div class="p-6">
            @if(count($blockedKeys ?? []) === 0)
                <p class="text-sm text-gray-600">No tracked rate-limit entries are currently blocked.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Profile</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actor</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">IP</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Retry In</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($blockedKeys as $blockedKey)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $blockedKey['label'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $blockedKey['email'] ?? ('User #'.($blockedKey['user_id'] ?? 'Unknown')) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $blockedKey['ip'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $blockedKey['available_in'] }}s</td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('admin.rate-limits.unblock') }}">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="key" value="{{ $blockedKey['key'] }}">
                                            <button type="submit" class="inline-flex items-center rounded-md bg-green-600 px-3 py-2 text-xs font-medium text-white hover:bg-green-700">
                                                Unblock
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('type').addEventListener('change', function() {
        const identifierField = document.getElementById('identifier-field');
        if (this.value === 'all') {
            identifierField.style.display = 'none';
        } else {
            identifierField.style.display = 'block';
        }
    });
</script>
@endpush
