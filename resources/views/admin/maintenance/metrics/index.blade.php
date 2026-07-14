@extends('layouts.admin')

@section('title', 'System Metrics - HealthLink Admin')
@section('header', 'System Health & Metrics')

@section('content')
    <div class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
        <p class="text-sm leading-6 text-blue-900">
            These metrics now adapt to the active deployment environment. If a driver or host does not expose a specific metric, HealthLink will mark it as unavailable instead of breaking the page.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                <h3 class="text-lg font-medium text-slate-900">Database</h3>
            </div>
            <div class="space-y-6 p-6">
                <dl class="grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Connection Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ ($dbMetrics['connected'] ?? false) ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                {{ ($dbMetrics['connected'] ?? false) ? 'Connected' : 'Unavailable' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Driver</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ strtoupper($dbMetrics['driver'] ?? 'unknown') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Connection</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $dbMetrics['connection_name'] ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Database</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $dbMetrics['database_name'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-slate-500">Total Size</dt>
                        <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ $dbMetrics['total_size_label'] ?? 'Unavailable' }}</dd>
                    </div>
                </dl>

                @if(! empty($dbMetrics['notes']))
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Database Notes</p>
                        <ul class="mt-2 space-y-1 text-sm text-amber-900">
                            @foreach($dbMetrics['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-medium text-slate-700">Table Metrics</h4>
                        <p class="text-xs text-slate-500">
                            {{ ($dbMetrics['supports_table_sizes'] ?? false) ? 'Includes per-table storage sizes' : 'Row counts only where supported' }}
                        </p>
                    </div>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Table</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Rows</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Size</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($dbMetrics['tables'] ?? [] as $table)
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-slate-900">{{ $table['table_name'] ?? 'Unknown' }}</td>
                                        <td class="px-3 py-2 text-right text-sm text-slate-600">
                                            {{ is_null($table['row_count'] ?? null) ? 'Unavailable' : number_format($table['row_count']) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-sm text-slate-600">
                                            {{ $table['size_label'] ?? 'Unavailable' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-3 py-4 text-center text-sm text-slate-500">
                                            No table telemetry is available for this connection yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                <h3 class="text-lg font-medium text-slate-900">Storage</h3>
            </div>
            <div class="space-y-6 p-6">
                <dl class="grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Storage Root</dt>
                        <dd class="mt-1 break-all text-sm text-slate-900">{{ $storageMetrics['storage_root'] ?? storage_path() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Disk Stats</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ ($storageMetrics['disk_stats_available'] ?? false) ? 'Available' : 'Unavailable' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Total Space</dt>
                        <dd class="mt-1 text-xl font-semibold text-slate-900">{{ $storageMetrics['total_label'] ?? 'Unavailable' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Free Space</dt>
                        <dd class="mt-1 text-xl font-semibold text-slate-900">{{ $storageMetrics['free_label'] ?? 'Unavailable' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-slate-500">Used Space</dt>
                        <dd class="mt-1 text-xl font-semibold text-slate-900">{{ $storageMetrics['used_label'] ?? 'Unavailable' }}</dd>
                    </div>
                </dl>

                @if(! is_null($storageMetrics['used_percentage'] ?? null))
                    <div>
                        <div class="h-3 w-full rounded-full bg-slate-100">
                            <div
                                class="h-3 rounded-full bg-tubigon transition-all duration-300"
                                style="width: {{ min((float) ($storageMetrics['used_percentage'] ?? 0), 100) }}%"
                            ></div>
                        </div>
                        <p class="mt-2 text-right text-xs text-slate-500">{{ $storageMetrics['used_percentage'] }}% used</p>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Backups</dt>
                        <dd class="mt-2 text-lg font-semibold text-slate-900">{{ $storageMetrics['backup_size_label'] ?? 'Unavailable' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Logs</dt>
                        <dd class="mt-2 text-lg font-semibold text-slate-900">{{ $storageMetrics['log_size_label'] ?? 'Unavailable' }}</dd>
                    </div>
                </div>

                @if(! empty($storageMetrics['notes']))
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Storage Notes</p>
                        <ul class="mt-2 space-y-1 text-sm text-amber-900">
                            @foreach($storageMetrics['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                <h3 class="text-lg font-medium text-slate-900">System Information</h3>
            </div>
            <div class="space-y-4 p-6">
                <dl class="space-y-3">
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">PHP Version</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['php_version'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Laravel Version</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['laravel_version'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Environment</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['environment'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Debug Mode</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['debug_mode'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Hostname</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['hostname'] ?? 'Unavailable' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Server Software</dt>
                        <dd class="text-sm text-right text-slate-900">{{ $systemMetrics['server_software'] ?? 'Unavailable' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Server Time</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['server_time'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Timezone</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['timezone'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">DB Driver</dt>
                        <dd class="text-sm text-slate-900">{{ strtoupper($systemMetrics['database_driver'] ?? 'unknown') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Session Driver</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['session_driver'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Cache Driver</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['cache_driver'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Queue Connection</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['queue_connection'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Memory Limit</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['memory_limit'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Max Execution Time</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['max_execution_time'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Upload Max Filesize</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['upload_max_filesize'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">POST Max Size</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['post_max_size'] ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm font-medium text-slate-500">Server Load</dt>
                        <dd class="text-sm text-slate-900">{{ $systemMetrics['server_load_label'] ?? 'Unavailable' }}</dd>
                    </div>
                </dl>

                @if(! empty($systemMetrics['notes']))
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">System Notes</p>
                        <ul class="mt-2 space-y-1 text-sm text-amber-900">
                            @foreach($systemMetrics['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                <h3 class="text-lg font-medium text-slate-900">User Statistics</h3>
            </div>
            <div class="space-y-6 p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-slate-50 p-4 text-center">
                        <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Total</dt>
                        <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($userMetrics['total'] ?? 0) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 text-center">
                        <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Active</dt>
                        <dd class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($userMetrics['active'] ?? 0) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 text-center">
                        <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Inactive</dt>
                        <dd class="mt-2 text-2xl font-semibold text-rose-700">{{ number_format($userMetrics['inactive'] ?? 0) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 text-center">
                        <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Online</dt>
                        <dd class="mt-2 text-2xl font-semibold text-tubigon">
                            {{ is_null($userMetrics['online'] ?? null) ? 'N/A' : number_format($userMetrics['online']) }}
                        </dd>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-slate-700">By Role</h4>
                    <div class="mt-3 space-y-3">
                        @foreach($userMetrics['by_role'] ?? [] as $role => $count)
                            @php
                                $total = max((int) ($userMetrics['total'] ?? 0), 1);
                                $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                            @endphp
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-slate-600">{{ \App\Models\User::ROLES[$role] ?? ucfirst($role) }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium text-slate-900">{{ number_format($count) }}</span>
                                    <div class="h-2 w-32 rounded-full bg-slate-100">
                                        <div class="h-2 rounded-full bg-tubigon" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if(! empty($userMetrics['notes']))
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">User Notes</p>
                        <ul class="mt-2 space-y-1 text-sm text-amber-900">
                            @foreach($userMetrics['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
