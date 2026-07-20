@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Create Household - HealthLink Admin')
@section('header', $pageHeader ?? 'Create Household')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
    <a href="{{ route($routePrefix.'.households.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
        Back
    </a>
@endsection

@section('content')
    <div class="rounded-lg bg-white shadow">
        <div class="p-6">
            <form
                method="POST"
                action="{{ route($routePrefix.'.households.store') }}"
                x-data="householdForm('{{ route($routePrefix.'.puroks.get-by-barangay') }}', '{{ old('purok_id', $selectedPurokId) }}', '{{ old('barangay_id', $selectedBarangayId) }}', @js(($availablePuroks ?? collect())->values()))"
            >
                @csrf
                @include('admin.geometry.households.partials.form')

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Create Household
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function householdForm(endpoint, initialPurok, initialBarangay, initialPuroks = []) {
            return {
                endpoint,
                barangayId: initialBarangay || '',
                purokId: initialPurok || '',
                puroks: initialPuroks,
                init() {
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

                    const response = await fetch(`${this.endpoint}?barangay_id=${this.barangayId}`);
                    this.puroks = await response.json();

                    if (!this.puroks.find((purok) => String(purok.id) === String(this.purokId))) {
                        this.purokId = '';
                    }
                },
            };
        }
    </script>
@endpush
