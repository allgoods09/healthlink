@php
    $officialFieldNames = $officialNames ?? [];
@endphp

<div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-6">
    <div class="max-w-3xl">
        <h2 class="text-lg font-semibold text-slate-900">Barangay Officials</h2>
        <p class="mt-1 text-sm text-slate-600">
            These names feed directly into the official barangay records and document outputs. The Punong Barangay is required. Barangay kagawads may be left blank for now and filled in later.
        </p>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        @foreach($officialDefinitions as $roleKey => $definition)
            @php
                $fieldId = 'official_names_' . $roleKey;
                $value = old("official_names.$roleKey", $officialFieldNames[$roleKey] ?? '');
                $isRequired = $roleKey === \App\Models\BarangayOfficial::ROLE_PUNONG_BARANGAY;
            @endphp
            <div>
                <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700">
                    {{ $definition['title'] }}
                    @if($isRequired)
                        <span class="text-red-600">*</span>
                    @endif
                </label>
                <input
                    type="text"
                    name="official_names[{{ $roleKey }}]"
                    id="{{ $fieldId }}"
                    value="{{ $value }}"
                    @if($isRequired) required @endif
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error("official_names.$roleKey") border-red-500 @enderror"
                    placeholder="Enter {{ strtolower($definition['title']) }} name">
                @error("official_names.$roleKey")
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
</div>
