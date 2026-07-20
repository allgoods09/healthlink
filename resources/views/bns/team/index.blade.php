@extends('layouts.portal')

@section('title', 'BHW Team - HealthLink')
@section('header', 'BHW Team')
@section('subheader', 'Approve new BHW registrations, assign puroks, reset passwords, and monitor account readiness for your barangay.')

@section('actions')
    <a href="{{ route('bns.team.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        Add BHW
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.team.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Name or email" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
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

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('bns.team.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">BHW</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Assignment</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Approval</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Joined</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($bhws as $bhw)
                        <tr class="{{ $bhw->approval_status === \App\Models\User::APPROVAL_PENDING ? 'bg-amber-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $bhw->name }}</p>
                                <p class="text-sm text-slate-500">{{ $bhw->email }}</p>
                                <p class="mt-1 text-xs text-slate-400">Registered via {{ $bhw->registered_via_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ $bhw->assignedPurok?->display_name ?? 'Not yet assigned' }}</p>
                                @if($bhw->requestedPurok && (! $bhw->assignedPurok || $bhw->requested_purok_id !== $bhw->assigned_purok_id))
                                    <p class="mt-1 text-xs text-amber-700">Requested: {{ $bhw->requestedPurok->display_name }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $bhw->approval_status === \App\Models\User::APPROVAL_APPROVED ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $bhw->approval_status === \App\Models\User::APPROVAL_PENDING ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $bhw->approval_status === \App\Models\User::APPROVAL_REJECTED ? 'bg-rose-100 text-rose-800' : '' }}">
                                    {{ $bhw->approval_status_label }}
                                </span>
                                @if($bhw->approval_notes)
                                    <p class="mt-2 max-w-xs text-xs text-slate-500">{{ $bhw->approval_notes }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $bhw->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $bhw->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $bhw->created_at->format('M d, Y') }}</td>
                            <td class="table-actions-cell px-6 py-4 text-right text-sm font-medium">
                                <div class="table-actions">
                                    <a href="{{ route('bns.team.show', $bhw) }}" class="text-tubigon hover:text-tubigon-hover">View</a>
                                    <a href="{{ route('bns.team.edit', $bhw) }}" class="text-indigo-600 hover:text-indigo-800">Manage</a>
                                    <a href="{{ route('bns.team.password.edit', $bhw) }}" class="text-amber-700 hover:text-amber-900">Password</a>

                                    @if($bhw->approval_status === \App\Models\User::APPROVAL_PENDING)
                                        <form action="{{ route('bns.team.approve', $bhw) }}" method="POST" class="inline" onsubmit="return confirm('Approve this BHW registration?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-emerald-600 hover:text-emerald-800">Approve</button>
                                        </form>

                                        <form action="{{ route('bns.team.reject', $bhw) }}" method="POST" class="inline" onsubmit="return captureRejectionReason(this, '{{ addslashes($bhw->name) }}')">
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
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No BHW accounts matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $bhws->links() }}
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
