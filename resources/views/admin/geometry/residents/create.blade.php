@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Create Resident - HealthLink Admin')
@section('header', $pageHeader ?? 'Create Resident')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
    <a href="{{ route($routePrefix.'.residents.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
        Back
    </a>
@endsection

@section('content')
    <div class="rounded-lg bg-white shadow">
        <div class="p-6">
            <form
                method="POST"
                action="{{ route($routePrefix.'.residents.store') }}"
                x-data="residentForm(
                    '{{ route($routePrefix.'.puroks.get-by-barangay') }}',
                    '{{ route($routePrefix.'.residents.households-by-purok') }}',
                    '{{ old('barangay_id', $selectedBarangayId) }}',
                    '{{ old('purok_id', $selectedPurokId) }}',
                    '{{ old('household_id', $selectedHouseholdId) }}'
                )"
            >
                @csrf
                @include('admin.geometry.residents.partials.form')

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Create Resident
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function residentForm(purokEndpoint, householdEndpoint, initialBarangay, initialPurok, initialHousehold) {
            return {
                purokEndpoint,
                householdEndpoint,
                barangayId: initialBarangay || '',
                purokId: initialPurok || '',
                householdId: initialHousehold || '',
                puroks: [],
                households: [],
                init() {
                    if (this.barangayId) {
                        this.loadPuroks();
                    }
                },
                async loadPuroks() {
                    if (!this.barangayId) {
                        this.puroks = [];
                        this.purokId = '';
                        this.households = [];
                        this.householdId = '';
                        return;
                    }

                    const response = await fetch(`${this.purokEndpoint}?barangay_id=${this.barangayId}`);
                    this.puroks = await response.json();

                    if (!this.puroks.find((purok) => String(purok.id) === String(this.purokId))) {
                        this.purokId = '';
                    }

                    await this.loadHouseholds();
                },
                async loadHouseholds() {
                    if (!this.purokId) {
                        this.households = [];
                        this.householdId = '';
                        return;
                    }

                    const response = await fetch(`${this.householdEndpoint}?purok_id=${this.purokId}`);
                    this.households = await response.json();

                    if (!this.households.find((household) => String(household.id) === String(this.householdId))) {
                        this.householdId = '';
                    }
                },
            };
        }
    </script>
@endpush
