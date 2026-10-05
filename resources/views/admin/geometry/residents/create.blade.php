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
                    '{{ old('household_id', $selectedHouseholdId) }}',
                    @js(($availablePuroks ?? collect())->values()),
                    @js(($availableHouseholds ?? collect())->values())
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
        function residentForm(purokEndpoint, householdEndpoint, initialBarangay, initialPurok, initialHousehold, initialPuroks = [], initialHouseholds = []) {
            return {
                purokEndpoint,
                householdEndpoint,
                rankingEnabled: @js($routePrefix === 'secretary'),
                barangayId: initialBarangay || '',
                purokId: initialPurok || '',
                householdId: initialHousehold || '',
                householdSearchQuery: '',
                householdSearchOpen: false,
                filteredHouseholds: [],
                highlightedHouseholdIndex: 0,
                puroks: initialPuroks,
                households: initialHouseholds,
                init() {
                    this.barangayId = this.barangayId ? String(this.barangayId) : '';
                    this.purokId = this.purokId ? String(this.purokId) : '';
                    this.householdId = this.householdId ? String(this.householdId) : '';
                    this.syncHouseholdSearch();

                    if (this.barangayId && this.puroks.length === 0) {
                        this.loadPuroks(true);
                    } else if (this.purokId && this.households.length === 0) {
                        this.loadHouseholds(true);
                    }

                    this.$nextTick(() => {
                        if (this.$refs.purokSelect && this.purokId) {
                            this.$refs.purokSelect.value = String(this.purokId);
                        }
                    });
                },
                householdLabel(household) {
                    return `#${household.household_no} - ${household.household_address}`;
                },
                refreshHouseholdResults() {
                    const term = this.householdSearchQuery.trim().toLowerCase();

                    if (!term) {
                        this.filteredHouseholds = [];
                        this.highlightedHouseholdIndex = 0;
                        return;
                    }

                    this.filteredHouseholds = (this.rankingEnabled
                        ? window.rankHouseholdOptions(this.households, term)
                        : this.households.filter((household) => [household.household_no, household.household_address].join(' ').toLowerCase().includes(term)))
                        .slice(0, 12);

                    if (this.rankingEnabled || this.highlightedHouseholdIndex >= this.filteredHouseholds.length) {
                        this.highlightedHouseholdIndex = 0;
                    }
                },
                syncHouseholdSearch() {
                    const selectedHousehold = this.households.find((household) => String(household.id) === String(this.householdId));

                    if (selectedHousehold) {
                        this.householdSearchQuery = this.householdLabel(selectedHousehold);
                    } else if (!this.householdSearchOpen) {
                        this.householdSearchQuery = '';
                    }

                    this.refreshHouseholdResults();
                    this.syncHouseholdSearchValidity();
                },
                handleHouseholdSearchInput() {
                    const selectedHousehold = this.households.find((household) => String(household.id) === String(this.householdId));

                    if (!selectedHousehold || this.householdSearchQuery !== this.householdLabel(selectedHousehold)) {
                        this.householdId = '';
                    }

                    this.highlightedHouseholdIndex = 0;
                    this.refreshHouseholdResults();
                    this.householdSearchOpen = this.householdSearchQuery.trim().length > 0;
                    this.syncHouseholdSearchValidity();
                },
                openHouseholdSearch() {
                    if (this.households.length === 0) {
                        return;
                    }

                    this.refreshHouseholdResults();
                    this.householdSearchOpen = this.householdSearchQuery.trim().length > 0;
                },
                closeHouseholdSearch() {
                    window.setTimeout(() => {
                        this.householdSearchOpen = false;
                        this.syncHouseholdSearchValidity();
                    }, 120);
                },
                moveHouseholdSelection(step) {
                    if (!this.householdSearchOpen) {
                        this.openHouseholdSearch();
                    }

                    if (this.filteredHouseholds.length === 0) {
                        return;
                    }

                    const total = this.filteredHouseholds.length;
                    this.highlightedHouseholdIndex = (this.highlightedHouseholdIndex + step + total) % total;
                },
                selectHighlightedHousehold() {
                    if (!this.filteredHouseholds[this.highlightedHouseholdIndex]) {
                        return;
                    }

                    this.selectHousehold(this.filteredHouseholds[this.highlightedHouseholdIndex]);
                },
                selectHousehold(household) {
                    this.householdId = String(household.id);
                    this.householdSearchQuery = this.householdLabel(household);
                    this.householdSearchOpen = false;
                    this.highlightedHouseholdIndex = 0;
                    this.syncHouseholdSearchValidity();
                },
                syncHouseholdSearchValidity() {
                    if (!this.$refs.householdSearchInput) {
                        return;
                    }

                    this.$refs.householdSearchInput.setCustomValidity(
                        this.households.length > 0 && !this.householdId
                            ? 'Please select a household from the search results.'
                            : '',
                    );
                },
                @include('admin.geometry.residents.partials.dependent-options')
            };
        }
    </script>
@endpush
