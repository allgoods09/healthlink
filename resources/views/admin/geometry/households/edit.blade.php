@extends($layout ?? 'layouts.admin')

@section('title', $pageTitle ?? 'Edit Household - HealthLink Admin')
@section('header', $pageHeader ?? 'Edit Household')

@php
    $routePrefix = $routePrefix ?? 'admin';
@endphp

@section('actions')
    <div class="flex items-center gap-2">
        <a href="{{ route($routePrefix.'.households.show', $household) }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            View
        </a>
        <a href="{{ route($routePrefix.'.households.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
            Back
        </a>
    </div>
@endsection

@section('content')
    <div class="rounded-lg bg-white shadow">
        <div class="p-6">
            <form
                method="POST"
                action="{{ route($routePrefix.'.households.update', $household) }}"
                x-data="householdForm('{{ route($routePrefix.'.puroks.get-by-barangay') }}', '{{ old('purok_id', $household->purok_id) }}', '{{ old('barangay_id', $selectedBarangayId) }}')"
            >
                @csrf
                @method('PUT')
                @include('admin.geometry.households.partials.form')

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Update Household
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function householdForm(endpoint, initialPurok, initialBarangay) {
            return {
                endpoint,
                barangayId: initialBarangay || '',
                purokId: initialPurok || '',
                puroks: [],
                init() {
                    if (this.barangayId) {
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
