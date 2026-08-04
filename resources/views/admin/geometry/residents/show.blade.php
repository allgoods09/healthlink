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
                Download RBI PDF
            </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has($routePrefix.'.residents.print'))
            <a href="{{ route($routePrefix.'.residents.print', $resident) }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                Open Print View
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
        @include('residents.partials.profile-details', [
            'resident' => $resident,
            'containerClass' => 'rounded-lg bg-white shadow xl:col-span-2',
        ])

        @include('residents.partials.socio-economic-profile', [
            'resident' => $resident,
            'containerClass' => 'rounded-lg bg-white shadow',
        ])
    </div>

    @if($routePrefix === 'bns')
        @php
            $latestPhilpen = $resident->latestPhilpenRiskAssessment;
        @endphp

        <div class="mt-6 rounded-lg bg-white shadow">
            <div class="border-b border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900">PhilPEN Nutrition-Relevant Summary</h2>
                <p class="mt-1 text-sm text-gray-500">Read-only adult risk screening details useful for nutrition follow-up and lifestyle review.</p>
            </div>

            @if($latestPhilpen)
                <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <div class="text-sm font-medium text-gray-500">Latest Assessment</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $latestPhilpen->assessment_date_label }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Recorded By</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $latestPhilpen->recordedBy?->name ?: 'Unknown BHW' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Immediate Referral Flag</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $latestPhilpen->requires_immediate_referral ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">BMI</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $latestPhilpen->body_mass_index ?: 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Waist Circumference</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $latestPhilpen->waist_circumference_cm ? number_format((float) $latestPhilpen->waist_circumference_cm, 2).' cm' : 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Physical Activity Goal</div>
                        <div class="mt-1 text-sm text-gray-900">
                            @if($latestPhilpen->physical_activity_met === null)
                                N/A
                            @else
                                {{ $latestPhilpen->physical_activity_met ? 'Met' : 'Not met' }}
                            @endif
                        </div>
                    </div>
                    <div class="md:col-span-2 xl:col-span-3">
                        <div class="text-sm font-medium text-gray-500">Dietary Assessment</div>
                        <div class="mt-1 text-sm text-gray-900">
                            @if($latestPhilpen->high_risk_diet_weekly === null)
                                No dietary screening answer recorded.
                            @else
                                {{ $latestPhilpen->high_risk_diet_weekly ? 'High-risk food and drink intake reported weekly.' : 'No weekly high-risk food and drink intake reported.' }}
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="p-6 text-sm text-gray-500">
                    No PhilPEN risk assessment has been synced for this resident yet.
                </div>
            @endif
        </div>
    @endif
@endsection
