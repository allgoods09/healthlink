@extends('layouts.portal')

@section('title', 'Frontline Team - HealthLink')
@section('header', 'Frontline Team')
@section('subheader', 'Approve BHW and BNS self-registrations, create frontline accounts directly, assign puroks, and keep local access scoped to your barangay.')

@section('actions')
    <a href="{{ route('secretary.team.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        Add Frontline User
    </a>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[300px_minmax(0,1fr)]">
        <aside class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-tubigon">Filters</h2>
            </div>
            <div class="p-5">
                <form method="GET" action="{{ route('secretary.team.index') }}" class="space-y-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Name or email" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-slate-700">Role</label>
                        <select name="role" id="role" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All roles</option>
                            <option value="bhw" {{ request('role') === 'bhw' ? 'selected' : '' }}>BHW</option>
                            <option value="bns" {{ request('role') === 'bns' ? 'selected' : '' }}>BNS</option>
                        </select>
                    </div>

                    <div>
                        <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                        <select name="purok_id" id="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All puroks</option>
                            @foreach($puroks as $purok)
                                <option value="{{ $purok->id }}" {{ (string) request('purok_id') === (string) $purok->id ? 'selected' : '' }}>
                                    {{ $purok->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="approval_status" class="block text-sm font-medium text-slate-700">Approval</label>
                        <select name="approval_status" id="approval_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All approvals</option>
                            <option value="{{ \App\Models\User::APPROVAL_PENDING }}" {{ request('approval_status') === \App\Models\User::APPROVAL_PENDING ? 'selected' : '' }}>Pending Approval</option>
                            <option value="{{ \App\Models\User::APPROVAL_APPROVED }}" {{ request('approval_status') === \App\Models\User::APPROVAL_APPROVED ? 'selected' : '' }}>Approved</option>
                            <option value="{{ \App\Models\User::APPROVAL_REJECTED }}" {{ request('approval_status') === \App\Models\User::APPROVAL_REJECTED ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            <option value="">All statuses</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                            Apply Filters
                        </button>
                        <a href="{{ route('secretary.team.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </aside>

        <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Role & Assignment</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Approval</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Joined</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($users as $frontlineUser)
                        <tr class="{{ $frontlineUser->approval_status === \App\Models\User::APPROVAL_PENDING ? 'bg-amber-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $frontlineUser->name }}</p>
                                <p class="text-sm text-slate-500">{{ $frontlineUser->email }}</p>
                                <p class="mt-1 text-xs text-slate-400">Registered via {{ $frontlineUser->registered_via_label }}</p>
                                <div class="mt-1">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $frontlineUser->hasVerifiedEmail() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        Email {{ $frontlineUser->email_verification_status_label }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p class="font-medium text-slate-900">{{ $frontlineUser->role_label }}</p>
                                <p class="mt-1">{{ $frontlineUser->assignedPurok?->display_name ?? 'Barangay-wide / not yet assigned' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $frontlineUser->approval_status === \App\Models\User::APPROVAL_APPROVED ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $frontlineUser->approval_status === \App\Models\User::APPROVAL_PENDING ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $frontlineUser->approval_status === \App\Models\User::APPROVAL_REJECTED ? 'bg-rose-100 text-rose-800' : '' }}">
                                    {{ $frontlineUser->approval_status_label }}
                                </span>
                                @if($frontlineUser->approval_notes)
                                    <p class="mt-2 max-w-xs text-xs text-slate-500">{{ $frontlineUser->approval_notes }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $frontlineUser->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $frontlineUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $frontlineUser->created_at->format('M d, Y') }}</td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('secretary.team.show', $frontlineUser) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                                    <a href="{{ route('secretary.team.edit', $frontlineUser) }}" class="text-indigo-600 hover:text-indigo-800">Manage</a>
                                    <a href="{{ route('secretary.team.password.edit', $frontlineUser) }}" class="text-amber-700 hover:text-amber-900">Password</a>

                                    @if($frontlineUser->approval_status === \App\Models\User::APPROVAL_PENDING)
                                        <form action="{{ route('secretary.team.approve', $frontlineUser) }}" method="POST" class="inline" onsubmit="return confirm('Approve this registration?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-emerald-600 hover:text-emerald-800">Approve</button>
                                        </form>

                                        <form action="{{ route('secretary.team.reject', $frontlineUser) }}" method="POST" class="inline" onsubmit="return captureRejectionReason(this, '{{ addslashes($frontlineUser->name) }}')">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="approval_notes" value="">
                                            <button type="submit" class="text-rose-600 hover:text-rose-800">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No frontline users matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div class="border-t border-slate-200 px-6 py-4">
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
