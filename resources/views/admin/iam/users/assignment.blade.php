@extends('layouts.admin')

@section('title', 'Assignment Workflow - HealthLink Admin')
@section('header', 'Assignment Workflow')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            Back to User
        </a>
        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Full Edit
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-900">
        This workspace is for assignment and approval handling. It is especially useful for self-registered BHW and BNS users who must be placed correctly before activation.
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Registration Snapshot</h2>
            </div>
            <div class="space-y-5 p-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">User</p>
                    <p class="mt-1 text-base font-semibold text-gray-900">{{ $user->name }}</p>
                    <p class="text-sm text-gray-500">{{ $user->email }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Requested Role</p>
                        <p class="mt-1 text-sm text-gray-900">{{ \App\Models\User::ROLES[$user->requested_role ?? $user->role] ?? ($user->requested_role ?? $user->role) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Approval Queue</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->approval_queue_label }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Requested Barangay</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->requestedBarangay?->name ?? 'No barangay requested' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Requested Purok</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->requestedPurok?->display_name ?? 'No purok requested' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Email Verification</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->email_verification_status_label }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Current Approval</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->approval_status_label }}</p>
                    </div>
                </div>

                @if($user->approval_status === \App\Models\User::APPROVAL_PENDING)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        This account is still pending. Save the assignment first, then approve when the placement looks correct.
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Assignment Controls</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" x-data="assignmentForm()">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="name" value="{{ old('name', $user->name) }}">
                    <input type="hidden" name="email" value="{{ old('email', $user->email) }}">

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="role" class="block text-sm font-medium text-gray-700">Role to Activate</label>
                            <select
                                name="role"
                                id="role"
                                x-model="role"
                                @change="updateAssignments()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('role') border-red-500 @enderror"
                                required
                            >
                                @foreach($roles as $key => $label)
                                    <option value="{{ $key }}" @selected(old('role', $user->requested_role ?? $user->role) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="showBarangay()">
                            <label for="assigned_barangay_id" class="block text-sm font-medium text-gray-700">Assigned Barangay</label>
                            <select
                                name="assigned_barangay_id"
                                id="assigned_barangay_id"
                                x-model="barangayId"
                                @change="loadPuroks()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('assigned_barangay_id') border-red-500 @enderror"
                            >
                                <option value="">Select barangay</option>
                                @foreach($barangays as $barangay)
                                    <option value="{{ $barangay->id }}" @selected((string) old('assigned_barangay_id', $user->assigned_barangay_id ?? $user->requested_barangay_id) === (string) $barangay->id)>{{ $barangay->name }}</option>
                                @endforeach
                            </select>
                            @error('assigned_barangay_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="showPurok()">
                            <label for="assigned_purok_id" class="block text-sm font-medium text-gray-700">Assigned Purok</label>
                            <select
                                name="assigned_purok_id"
                                id="assigned_purok_id"
                                x-model="purokId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('assigned_purok_id') border-red-500 @enderror"
                            >
                                <option value="">Select purok</option>
                                <template x-for="purok in puroks" :key="purok.id">
                                    <option :value="String(purok.id)" x-text="purok.purok_name ? `Purok ${purok.purok_number} - ${purok.purok_name}` : `Purok ${purok.purok_number}`"></option>
                                </template>
                            </select>
                            @error('assigned_purok_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Saving the assignment does not approve the registration. Use the approval button below when the placement is ready.
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Save Assignment
                        </button>
                    </div>
                </form>

                @if($user->approval_status === \App\Models\User::APPROVAL_PENDING)
                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <form action="{{ route('admin.users.approve', $user) }}" method="POST" onsubmit="return confirm('Approve this registration after reviewing the assignment?')">
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
                    </div>
                @endif
            </div>
        </section>
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

        function assignmentForm() {
            return {
                role: '{{ old('role', $user->requested_role ?? $user->role) }}',
                barangayId: '{{ old('assigned_barangay_id', $user->assigned_barangay_id ?? $user->requested_barangay_id) }}',
                purokId: '{{ old('assigned_purok_id', $user->assigned_purok_id ?? $user->requested_purok_id) }}',
                puroks: @json($puroks),
                init() {
                    if (this.barangayId && this.showBarangay() && this.puroks.length === 0) {
                        this.loadPuroks();
                    }
                },

                showBarangay() {
                    return ['secretary', 'bns', 'bhw'].includes(this.role);
                },

                showPurok() {
                    return this.role === 'bhw';
                },

                updateAssignments() {
                    if (!this.showBarangay()) {
                        this.barangayId = '';
                        this.puroks = [];
                        this.purokId = '';
                        return;
                    }

                    if (!this.showPurok()) {
                        this.purokId = '';
                        return;
                    }

                    if (this.barangayId && this.puroks.length === 0) {
                        this.loadPuroks();
                    }
                },

                async loadPuroks() {
                    if (!this.barangayId) {
                        this.puroks = [];
                        this.purokId = '';
                        return;
                    }

                    try {
                        const response = await fetch(`{{ route('admin.users.get-puroks') }}?barangay_id=${this.barangayId}`);
                        const data = await response.json();
                        this.puroks = data;

                        if (!this.puroks.find((purok) => String(purok.id) === String(this.purokId))) {
                            this.purokId = '';
                        }
                    } catch (error) {
                        console.error('Error loading puroks:', error);
                        this.puroks = [];
                    }
                },
            };
        }
    </script>
@endpush
