@extends('layouts.admin')

@section('title', 'User Details - HealthLink Admin')
@section('header', 'User Details')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            Back
        </a>
        <a href="{{ route('admin.users.assignment', $user) }}" class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            Manage Assignment
        </a>
        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Edit User
        </a>
        <a href="{{ route('admin.users.password.edit', $user) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Reset Password
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">User Information</h3>
                </div>
                <div class="p-6">
                    <div class="mb-6 flex justify-center">
                        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-600">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                    </div>

                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email Verification</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->hasVerifiedEmail() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $user->email_verification_status_label }}
                                </span>
                                @if($user->hasVerifiedEmail())
                                    <div class="mt-1 text-xs text-gray-500">{{ $user->email_verified_at?->format('F d, Y h:i A') }}</div>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Role</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->role_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Assignment</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->assignment_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Registration Source</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->registered_via_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Approval Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $user->approval_status === \App\Models\User::APPROVAL_APPROVED ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $user->approval_status === \App\Models\User::APPROVAL_PENDING ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $user->approval_status === \App\Models\User::APPROVAL_REJECTED ? 'bg-rose-100 text-rose-800' : '' }}">
                                    {{ $user->approval_status_label }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Joined</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('F d, Y h:i A') }}</dd>
                        </div>
                        @if($user->deleted_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Deleted At</dt>
                                <dd class="mt-1 text-sm text-rose-600">{{ $user->deleted_at->format('F d, Y h:i A') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Approval Workflow</h3>
                </div>
                <div class="space-y-4 p-6">
                    @if($user->approval_status === \App\Models\User::APPROVAL_PENDING)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            This account was self-registered and is waiting for approval. Use the assignment workflow first if the barangay or purok still needs to be finalized.
                        </div>
                    @endif

                    <div class="rounded-lg border {{ $user->hasVerifiedEmail() ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} p-4 text-sm">
                        @if($user->hasVerifiedEmail())
                            This email address is already verified and can be used for password recovery and mobile access.
                        @else
                            This email address is still unverified. Self-registered users must verify their email before mobile access will be granted.
                        @endif
                    </div>

                    @if($user->requested_role)
                        <div>
                            <p class="text-sm font-medium text-gray-500">Requested Role</p>
                            <p class="mt-1 text-sm text-gray-900">{{ \App\Models\User::ROLES[$user->requested_role] ?? $user->requested_role }}</p>
                        </div>
                    @endif

                    @if($user->requestedBarangay)
                        <div>
                            <p class="text-sm font-medium text-gray-500">Requested Assignment</p>
                            <p class="mt-1 text-sm text-gray-900">
                                {{ $user->requestedBarangay->name }}
                                @if($user->requestedPurok)
                                    / {{ $user->requestedPurok->display_name }}
                                @endif
                            </p>
                        </div>
                    @endif

                    @if($user->approved_at)
                        <div>
                            <p class="text-sm font-medium text-gray-500">Approved At</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $user->approved_at->format('F d, Y h:i A') }}</p>
                        </div>
                    @endif

                    @if($user->rejected_at)
                        <div>
                            <p class="text-sm font-medium text-gray-500">Rejected At</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $user->rejected_at->format('F d, Y h:i A') }}</p>
                        </div>
                    @endif

                    @if($user->approval_notes)
                        <div>
                            <p class="text-sm font-medium text-gray-500">Approval Notes</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $user->approval_notes }}</p>
                        </div>
                    @endif

                    @if($user->approval_status === \App\Models\User::APPROVAL_PENDING)
                        <div class="pt-2">
                            <a href="{{ route('admin.users.assignment', $user) }}" class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100">
                                Open Assignment Workflow
                            </a>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <form action="{{ route('admin.users.approve', $user) }}" method="POST" onsubmit="return confirm('Approve this registration?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                    Approve Registration
                                </button>
                            </form>

                            <form action="{{ route('admin.users.reject', $user) }}" method="POST" onsubmit="return captureRejectionReason(this, '{{ addslashes($user->name) }}')">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="approval_notes" value="">
                                <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                                    Reject Registration
                                </button>
                            </form>
                        </div>
                    @endif

                    @unless($user->hasVerifiedEmail())
                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <form action="{{ route('admin.users.verification.resend', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                    Resend Verification Email
                                </button>
                            </form>

                            <form action="{{ route('admin.users.verification.mark', $user) }}" method="POST" onsubmit="return confirm('Mark this email as verified manually?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                                    Mark as Verified
                                </button>
                            </form>
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Assignment Details</h3>
                </div>
                <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Assigned Barangay</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->assignedBarangay?->name ?? 'None assigned' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Assigned Purok</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->assignedPurok?->display_name ?? 'None assigned' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Requested Barangay</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->requestedBarangay?->name ?? 'No self-registration request' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Requested Purok</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->requestedPurok?->display_name ?? 'No self-registration request' }}</p>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Recent Activity</h3>
                </div>
                <div class="p-6">
                    @if($user->auditLogs->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($user->auditLogs as $log)
                                <li class="py-3 first:pt-0 last:pb-0">
                                    <div class="flex items-center gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900">{{ $log->event_description }}</p>
                                            <p class="text-sm text-gray-500">{{ $log->event_type_label }} · {{ $log->created_at->diffForHumans() }}</p>
                                        </div>
                                        <a href="{{ route('admin.audit.show', $log) }}" class="text-sm text-blue-600 hover:text-blue-900">View Log</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">No activity recorded.</p>
                    @endif
                </div>
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
