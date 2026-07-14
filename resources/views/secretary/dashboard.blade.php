@extends('layouts.portal')

@section('title', 'Secretary Dashboard - HealthLink')
@section('header', 'Secretary Dashboard')
@section('subheader', 'Civil registry oversight, frontline approvals, draft verification readiness, and scoped barangay activity for your assigned records.')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
        <section class="overflow-hidden rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover text-white shadow-xl shadow-tubigon/20">
            <div class="grid gap-6 px-6 py-8 sm:px-8 lg:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/65">Barangay Records</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ auth()->user()->assignedBarangay?->name ?? auth()->user()->assignment_label }}</h2>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-white/85">
                        This workspace keeps the barangay civil registry clean: residents stay profiled, households stay clustered, frontline users stay approved inside the right barangay, and every record change stays exportable and auditable.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('secretary.residents.create') }}" class="inline-flex items-center rounded-full bg-white px-4 py-2 text-sm font-medium text-tubigon transition hover:bg-slate-100">
                            Add Resident
                        </a>
                        <a href="{{ route('secretary.households.create') }}" class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/15">
                            Add Household
                        </a>
                        <a href="{{ route('secretary.team.index', ['approval_status' => \App\Models\User::APPROVAL_PENDING]) }}" class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/15">
                            Review Frontline Approvals
                        </a>
                        <a href="{{ route('secretary.certificates.create') }}" class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/15">
                            Issue Certificate
                        </a>
                    </div>
                </div>

                <div class="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/65">Attention Needed</p>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-2xl bg-white/8 p-4">
                            <p class="text-sm text-white/70">Pending Frontline Approvals</p>
                            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($pendingFrontlineApprovals) }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/8 p-4">
                            <p class="text-sm text-white/70">Pending Draft Packages</p>
                            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($pendingDraftPackages) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Current Population</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Active Residents</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($activeResidents) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Households</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($householdCount) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Civil Registry</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Deceased</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($deceasedResidents) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Relocated</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($relocatedResidents) }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Seniors</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($seniorCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Minors</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($minorCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Pending Update Requests</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($pendingUpdateRequests) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Pending Triage Queue</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($pendingTriageQueue) }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Purok Density Snapshot</h3>
                    <p class="text-sm text-slate-500">Population, household count, and civil status mix across your barangay.</p>
                </div>
                <a href="{{ route('secretary.reports.demographics') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Demographics</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Purok</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Households</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Active Residents</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Deceased</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Relocated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($purokDensity as $row)
                            <tr>
                                <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $row['purok']->display_name }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['households']) }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['active_residents']) }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['deceased']) }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($row['relocated']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">No purok density data is available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Quick Status</h3>
            </div>
            <div class="space-y-4 p-6 text-sm">
                <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3">
                    <p class="font-semibold text-blue-900">{{ number_format($pendingFrontlineApprovals) }} pending frontline approval(s)</p>
                    <p class="mt-1 text-blue-800">BHW and BNS self-registrations now wait here for secretary approval and assignment control.</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="font-semibold text-amber-900">{{ number_format($headlessHouseholdCount) }} household(s) need head assignment</p>
                    <p class="mt-1 text-amber-800">Useful for cleanup before certification and demographic exports.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="font-semibold text-slate-900">{{ number_format($pendingDraftPackages + $pendingUpdateRequests + $pendingTriageQueue) }} pipeline item(s) queued</p>
                    <p class="mt-1 text-slate-700">Field drafts, correction requests, and triage records are now scoped into the secretary pipeline.</p>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Frontline Approval Queue</h3>
                    <p class="text-sm text-slate-500">Pending BHW and BNS self-registrations waiting for secretary review.</p>
                </div>
                <a href="{{ route('secretary.team.index', ['approval_status' => \App\Models\User::APPROVAL_PENDING]) }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Team Queue</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($recentFrontlineRegistrations as $frontlineUser)
                    <div class="flex flex-col gap-4 px-6 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $frontlineUser->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $frontlineUser->email }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $frontlineUser->role_label }} · {{ $frontlineUser->created_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('secretary.team.edit', $frontlineUser) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-tubigon/20 hover:text-tubigon">
                            Review
                        </a>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No frontline approvals are waiting right now.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Recent Certificates</h3>
                    <p class="text-sm text-slate-500">Latest barangay clearances and indigency certificates issued from this desk.</p>
                </div>
                <a href="{{ route('secretary.certificates.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Certificate Log</a>
            </div>
            <div class="divide-y divide-slate-200">
                @forelse($recentCertificates as $certificate)
                    <div class="flex flex-col gap-4 px-6 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $certificate->certificate_no }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $certificate->certificate_type_label }} for {{ $certificate->issued_to_name }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $certificate->issued_at?->format('M d, Y h:i A') }}</p>
                        </div>
                        <a href="{{ route('secretary.certificates.show', $certificate) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-tubigon/20 hover:text-tubigon">
                            View
                        </a>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-slate-500">No certificates have been issued yet.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="mt-8 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Recent Activity</h3>
                <p class="text-sm text-slate-500">Read-only barangay audit feed across record changes and registry actions.</p>
            </div>
            <a href="{{ route('secretary.activity.index') }}" class="text-sm font-medium text-tubigon hover:text-tubigon-hover">Open Activity Feed</a>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse($recentActivity as $log)
                <div class="px-6 py-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $log->event_description }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $log->actor_name }} · {{ $log->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="px-6 py-8 text-sm text-slate-500">No scoped activity has been recorded yet.</div>
            @endforelse
        </div>
    </section>
@endsection
