@extends('layouts.admin')

@section('title', 'Backups - HealthLink Admin')
@section('header', 'Backup Management')

@section('actions')
    {{-- <div class="flex items-center space-x-2">
        <a href="#backup-create" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Generate Backup
        </a>
        <form action="{{ route('admin.backups.delete-expired') }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete all expired backups?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center rounded-md bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700">
                Delete Expired
            </button>
        </form>
    </div> --}}
@endsection

@section('content')
    @if(! empty($capabilities['issues']))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">Backup Tooling Needs Attention</p>
            <ul class="mt-2 space-y-1 text-sm text-amber-800">
                @foreach($capabilities['issues'] as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Total Backups</dt>
            <dd class="mt-2 text-2xl font-bold text-gray-900">{{ $summary['total'] ?? 0 }}</dd>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Completed</dt>
            <dd class="mt-2 text-2xl font-bold text-emerald-700">{{ $summary['completed'] ?? 0 }}</dd>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Verified</dt>
            <dd class="mt-2 text-2xl font-bold text-blue-700">{{ $summary['verified'] ?? 0 }}</dd>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Restored</dt>
            <dd class="mt-2 text-2xl font-bold text-indigo-700">{{ $summary['restored'] ?? 0 }}</dd>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <dt class="text-sm font-medium text-gray-500">Failed</dt>
            <dd class="mt-2 text-2xl font-bold text-rose-700">{{ $summary['failed'] ?? 0 }}</dd>
        </div>
    </div>

    <div id="backup-create" class="mb-6 rounded-lg bg-white shadow">
        <div class="border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-medium text-gray-900">Generate New Backup</h3>
            <p class="mt-1 text-sm text-gray-600">
                Driver: <span class="font-medium text-gray-900">{{ $capabilities['driver'] }}</span>.
                Storage: <span class="font-medium text-gray-900">{{ $capabilities['storage_directory'] }}</span>.
            </p>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('admin.backups.generate') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @csrf
                <div>
                    <label for="backup_type" class="block text-sm font-medium text-gray-700">Backup Type</label>
                    <select name="backup_type" id="backup_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        @foreach($backupTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('backup_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Use a full backup before major releases or destructive maintenance.</p>
                </div>
                <div class="lg:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700">Operational Notes</label>
                    <input type="text" name="notes" id="notes" value="{{ old('notes') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                           placeholder="Example: Pre-release snapshot before role rollout">
                    <p class="mt-1 text-xs text-gray-500">These notes are saved with the backup and shown in the recovery view.</p>
                </div>
                <div class="lg:col-span-3">
                    <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Generate Backup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white shadow">
        <div class="p-4">
            <form method="GET" action="{{ route('admin.backups.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                           placeholder="Filename...">
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                    <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Types</option>
                        @foreach($backupTypes as $key => $label)
                            <option value="{{ $key }}" {{ request('type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ str_replace('_', ' ', ucfirst($status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end space-x-2">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.backups.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Backup</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Integrity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Recovery</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Expires</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($backups as $backup)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="font-medium">{{ $backup->filename }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $backup->backup_type_label }} by {{ $backup->generator?->name ?? 'Unknown' }}
                                </div>
                                @if($backup->notes)
                                    <div class="mt-1 text-xs text-gray-500">{{ $backup->notes }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{!! $backup->integrity_badge !!}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $backup->last_verified_at?->format('Y-m-d H:i') ?? 'Not checked yet' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $backup->formatted_file_size }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {!! $backup->status_badge !!}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div>Restore count: {{ $backup->restore_count }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $backup->last_restored_at?->format('Y-m-d H:i') ?? 'Not restored yet' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($backup->expires_at)
                                    {{ $backup->expires_at->format('Y-m-d') }}
                                    @if($backup->is_expired)
                                        <span class="ml-1 text-rose-600">(Expired)</span>
                                    @endif
                                @else
                                    Never
                                @endif
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('admin.backups.show', $backup) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                    @if($backup->is_verifiable)
                                        <form action="{{ route('admin.backups.verify', $backup) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-900">Verify</button>
                                        </form>
                                    @endif
                                    @if($backup->status === 'completed')
                                        <a href="{{ route('admin.backups.download', $backup) }}" class="text-emerald-600 hover:text-emerald-900">Download</a>
                                    @endif
                                    <form action="{{ route('admin.backups.destroy', $backup) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this backup?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-900">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                No backups found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $backups->links() }}
        </div>
    </div>
@endsection
