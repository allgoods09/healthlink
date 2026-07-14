@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Resident Details - HealthLink Admin')
@section('header', $pageHeader ?? 'Resident Details')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        @if(\Illuminate\Support\Facades\Route::has($routePrefix.'.residents.pdf'))
            <a href="{{ route($routePrefix.'.residents.pdf', $resident) }}" class="inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                Download PDF
            </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has($routePrefix.'.residents.print'))
            <a href="{{ route($routePrefix.'.residents.print', $resident) }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                Print Form
            </a>
        @endif
        <a href="{{ route($routePrefix.'.residents.edit', $resident) }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Edit
        </a>
        @if($canRelocate ?? false)
            <a href="{{ route($routePrefix.'.residents.relocate.edit', $resident) }}" class="inline-flex items-center rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                Relocate
            </a>
        @endif
        <a href="{{ route($routePrefix.'.households.show', $resident->household) }}" class="inline-flex items-center rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
            View Household
        </a>
        <a href="{{ route($routePrefix.'.residents.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            Back
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-lg bg-white shadow xl:col-span-2">
            <div class="border-b border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900">{{ $resident->formal_name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $resident->relationship_to_head }} · {{ $resident->sex }} · Age {{ $resident->age }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if($resident->is_household_head)
                        <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">Household Head</span>
                    @endif
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $resident->resident_status_label }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Personal</h3>
                    <dl class="mt-4 space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">PhilSys ID</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->philsys_card_no ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Birth Date</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->birth_date?->format('F j, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Birth Place</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->birth_place }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Civil Status</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->civil_status }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Citizenship</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->citizenship }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Religion</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->religion ?: 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Contact and Assignment</h3>
                    <dl class="mt-4 space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Number</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->contact_number ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->email_address ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Barangay</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->household->purok->barangay->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Purok</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->household->purok->display_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Household</dt>
                            <dd class="text-sm text-gray-900">#{{ $resident->household->household_no }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->is_active ? 'Active' : 'Inactive' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Move-In Date</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->moved_in_at?->format('F j, Y') ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Move-Out Date</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->moved_out_at?->format('F j, Y') ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Date of Death</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->date_of_death?->format('F j, Y') ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status Notes</dt>
                            <dd class="text-sm text-gray-900">{{ $resident->status_notes ?: 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900">Socio-Economic Profile</h2>
            </div>
            <div class="space-y-4 p-6 text-sm">
                <div>
                    <div class="font-medium text-gray-500">Occupation</div>
                    <div class="text-gray-900">{{ $resident->socioEconomicProfile?->occupation ?: 'N/A' }}</div>
                </div>
                <div>
                    <div class="font-medium text-gray-500">Employment Status</div>
                    <div class="text-gray-900">{{ $resident->socioEconomicProfile?->employment_status ?: 'N/A' }}</div>
                </div>
                <div>
                    <div class="font-medium text-gray-500">Highest Education</div>
                    <div class="text-gray-900">{{ $resident->socioEconomicProfile?->highest_education_level ?: 'N/A' }}</div>
                </div>
                <div>
                    <div class="font-medium text-gray-500">Education Status</div>
                    <div class="text-gray-900">{{ $resident->socioEconomicProfile?->education_status ?: 'N/A' }}</div>
                </div>
                <div>
                    <div class="font-medium text-gray-500">Ethnicity</div>
                    <div class="text-gray-900">{{ $resident->socioEconomicProfile?->ethnicity ?: 'N/A' }}</div>
                </div>
                <div>
                    <div class="font-medium text-gray-500">Disability Type</div>
                    <div class="text-gray-900">{{ $resident->socioEconomicProfile?->disability_type ?: 'N/A' }}</div>
                </div>

                @php
                    $activeBadges = collect([
                        'PWD' => (bool) $resident->socioEconomicProfile?->is_pwd,
                        'OFW' => (bool) $resident->socioEconomicProfile?->is_ofw,
                        'Solo Parent' => (bool) $resident->socioEconomicProfile?->is_solo_parent,
                        'OSY' => (bool) $resident->socioEconomicProfile?->is_osy,
                        'OSC' => (bool) $resident->socioEconomicProfile?->is_osc,
                        'IP' => (bool) $resident->socioEconomicProfile?->is_ip,
                    ])->filter();
                @endphp

                <div>
                    <div class="font-medium text-gray-500">Flags</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse($activeBadges as $label => $enabled)
                            <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">{{ $label }}</span>
                        @empty
                            <span class="text-gray-500">No flags</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
