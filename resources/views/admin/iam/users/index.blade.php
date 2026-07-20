@extends('layouts.admin')

@section('title', 'Users - HealthLink Admin')
@section('header', 'User Management')

@section('actions')
    <a href="{{ route('admin.users.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        Add User
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 px-4 py-4">
        <p class="text-sm text-blue-900">
            Pending registrations are now separated by their real approval owner. BHW and BNS requests normally wait for the assigned barangay secretary, while secretary, PHN, and MHO accounts stay in the municipal admin queue.
        </p>
    </div>

    <div class="mb-6 grid gap-4 xl:grid-cols-2">
        @foreach($approvalQueues as $queueKey => $queue)
            <section class="rounded-lg bg-white p-5 shadow">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">{{ $queue['label'] }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $queue['description'] }}</p>
                    </div>
                    <div class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800">
                        {{ number_format($queue['count']) }}
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <span>Oldest request: {{ $queue['oldest']?->created_at?->diffForHumans() ?? 'None pending' }}</span>
                    <a href="{{ route('admin.users.index', array_merge(request()->except('page'), ['approval_status' => \App\Models\User::APPROVAL_PENDING, 'approval_queue' => $queueKey])) }}" class="font-medium text-blue-700 hover:text-blue-900">
                        Open filtered queue
                    </a>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($queue['users'] as $queuedUser)
                        <div class="rounded-lg border border-gray-100 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $queuedUser->name }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $queuedUser->email }}</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ \App\Models\User::ROLES[$queuedUser->requested_role ?? $queuedUser->role] ?? ($queuedUser->requested_role ?? $queuedUser->role) }}
                                        ·
                                        {{ $queuedUser->requestedBarangay?->name ?? $queuedUser->assignedBarangay?->name ?? 'No barangay selected' }}
                                        @if($queuedUser->requestedPurok)
                                            / {{ $queuedUser->requestedPurok->display_name }}
                                        @endif
                                    </p>
                                </div>
                                <a href="{{ route('admin.users.show', $queuedUser) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900">Review</a>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500">
                            No pending registrations are in this queue right now.
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-700">Filters</h2>
            </div>
            <div class="p-5">
                <form method="GET" action="{{ route('admin.users.index') }}" class="space-y-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name or email">
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All roles</option>
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}" {{ request('role') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="approval_status" class="block text-sm font-medium text-gray-700">Approval</label>
                        <select name="approval_status" id="approval_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All approvals</option>
                            @foreach($approvalStatuses as $key => $label)
                                <option value="{{ $key }}" {{ request('approval_status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="approval_queue" class="block text-sm font-medium text-gray-700">Approval Queue</label>
                        <select name="approval_queue" id="approval_queue" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All queues</option>
                            @foreach($approvalQueueOptions as $key => $label)
                                <option value="{{ $key }}" {{ request('approval_queue') === $key ? 'selected' : '' }}>{{ $label }}</option>
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
                        <label for="barangay" class="block text-sm font-medium text-gray-700">Barangay</label>
                        <select name="barangay" id="barangay" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All barangays</option>
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay->id }}" {{ (string) request('barangay') === (string) $barangay->id ? 'selected' : '' }}>
                                    {{ $barangay->name }}
                                </option>
                            @endforeach
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

                    <div class="flex flex-wrap gap-2 pt-2">
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Apply Filters
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </aside>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assignment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Approval</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Joined</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($users as $user)
                        <tr class="{{ $user->trashed() ? 'bg-gray-50' : '' }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 font-semibold text-gray-700">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ $user->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                        <div class="text-xs text-gray-400">Registered via {{ $user->registered_via_label }}</div>
                                        <div class="mt-1">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $user->hasVerifiedEmail() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                Email {{ $user->email_verification_status_label }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div class="font-medium text-gray-900">{{ $user->role_label }}</div>
                                @if($user->requested_role && $user->requested_role !== $user->role)
                                    <div class="text-xs text-amber-700">Requested: {{ \App\Models\User::ROLES[$user->requested_role] ?? $user->requested_role }}</div>
                                @endif
                                @if($user->approval_status === \App\Models\User::APPROVAL_PENDING)
                                    <div class="mt-1 inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                        {{ $user->approval_queue_label }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div>{{ $user->assignment_label }}</div>
                                @if($user->approval_status === \App\Models\User::APPROVAL_PENDING && $user->requestedBarangay)
                                    <div class="text-xs text-amber-700">
                                        Requested:
                                        {{ $user->requestedBarangay->name }}
                                        @if($user->requestedPurok)
                                            / {{ $user->requestedPurok->display_name }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $user->approval_status === \App\Models\User::APPROVAL_APPROVED ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $user->approval_status === \App\Models\User::APPROVAL_PENDING ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $user->approval_status === \App\Models\User::APPROVAL_REJECTED ? 'bg-rose-100 text-rose-800' : '' }}">
                                    {{ $user->approval_status_label }}
                                </span>
                                @if($user->approval_notes)
                                    <div class="mt-1 max-w-xs text-xs text-gray-500">{{ $user->approval_notes }}</div>
                                @endif
                                @if($user->approval_status === \App\Models\User::APPROVAL_PENDING && $user->approval_queue === 'secretary')
                                    <div class="mt-1 max-w-xs text-xs text-blue-700">Normal owner: assigned barangay secretary. Admin approval here acts as a supervisory override.</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $user->trashed() ? 'Deleted' : 'Current' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $user->created_at->format('M d, Y') }}
                                @if($user->deleted_at)
                                    <div class="text-xs text-rose-600">Deleted {{ $user->deleted_at->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 hover:text-blue-900">View</a>

                                    @if($user->trashed())
                                        <form action="{{ route('admin.users.restore', $user) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-green-600 hover:text-green-900">Restore</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.users.assignment', $user) }}" class="text-emerald-600 hover:text-emerald-900">Assign</a>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>

                                        @if($user->approval_status === \App\Models\User::APPROVAL_PENDING)
                                            <form action="{{ route('admin.users.approve', $user) }}" method="POST" class="inline" onsubmit="return confirm('Approve this self-registration?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-emerald-600 hover:text-emerald-900">Approve</button>
                                            </form>

                                            <form action="{{ route('admin.users.reject', $user) }}" method="POST" class="inline" onsubmit="return captureRejectionReason(this, '{{ addslashes($user->name) }}')">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="approval_notes" value="">
                                                <button type="submit" class="text-rose-600 hover:text-rose-900">Reject</button>
                                            </form>
                                        @endif

                                        <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="{{ $user->is_active ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}">
                                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user account?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function captureRejectionReason(form, userName) {
            const reason = window.prompt(`Enter a rejection note for ${userName}:`);

            if (!reason) {
                return false;
            }

            form.querySelector('input[name="approval_notes"]').value = reason;

            return true;
        }
    </script>
@endpush
