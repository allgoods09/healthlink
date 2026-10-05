@extends('layouts.portal')

@section('title', 'RBI Document Generator - HealthLink Secretary')
@section('header', 'RBI Document Generator')
@section('subheader', 'Choose the document, coverage and filters, then review before generating the locked RBI PDF.')

@php
    $steps = [1 => 'Document', 2 => 'Coverage', 3 => 'Filters', 4 => 'Review'];
    $isHousehold = $state['document_type'] === 'household_rbi';
    $fields = $state;
    foreach ($isHousehold ? ['sex', 'resident_status', 'age_min', 'age_max'] : ['social_aid', 'record_status', 'resident_status'] as $field) {
        unset($fields[$field]);
    }
    $visibleFields = match ($step) {
        1 => ['document_type'], 2 => ['coverage', 'purok_ids', 'household_ids'],
        3 => ['record_status', 'social_aid', 'sex', 'age_min', 'age_max'], default => [],
    };
    $unit = $isHousehold ? 'households' : 'residents';
@endphp

@section('content')
    <nav aria-label="Document generation steps" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach($steps as $number => $label)
            @if($number < $step)
                <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-800">
                    {{ $number }}. {{ $label }} <span class="block text-xs font-normal">Completed</span>
                </div>
            @else
                <div @if($number === $step) aria-current="step" @endif class="rounded-lg border px-4 py-3 text-sm font-semibold {{ $number === $step ? 'border-tubigon bg-tubigon/5 text-tubigon' : 'border-slate-200 bg-white text-slate-400' }}">
                    {{ $number }}. {{ $label }} <span class="block text-xs font-normal">{{ $number === $step ? 'Current step' : 'Upcoming' }}</span>
                </div>
            @endif
        @endforeach
    </nav>

    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" data-rbi-step="{{ $step }}">
        <h2 class="text-xl font-semibold text-slate-900">{{ $step }}. {{ $step === 4 ? 'Review & Generate' : $steps[$step] }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ $barangay->name }}</p>

        @if($step < 4)
            <form method="GET" action="{{ route('secretary.documents.index') }}" class="mt-6 space-y-6" data-filter-panel="false">
                <input type="hidden" name="step" value="{{ $step + 1 }}">
                @foreach($fields as $field => $value)
                    @continue(in_array($field, $visibleFields, true))
                    @continue($field === 'purok_ids' && $state['coverage'] !== 'puroks')
                    @continue($field === 'household_ids' && $state['coverage'] !== 'households')
                    @if(is_array($value))
                        @foreach($value as $id)
                            <input type="hidden" name="{{ $field }}[]" value="{{ $id }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                    @endif
                @endforeach

                @if($step === 1)
                    <label for="document_type" class="block text-sm font-medium text-slate-700">Document</label>
                    <select id="document_type" name="document_type" required class="block w-full max-w-xl rounded-lg border-slate-300 text-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">Choose a document</option>
                        @foreach($documentTypes as $value => $label)
                            <option value="{{ $value }}" @selected($state['document_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('document_type')" />
                    <p class="text-sm text-slate-600">Form A records households and their members. Form B creates an individual form for each matching resident.</p>
                @elseif($step === 2)
                    <div x-data="{ coverage: @js($state['coverage']) }" class="space-y-5">
                        <label for="coverage" class="block text-sm font-medium text-slate-700">Coverage</label>
                        <select id="coverage" name="coverage" x-model="coverage" required class="block w-full max-w-xl rounded-lg border-slate-300 text-sm focus:border-tubigon focus:ring-tubigon">
                            @foreach($coverageTypes as $value => $label)
                                <option value="{{ $value }}" @selected($state['coverage'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->all()" />
                        <p x-show="coverage === 'barangay'" class="text-sm text-slate-600">Include all matching {{ $unit }} in your assigned barangay. Filters can narrow them in the next step.</p>

                        <fieldset x-show="coverage === 'puroks'" :disabled="coverage !== 'puroks'" class="rounded-lg border border-slate-200 p-4">
                            <legend class="px-2 text-sm font-semibold text-slate-800">Puroks (for Selected Puroks coverage)</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @forelse($puroks as $purok)
                                    <label class="flex items-center gap-3 text-sm text-slate-700">
                                        <input type="checkbox" name="purok_ids[]" value="{{ $purok->id }}" @checked(in_array((string) $purok->id, array_map('strval', $state['purok_ids']), true)) class="rounded border-slate-300 text-tubigon focus:ring-tubigon">
                                        {{ $purok->display_name }}{{ $purok->is_active ? '' : ' (Inactive)' }}
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-500">No puroks are registered in this barangay.</p>
                                @endforelse
                            </div>
                        </fieldset>

                        @php
                            $householdOptions = $households->map(fn ($household) => [
                                'id' => (string) $household->id,
                                'label' => 'Household #'.$household->household_no.' - '.$household->purok->display_name,
                            ])->values()->all();
                        @endphp
                        <fieldset x-show="coverage === 'households'" :disabled="coverage !== 'households'" class="rounded-lg border border-slate-200 p-4"
                            x-data="rbiHouseholdSelector(@js($householdOptions), @js(array_map('strval', $state['household_ids'])))">
                            <legend class="px-2 text-sm font-semibold text-slate-800">Households (for Selected Households coverage)</legend>
                            <div x-cloak class="mb-4">
                                <label for="household_search" class="block text-sm font-medium text-slate-700">Search household number or purok</label>
                                <input id="household_search" type="search" x-model="query" @input="updateSearch()" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-tubigon focus:ring-tubigon" autocomplete="off">
                            </div>
                            <p class="mb-3 text-sm font-medium text-slate-700" role="status" x-text="selected.length + ' households selected'">{{ count($state['household_ids']) }} households selected</p>
                            <div class="max-h-80 space-y-3 overflow-y-auto">
                                @forelse($households as $household)
                                    <label x-show="visibleIds.includes(@js((string) $household->id))" class="flex items-center gap-3 rounded-lg border border-slate-100 p-3 text-sm text-slate-700">
                                        <input type="checkbox" name="household_ids[]" value="{{ $household->id }}" x-model="selected" @checked(in_array((string) $household->id, array_map('strval', $state['household_ids']), true)) class="rounded border-slate-300 text-tubigon focus:ring-tubigon">
                                        Household #{{ $household->household_no }} - {{ $household->purok->display_name }}{{ $household->is_active ? '' : ' (Inactive)' }}
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-500">No households are registered in this barangay.</p>
                                @endforelse
                                <p x-cloak x-show="matches.length === 0" class="text-sm text-slate-500">No households match your search.</p>
                            </div>
                            <div x-cloak class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <button type="button" @click="changePage(-1)" :disabled="page <= 1" class="rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:opacity-40">Previous</button>
                                <span class="text-sm text-slate-500" x-text="'Page ' + page + ' of ' + pageCount"></span>
                                <button type="button" @click="changePage(1)" :disabled="page >= pageCount" class="rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:opacity-40">Next households</button>
                            </div>
                            <noscript><p class="mt-3 text-sm text-slate-500">Scroll the household list to select records. Only the chosen coverage mode will be used.</p></noscript>
                        </fieldset>
                    </div>
                @else
                    <div class="grid max-w-3xl gap-5 sm:grid-cols-2">
                        <p class="text-sm text-slate-500 sm:col-span-2">Current residents only. Household forms require at least one current member.</p>
                        @if($isHousehold)
                        <div>
                            <label for="record_status" class="block text-sm font-medium text-slate-700">{{ $isHousehold ? 'Household' : 'Resident' }} Record Status</label>
                            <select id="record_status" name="record_status" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-tubigon focus:ring-tubigon">
                                @foreach(['active' => 'Active only', 'inactive' => 'Inactive only', 'all' => 'All records'] as $value => $label)
                                    <option value="{{ $value }}" @selected($state['record_status'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('record_status')" class="mt-2" />
                        </div>
                        @endif
                        @if($isHousehold)
                            <div>
                                <label for="social_aid" class="block text-sm font-medium text-slate-700">Social Aid Beneficiary</label>
                                <select id="social_aid" name="social_aid" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-tubigon focus:ring-tubigon">
                                    @foreach(['all' => 'All households', 'yes' => 'Beneficiary only', 'no' => 'Non-beneficiary only'] as $value => $label)
                                        <option value="{{ $value }}" @selected($state['social_aid'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('social_aid')" class="mt-2" />
                            </div>
                        @else
                            <div>
                                <label for="sex" class="block text-sm font-medium text-slate-700">Sex</label>
                                <select id="sex" name="sex" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-tubigon focus:ring-tubigon">
                                    @foreach(['' => 'All', 'Male' => 'Male', 'Female' => 'Female'] as $value => $label)
                                        <option value="{{ $value }}" @selected($state['sex'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('sex')" class="mt-2" />
                            </div>
                            @foreach(['age_min' => 'Minimum Age', 'age_max' => 'Maximum Age'] as $field => $label)
                                <div>
                                    <label for="{{ $field }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                                    <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" max="150" value="{{ $state[$field] }}" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-tubigon focus:ring-tubigon">
                                    <x-input-error :messages="$errors->get($field)" class="mt-2" />
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endif
                <div class="flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-5">
                    @if($step > 1)
                        <x-record-action type="submit" name="step" :value="$step - 1" formnovalidate variant="back">Back</x-record-action>
                    @endif
                    <x-record-action type="submit" variant="edit">{{ $step === 3 ? 'Review Selection' : 'Continue' }}</x-record-action>
                </div>
            </form>
        @else
            <dl class="mt-6 grid gap-6 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Document</dt><dd class="mt-2 font-medium text-slate-900">{{ $documentTypes[$selection['document_type']] }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Barangay</dt><dd class="mt-2 text-slate-900">{{ $barangay->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Coverage</dt><dd class="mt-2 text-slate-900">
                    {{ $coverageTypes[$selection['coverage']] }}
                    @if($selection['coverage'] === 'puroks')
                        <ul class="mt-2 space-y-1 text-sm">@foreach($puroks->whereIn('id', $selection['purok_ids']) as $purok)<li>{{ $purok->display_name }}</li>@endforeach</ul>
                    @elseif($selection['coverage'] === 'households')
                        <ul class="mt-2 space-y-1 text-sm">@foreach($households->whereIn('id', $selection['household_ids']) as $household)<li>Household #{{ $household->household_no }} - {{ $household->purok->display_name }}</li>@endforeach</ul>
                    @endif
                </dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Effective Filters</dt><dd class="mt-2"><ul class="space-y-1 text-sm text-slate-700">@foreach($filterLabels as $label => $value)<li><strong>{{ $label }}:</strong> {{ $value }}</li>@endforeach</ul></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Matching {{ ucfirst($unit) }}</dt><dd class="mt-2 text-2xl font-semibold text-tubigon">{{ number_format($previewCount) }} {{ $unit }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Signatories</dt><dd class="mt-2 space-y-2 text-sm text-slate-700">
                    @foreach($isHousehold ? ['barangay_secretary' => 'Barangay Secretary', 'punong_barangay' => 'Punong Barangay'] : ['barangay_secretary' => 'Barangay Secretary'] as $role => $label)
                        @php($name = $role === 'barangay_secretary' ? $resolvedSecretaryName : $officials->get($role)?->official_name)
                        <p><strong>{{ $label }}:</strong> {{ $name ?: 'Not assigned' }}</p>
                        @if(blank($name))<p class="text-amber-700">{{ $label }} is not assigned. The corresponding signature/name line will be blank.</p>@endif
                    @endforeach
                </dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Output</dt><dd class="mt-2 text-sm text-slate-700">One locked RBI PDF download. {{ $isHousehold ? 'Households with more than 12 members use continuation pages.' : $previewCount.' individual pages.' }}</dd></div>
            </dl>
            @if($isHousehold)<p class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Household forms include only current household members.</p>@endif
            <p class="mt-5 text-sm text-slate-500">This review reflects current records. Generation checks the selection again; records and official names may have changed since review.</p>
            @if($previewCount === 0)<p role="status" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No {{ $unit }} match this selection. Go Back to adjust coverage or filters.</p>@endif
            <div class="mt-6 flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-5">
                <x-record-action :href="route('secretary.documents.index', $selection + ['step' => 3])" variant="back">Back to Filters</x-record-action>
                @if($reviewToken)
                    <form method="GET" action="{{ route('secretary.documents.export') }}" data-filter-panel="false" data-rbi-reviewed>
                        <input type="hidden" name="review" value="{{ $reviewToken }}">
                        <x-record-action type="submit" variant="document">Generate Locked PDF</x-record-action>
                    </form>
                @endif
            </div>
        @endif
    </section>
@endsection
