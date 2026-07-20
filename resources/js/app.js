

import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.searchableRecordSelect = (config = {}) => ({
    options: Array.isArray(config.options) ? config.options : [],
    selectedValue: config.selected === undefined || config.selected === null ? '' : String(config.selected),
    placeholder: config.placeholder || 'Search records',
    emptyMessage: config.emptyMessage || 'No matching records found.',
    required: Boolean(config.required),
    disabled: Boolean(config.disabled),
    maxResults: Number.parseInt(config.maxResults ?? 12, 10),
    query: '',
    isOpen: false,
    highlightedIndex: 0,
    normalizedOptions: [],
    filteredOptions: [],

    init() {
        this.setOptions(this.options);
    },

    setOptions(options) {
        this.options = Array.isArray(options) ? options : [];
        this.normalizedOptions = this.options
            .map((option) => ({
                value: String(option.value ?? option.id ?? ''),
                label: String(option.label ?? ''),
                search: String(option.search ?? option.label ?? '').toLowerCase(),
                description: String(option.description ?? ''),
            }))
            .filter((option) => option.value !== '');

        if (!this.normalizedOptions.find((option) => option.value === this.selectedValue)) {
            this.selectedValue = '';
        }

        this.syncQueryToSelection();
        this.refreshResults();
        this.syncValidity();
    },

    syncQueryToSelection() {
        const selectedOption = this.normalizedOptions.find((option) => option.value === this.selectedValue);
        this.query = selectedOption ? selectedOption.label : '';
    },

    handleInput() {
        const selectedOption = this.normalizedOptions.find((option) => option.value === this.selectedValue);

        if (!selectedOption || this.query !== selectedOption.label) {
            this.selectedValue = '';
        }

        this.highlightedIndex = 0;
        this.refreshResults();
        this.isOpen = this.query.trim().length > 0;
        this.syncValidity();
    },

    handleBlur() {
        window.setTimeout(() => {
            this.isOpen = false;
            this.syncValidity();
        }, 120);
    },

    openIfSearching() {
        if (this.disabled) {
            return;
        }

        this.refreshResults();
        this.isOpen = this.query.trim().length > 0;
    },

    refreshResults() {
        const term = this.query.trim().toLowerCase();

        if (!term) {
            this.filteredOptions = [];
            this.highlightedIndex = 0;
            return;
        }

        this.filteredOptions = this.normalizedOptions
            .filter((option) => option.label.toLowerCase().includes(term) || option.search.includes(term))
            .slice(0, this.maxResults);

        if (this.highlightedIndex >= this.filteredOptions.length) {
            this.highlightedIndex = 0;
        }
    },

    move(step) {
        if (this.disabled) {
            return;
        }

        if (!this.isOpen) {
            this.openIfSearching();
        }

        if (this.filteredOptions.length === 0) {
            return;
        }

        const total = this.filteredOptions.length;
        this.highlightedIndex = (this.highlightedIndex + step + total) % total;
    },

    selectHighlighted() {
        if (!this.filteredOptions[this.highlightedIndex]) {
            return;
        }

        this.selectOption(this.filteredOptions[this.highlightedIndex]);
    },

    selectOption(option) {
        this.selectedValue = option.value;
        this.query = option.label;
        this.isOpen = false;
        this.highlightedIndex = 0;
        this.syncValidity();
    },

    syncValidity() {
        if (!this.$refs.searchInput) {
            return;
        }

        this.$refs.searchInput.setCustomValidity(
            this.required && !this.selectedValue
                ? 'Please select a record from the search results.'
                : '',
        );
    },
});

Alpine.data('sidebarLayout', (sidebarContext = 'default', desktopBreakpoint = 1024) => ({
    storageKey: 'healthlink.sidebar.desktop.open',
    scrollStoragePrefix: 'healthlink.sidebar.scroll',
    sidebarContext,
    isDesktop: window.innerWidth >= desktopBreakpoint,
    sidebarOpen: window.innerWidth >= desktopBreakpoint,
    resizeHandler: null,
    scrollHandler: null,
    pageHideHandler: null,

    init() {
        this.sidebarOpen = this.resolveInitialSidebarState();

        this.resizeHandler = () => {
            const isDesktop = window.innerWidth >= desktopBreakpoint;

            if (isDesktop !== this.isDesktop) {
                this.isDesktop = isDesktop;
                this.sidebarOpen = isDesktop
                    ? this.getStoredDesktopPreference() ?? true
                    : false;
                return;
            }

            this.isDesktop = isDesktop;

            if (isDesktop) {
                this.sidebarOpen = this.getStoredDesktopPreference() ?? true;
            }
        };

        this.resizeHandler();
        window.addEventListener('resize', this.resizeHandler);

        this.$watch('sidebarOpen', (isOpen) => {
            if (isOpen) {
                this.$nextTick(() => {
                    this.attachScrollListener();
                    this.restoreSidebarScroll();
                });

                return;
            }

            this.persistSidebarScroll();
        });

        this.pageHideHandler = () => this.persistSidebarScroll();
        window.addEventListener('pagehide', this.pageHideHandler);

        this.$nextTick(() => {
            this.attachScrollListener();
            this.restoreSidebarScroll();
        });
    },

    destroy() {
        this.persistSidebarScroll();

        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler);
        }

        if (this.scrollHandler && this.$refs.sidebarScroll) {
            this.$refs.sidebarScroll.removeEventListener('scroll', this.scrollHandler);
        }

        if (this.pageHideHandler) {
            window.removeEventListener('pagehide', this.pageHideHandler);
        }
    },

    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
        this.persistDesktopPreference();
    },

    closeSidebar() {
        if (!this.isDesktop) {
            this.sidebarOpen = false;
        }
    },

    handleNavClick(event) {
        if (!event.target.closest('a[href]')) {
            return;
        }

        this.persistSidebarScroll();

        if (!this.isDesktop) {
            this.sidebarOpen = false;
        }
    },

    resolveInitialSidebarState() {
        if (!this.isDesktop) {
            return false;
        }

        return this.getStoredDesktopPreference() ?? true;
    },

    persistDesktopPreference() {
        if (!this.isDesktop) {
            return;
        }

        try {
            window.localStorage.setItem(this.storageKey, this.sidebarOpen ? '1' : '0');
        } catch (error) {
            // Ignore storage failures so navigation never breaks.
        }
    },

    getStoredDesktopPreference() {
        try {
            const value = window.localStorage.getItem(this.storageKey);

            if (value === null) {
                return null;
            }

            return value === '1';
        } catch (error) {
            return null;
        }
    },

    attachScrollListener() {
        if (!this.$refs.sidebarScroll || this.scrollHandler) {
            return;
        }

        this.scrollHandler = () => this.persistSidebarScroll();
        this.$refs.sidebarScroll.addEventListener('scroll', this.scrollHandler, { passive: true });
    },

    persistSidebarScroll() {
        if (!this.$refs.sidebarScroll) {
            return;
        }

        try {
            window.sessionStorage.setItem(this.scrollStorageKey(), String(this.$refs.sidebarScroll.scrollTop));
        } catch (error) {
            // Ignore storage failures so navigation never breaks.
        }
    },

    restoreSidebarScroll() {
        if (!this.$refs.sidebarScroll) {
            return;
        }

        const storedScrollTop = this.getStoredSidebarScroll();

        if (storedScrollTop === null) {
            return;
        }

        this.$refs.sidebarScroll.scrollTop = storedScrollTop;
    },

    getStoredSidebarScroll() {
        try {
            const value = window.sessionStorage.getItem(this.scrollStorageKey());

            if (value === null) {
                return null;
            }

            const parsedValue = Number.parseInt(value, 10);

            return Number.isNaN(parsedValue) ? null : parsedValue;
        } catch (error) {
            return null;
        }
    },

    scrollStorageKey() {
        return `${this.scrollStoragePrefix}.${this.sidebarContext}`;
    },
}));

function initializeProgressivePurokFilters() {
    const filterGroups = document.querySelectorAll('[data-progressive-purok-filter]');

    filterGroups.forEach((group) => {
        const barangaySelect = group.querySelector('[data-barangay-filter-select]');
        const purokSelect = group.querySelector('[data-purok-filter-select]');

        if (!(barangaySelect instanceof HTMLSelectElement) || !(purokSelect instanceof HTMLSelectElement)) {
            return;
        }

        const placeholderOption = purokSelect.querySelector('option[value=""]')?.cloneNode(true)
            ?? new Option('All puroks', '');

        const allOptions = Array.from(purokSelect.querySelectorAll('option'))
            .filter((option) => option.value !== '')
            .map((option) => ({
                value: option.value,
                text: option.textContent ?? '',
                barangayId: option.dataset.barangayId ?? '',
            }));

        const rebuildOptions = () => {
            const selectedBarangayId = barangaySelect.value;
            const previousPurokValue = purokSelect.value;
            const allowedOptions = selectedBarangayId
                ? allOptions.filter((option) => option.barangayId === selectedBarangayId)
                : allOptions;

            purokSelect.innerHTML = '';
            purokSelect.appendChild(placeholderOption.cloneNode(true));

            allowedOptions.forEach((option) => {
                const nextOption = new Option(option.text, option.value);
                nextOption.dataset.barangayId = option.barangayId;
                purokSelect.appendChild(nextOption);
            });

            const canKeepPreviousSelection = allowedOptions.some((option) => option.value === previousPurokValue);
            purokSelect.value = canKeepPreviousSelection ? previousPurokValue : '';
            purokSelect.disabled = selectedBarangayId !== '' && allowedOptions.length === 0;
        };

        barangaySelect.addEventListener('change', rebuildOptions);
        rebuildOptions();
    });
}

Alpine.start();
initializeProgressivePurokFilters();
