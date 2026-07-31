@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Documents - HealthLink')
@section('header', $pageHeader ?? 'Documents')

@php
    $routePrefix = $routePrefix ?? 'admin';
    $selectedBarangayId = $filters['barangay_id'] ?? $barangay?->id;
    $selectedDocumentType = $filters['document_type'] ?? 'resident_rbi';
@endphp

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Template-Locked Exports</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">RBI Document Generator</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
                        These exports use the official RBI PDF templates as fixed backgrounds, then stamp live registry data on exact coordinate points for clean, print-ready output.
                    </p>
                </div>
                <div class="rounded-2xl border border-tubigon/15 bg-tubigon/5 px-4 py-3 text-sm text-slate-700">
                    <div class="font-semibold text-tubigon">{{ $selectedDocumentType === 'household_rbi' ? 'RBI Form A' : 'RBI Form B' }}</div>
                    <div class="mt-1">{{ number_format($previewCount) }} record{{ $previewCount === 1 ? '' : 's' }} ready</div>
                </div>
            </div>

            <form method="GET" action="{{ route($routePrefix.'.documents.index') }}" class="mt-6" x-data="{ documentType: @js($selectedDocumentType) }">
                <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
                    <div class="space-y-5 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div>
                            <label for="document_type" class="block text-sm font-medium text-slate-700">Document Type</label>
                            <select id="document_type" name="document_type" x-model="documentType" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                @foreach($documentTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($routePrefix === 'admin')
                            <div>
                                <label for="barangay_id" class="block text-sm font-medium text-slate-700">Barangay</label>
                                <select id="barangay_id" name="barangay_id" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    <option value="">Select a barangay</option>
                                    @foreach($barangays as $barangayOption)
                                        <option value="{{ $barangayOption->id }}" @selected((string) $selectedBarangayId === (string) $barangayOption->id)>{{ $barangayOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Barangay</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $barangay?->name }}</div>
                            </div>
                        @endif

                        <div>
                            <label for="purok_id" class="block text-sm font-medium text-slate-700">Purok</label>
                            <select id="purok_id" name="purok_id" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <option value="">All puroks</option>
                                @foreach($puroks as $purok)
                                    <option value="{{ $purok->id }}" @selected((string) ($filters['purok_id'] ?? '') === (string) $purok->id)>
                                        {{ $purok->display_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="household_id" class="block text-sm font-medium text-slate-700">Household</label>
                            <select id="household_id" name="household_id" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                <option value="">All households</option>
                                @foreach($households as $householdOption)
                                    <option value="{{ $householdOption->id }}" @selected((string) ($filters['household_id'] ?? '') === (string) $householdOption->id)>
                                        Household #{{ $householdOption->household_no }}{{ $householdOption->purok ? ' - '.$householdOption->purok->display_name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="record_status" class="block text-sm font-medium text-slate-700">Record Status</label>
                                <select id="record_status" name="record_status" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    <option value="active" @selected(($filters['record_status'] ?? 'active') === 'active')>Active only</option>
                                    <option value="inactive" @selected(($filters['record_status'] ?? 'active') === 'inactive')>Inactive only</option>
                                    <option value="all" @selected(($filters['record_status'] ?? 'active') === 'all')>All</option>
                                </select>
                            </div>

                            <div x-show="documentType === 'household_rbi'" x-cloak>
                                <label for="social_aid" class="block text-sm font-medium text-slate-700">Social Aid</label>
                                <select id="social_aid" name="social_aid" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    <option value="all" @selected(($filters['social_aid'] ?? 'all') === 'all')>All households</option>
                                    <option value="yes" @selected(($filters['social_aid'] ?? 'all') === 'yes')>Beneficiary only</option>
                                    <option value="no" @selected(($filters['social_aid'] ?? 'all') === 'no')>Non-beneficiary only</option>
                                </select>
                            </div>

                            <div x-show="documentType === 'resident_rbi'" x-cloak>
                                <label for="sex" class="block text-sm font-medium text-slate-700">Sex</label>
                                <select id="sex" name="sex" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    <option value="">All</option>
                                    <option value="Male" @selected(($filters['sex'] ?? null) === 'Male')>Male</option>
                                    <option value="Female" @selected(($filters['sex'] ?? null) === 'Female')>Female</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="documentType === 'resident_rbi'" x-cloak class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="resident_status" class="block text-sm font-medium text-slate-700">Resident Status</label>
                                <select id="resident_status" name="resident_status" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    <option value="">All</option>
                                    <option value="active" @selected(($filters['resident_status'] ?? null) === 'active')>Active</option>
                                    <option value="deceased" @selected(($filters['resident_status'] ?? null) === 'deceased')>Deceased</option>
                                    <option value="relocated" @selected(($filters['resident_status'] ?? null) === 'relocated')>Relocated</option>
                                </select>
                            </div>
                            <div>
                                <label for="age_min" class="block text-sm font-medium text-slate-700">Min Age</label>
                                <input id="age_min" name="age_min" type="number" min="0" max="150" value="{{ $filters['age_min'] ?? '' }}" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label for="age_max" class="block text-sm font-medium text-slate-700">Max Age</label>
                                <input id="age_max" name="age_max" type="number" min="0" max="150" value="{{ $filters['age_max'] ?? '' }}" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Export Summary</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-600">
                                The generated file will preserve the locked government template and place live registry data using vector text overlay for clean printing and crisp digital archiving.
                            </p>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                    <div class="text-sm text-slate-500">Template</div>
                                    <div class="mt-1 text-base font-semibold text-slate-900">{{ $selectedDocumentType === 'household_rbi' ? 'RBI Form A' : 'RBI Form B' }}</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                    <div class="text-sm text-slate-500">Matching Records</div>
                                    <div class="mt-1 text-base font-semibold text-slate-900">{{ number_format($previewCount) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <button type="submit" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                Refresh Preview
                            </button>
                            <button type="submit" formaction="{{ route($routePrefix.'.documents.export') }}" class="inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                                Generate Locked PDF
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Attestation Settings</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">Barangay Officials</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        These names are automatically stamped into the RBI attestation fields so exports stay consistent and no manual prompt is needed during every print run.
                    </p>
                </div>
                @if($barangay)
                    <span class="inline-flex rounded-full border border-tubigon/20 bg-tubigon/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-tubigon">
                        {{ $barangay->name }}
                    </span>
                @endif
            </div>

            @if($routePrefix === 'admin' && ! $barangay)
                <div class="mt-6 rounded-2xl border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">
                    Select a barangay in the document filters first to manage that barangay's attestation officials.
                </div>
            @else
                <form method="POST" action="{{ route($routePrefix.'.documents.officials.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')

                    @if($routePrefix === 'admin')
                        <input type="hidden" name="barangay_id" value="{{ $barangay?->id }}">
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($officials as $official)
                            <div>
                                <label for="official_{{ $official->role_key }}" class="block text-sm font-medium text-slate-700">{{ $official->official_title }}</label>
                                <input
                                    id="official_{{ $official->role_key }}"
                                    name="officials[{{ $official->role_key }}]"
                                    type="text"
                                    value="{{ old('officials.'.$official->role_key, $official->official_name) }}"
                                    class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-tubigon focus:ring-tubigon"
                                    placeholder="Enter {{ strtolower($official->official_title) }} name">
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Save Officials
                        </button>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection
