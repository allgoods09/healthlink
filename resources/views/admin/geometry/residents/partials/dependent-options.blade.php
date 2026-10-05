purokRequestGeneration: 0,
householdRequestGeneration: 0,
purokLoading: false,
householdLoading: false,
purokError: '',
householdError: '',
invalidateHouseholds(preserveSelection = false) {
    ++this.householdRequestGeneration;
    this.households = [];
    if (!preserveSelection) this.householdId = '';
    this.householdLoading = false;
    this.householdError = '';
    this.householdSearchOpen = false;
    this.householdSearchQuery = '';
    this.syncHouseholdSearch();
},
async loadPuroks(preserveSelection = false) {
    const generation = ++this.purokRequestGeneration;
    const barangay = String(this.barangayId || '');
    const isCurrent = () => generation === this.purokRequestGeneration && barangay === String(this.barangayId || '');
    this.puroks = [];
    if (!preserveSelection || !barangay) this.purokId = '';
    this.invalidateHouseholds(preserveSelection && !!barangay);
    this.purokError = '';
    this.purokLoading = !!barangay;
    if (!barangay) return;

    try {
        const response = await fetch(`${this.purokEndpoint}?barangay_id=${encodeURIComponent(barangay)}`);
        if (!isCurrent()) return;
        if (!response.ok) throw new Error('Purok lookup failed');
        const options = await response.json();
        if (!isCurrent()) return;
        if (!Array.isArray(options)) throw new Error('Invalid Purok response');
        this.puroks = options;
        if (!options.some((purok) => String(purok.id) === String(this.purokId))) this.purokId = '';
        this.purokLoading = false;
        await this.loadHouseholds(preserveSelection);
    } catch {
        if (!isCurrent()) return;
        this.puroks = [];
        this.purokId = '';
        this.invalidateHouseholds();
        this.purokError = 'Unable to load puroks. Please select the barangay again.';
    } finally {
        if (isCurrent()) this.purokLoading = false;
    }
},
async loadHouseholds(preserveSelection = false) {
    const purok = String(this.purokId || '');
    const barangay = String(this.barangayId || '');
    this.invalidateHouseholds(preserveSelection && !!purok);
    const generation = this.householdRequestGeneration;
    // Parent identity alone cannot distinguish an older A request from a newer A after A/B/A.
    const isCurrent = () => generation === this.householdRequestGeneration
        && purok === String(this.purokId || '') && barangay === String(this.barangayId || '');
    this.householdLoading = !!purok;
    if (!purok) return;

    try {
        const response = await fetch(`${this.householdEndpoint}?purok_id=${encodeURIComponent(purok)}`);
        if (!isCurrent()) return;
        if (!response.ok) throw new Error('Household lookup failed');
        const options = await response.json();
        if (!isCurrent()) return;
        if (!Array.isArray(options)) throw new Error('Invalid Household response');
        this.households = options;
        if (!options.some((household) => String(household.id) === String(this.householdId))) this.householdId = '';
        this.syncHouseholdSearch();
    } catch {
        if (!isCurrent()) return;
        this.households = [];
        this.householdId = '';
        this.syncHouseholdSearch();
        this.householdError = 'Unable to load households. Please select the purok again.';
    } finally {
        if (isCurrent()) this.householdLoading = false;
    }
},
