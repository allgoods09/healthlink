@extends('layouts.admin')

@section('title', 'System Metrics - HealthLink Admin')
@section('header', 'System Health & Metrics')

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Database Metrics -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Database</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Connection Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Connected
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Total Size</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $dbMetrics['total_size_mb'] ?? 0 }} MB</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Connection</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dbMetrics['connection_name'] ?? 'Unknown' }}</dd>
                    </div>
                </dl>

                <h4 class="mt-6 text-sm font-medium text-gray-700">Table Sizes</h4>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Table</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Rows</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Size (MB)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($dbMetrics['tables'] ?? [] as $table)
                                <tr>
                                    <td class="px-3 py-2 text-sm text-gray-900">{{ $table->table_name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-500 text-right">{{ number_format($table->row_count ?? 0) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-500 text-right">{{ $table->size_mb ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Storage Metrics -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Storage</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Total Space</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $storageMetrics['total_gb'] ?? 0 }} GB</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Used Space</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $storageMetrics['used_gb'] ?? 0 }} GB ({{ $storageMetrics['used_percentage'] ?? 0 }}%)</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Free Space</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $storageMetrics['free_gb'] ?? 0 }} GB</dd>
                    </div>
                </dl>

                <!-- Storage Bar -->
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="bg-blue-600 h-4 rounded-full transition-all duration-500" 
                             style="width: {{ $storageMetrics['used_percentage'] ?? 0 }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 text-right">
                        {{ $storageMetrics['used_percentage'] ?? 0 }}% used
                    </p>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-3 rounded-md">
                        <dt class="text-xs font-medium text-gray-500">Backups</dt>
                        <dd class="text-lg font-bold text-gray-900">{{ $storageMetrics['backup_size_mb'] ?? 0 }} MB</dd>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-md">
                        <dt class="text-xs font-medium text-gray-500">Logs</dt>
                        <dd class="text-lg font-bold text-gray-900">{{ $storageMetrics['log_size_mb'] ?? 0 }} MB</dd>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="bg-white rounded-lg shadow overflow-hidden lg:col-span-1">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">System Information</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">PHP Version</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['php_version'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Laravel Version</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['laravel_version'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Environment</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['environment'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Debug Mode</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['debug_mode'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Server Time</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['server_time'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Timezone</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['timezone'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Memory Limit</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['memory_limit'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Max Execution Time</dt>
                        <dd class="text-sm text-gray-900">{{ $systemMetrics['max_execution_time'] ?? 'Unknown' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- User Metrics -->
        <div class="bg-white rounded-lg shadow overflow-hidden lg:col-span-1">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">User Statistics</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-gray-50 p-3 rounded-md text-center">
                        <dt class="text-xs font-medium text-gray-500">Total</dt>
                        <dd class="text-2xl font-bold text-gray-900">{{ $userMetrics['total'] ?? 0 }}</dd>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-md text-center">
                        <dt class="text-xs font-medium text-gray-500">Active</dt>
                        <dd class="text-2xl font-bold text-green-600">{{ $userMetrics['active'] ?? 0 }}</dd>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-md text-center">
                        <dt class="text-xs font-medium text-gray-500">Inactive</dt>
                        <dd class="text-2xl font-bold text-red-600">{{ $userMetrics['inactive'] ?? 0 }}</dd>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-md text-center">
                        <dt class="text-xs font-medium text-gray-500">Online</dt>
                        <dd class="text-2xl font-bold text-blue-600">{{ $userMetrics['online'] ?? 0 }}</dd>
                    </div>
                </div>

                <h4 class="text-sm font-medium text-gray-700 mb-2">By Role</h4>
                <div class="space-y-2">
                    @foreach($userMetrics['by_role'] ?? [] as $role => $count)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">{{ ucfirst($role) }}</span>
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-900 mr-2">{{ $count }}</span>
                                <div class="w-32 bg-gray-200 rounded-full h-2">
                                    @php
                                        $total = $userMetrics['total'] ?? 1;
                                        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                                    @endphp
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection