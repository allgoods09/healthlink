

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

function filterLabelForControl(form, control) {
    if (!control.name) {
        return 'Filter';
    }

    const label = control.id
        ? form.querySelector(`label[for="${CSS.escape(control.id)}"]`)
        : null;

    return label?.textContent?.trim() || control.name.replace(/_/g, ' ');
}

function filterValueForControl(control, fallbackValue) {
    if (control instanceof HTMLSelectElement) {
        return control.selectedOptions[0]?.textContent?.trim() || fallbackValue;
    }

    if (control instanceof HTMLInputElement && control.type === 'date') {
        const date = new Date(`${fallbackValue}T00:00:00`);

        return Number.isNaN(date.getTime())
            ? fallbackValue
            : new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
    }

    return fallbackValue;
}

function buildActiveFilterSummary(form) {
    const activeFilters = [];
    const query = new URLSearchParams(window.location.search);
    const handledNames = new Set();

    Array.from(form.elements).forEach((control) => {
        if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
            return;
        }

        if (!control.name || handledNames.has(control.name) || ['page', 'search'].includes(control.name)) {
            return;
        }

        const value = query.get(control.name);

        if (!value) {
            return;
        }

        handledNames.add(control.name);
        activeFilters.push({
            name: control.name,
            label: filterLabelForControl(form, control),
            value: filterValueForControl(control, value),
        });
    });

    if (activeFilters.length === 0) {
        return null;
    }

    const summary = document.createElement('div');
    summary.className = 'filter-modal-active-summary';

    const heading = document.createElement('span');
    heading.className = 'filter-modal-active-label';
    heading.textContent = `${activeFilters.length} active`;
    summary.appendChild(heading);

    const chips = document.createElement('div');
    chips.className = 'filter-modal-chips';

    activeFilters.slice(0, 3).forEach((filter) => {
        const chip = document.createElement('a');
        const removalUrl = new URL(window.location.href);
        removalUrl.searchParams.delete(filter.name);
        removalUrl.searchParams.delete('page');

        chip.className = 'filter-modal-chip';
        chip.href = removalUrl.toString();
        chip.title = `Remove ${filter.label} filter`;
        chip.setAttribute('aria-label', `Remove ${filter.label}: ${filter.value}`);
        chip.innerHTML = `
            <span class="filter-modal-chip-text"></span>
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" d="M6 6l8 8m0-8-8 8"></path>
            </svg>
        `;
        chip.querySelector('.filter-modal-chip-text').textContent = `${filter.label}: ${filter.value}`;
        chips.appendChild(chip);
    });

    if (activeFilters.length > 3) {
        const overflow = document.createElement('span');
        overflow.className = 'filter-modal-chip filter-modal-chip-overflow';
        overflow.textContent = `+${activeFilters.length - 3} more`;
        chips.appendChild(overflow);
    }

    const clearLink = document.createElement('a');
    clearLink.className = 'filter-modal-clear';
    const clearUrl = new URL(form.action, window.location.href);
    const currentSearch = query.get('search');

    if (currentSearch) {
        clearUrl.searchParams.set('search', currentSearch);
    }

    clearLink.href = clearUrl.toString();
    clearLink.textContent = 'Clear filters';

    summary.append(heading, chips, clearLink);

    return summary;
}

function findStandaloneFilterContainer(form) {
    let candidate = form;
    let parent = candidate.parentElement;

    while (parent && !parent.matches('body, main, [role="main"]')) {
        const siblingElements = Array.from(parent.children).filter((child) => child !== candidate);

        if (siblingElements.length > 0) {
            break;
        }

        candidate = parent;
        parent = candidate.parentElement;
    }

    return candidate;
}

function createLiveTableSearch(filterForm, index) {
    const searchInput = filterForm.querySelector('input[name="search"], input[type="search"]');

    if (!(searchInput instanceof HTMLInputElement)) {
        return null;
    }

    const searchName = searchInput.name || 'search';
    const currentValue = searchInput.value;
    const fieldContainer = searchInput.parentElement;

    searchInput.remove();

    if (fieldContainer && !fieldContainer.querySelector('input, select, textarea, button, a')) {
        fieldContainer.remove();
    }

    // Keep the current search term when filters are applied from the modal.
    const searchMirror = document.createElement('input');
    searchMirror.type = 'hidden';
    searchMirror.name = searchName;
    searchMirror.value = currentValue;
    filterForm.appendChild(searchMirror);

    const liveSearchForm = document.createElement('form');
    liveSearchForm.method = 'GET';
    liveSearchForm.action = filterForm.action;
    liveSearchForm.className = 'live-table-search';
    liveSearchForm.dataset.filterPanel = 'false';
    liveSearchForm.setAttribute('role', 'search');

    const query = new URLSearchParams(window.location.search);

    query.forEach((value, name) => {
        if (!name || name === searchName || name === 'page') {
            return;
        }

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = name;
        hiddenInput.value = value;
        liveSearchForm.appendChild(hiddenInput);
    });

    const accessibleLabel = document.createElement('label');
    const searchId = `live-table-search-${index + 1}`;
    accessibleLabel.className = 'sr-only';
    accessibleLabel.htmlFor = searchId;
    accessibleLabel.textContent = 'Search records';

    searchInput.id = searchId;
    searchInput.type = 'search';
    searchInput.classList.add('live-table-search-input');
    searchInput.setAttribute('autocomplete', 'off');
    searchInput.setAttribute('aria-label', 'Search records');

    const field = document.createElement('div');
    field.className = 'live-table-search-field';

    const searchIcon = document.createElement('span');
    searchIcon.className = 'live-table-search-icon';
    searchIcon.setAttribute('aria-hidden', 'true');
    searchIcon.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"></circle>
            <path stroke-linecap="round" d="m20 20-3.5-3.5"></path>
        </svg>
    `;

    const clearButton = document.createElement('button');
    clearButton.type = 'button';
    clearButton.className = 'live-table-search-clear';
    clearButton.setAttribute('aria-label', 'Clear search');
    clearButton.hidden = currentValue === '';
    clearButton.innerHTML = `
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"></path>
        </svg>
    `;

    field.append(searchIcon, searchInput, clearButton);
    liveSearchForm.append(accessibleLabel, field);

    const focusStorageKey = `healthlink.live-search-focus:${window.location.pathname}`;
    let debounceTimer = null;
    let isComposing = false;

    const rememberFocus = () => {
        try {
            window.sessionStorage.setItem(focusStorageKey, '1');
        } catch (error) {
            // Searching must still work when browser storage is unavailable.
        }
    };

    const submitSearch = () => {
        rememberFocus();
        liveSearchForm.requestSubmit();
    };

    const scheduleSearch = () => {
        searchMirror.value = searchInput.value;
        clearButton.hidden = searchInput.value === '';
        window.clearTimeout(debounceTimer);

        if (!isComposing) {
            debounceTimer = window.setTimeout(submitSearch, 400);
        }
    };

    searchInput.addEventListener('input', scheduleSearch);
    searchInput.addEventListener('compositionstart', () => {
        isComposing = true;
        window.clearTimeout(debounceTimer);
    });
    searchInput.addEventListener('compositionend', () => {
        isComposing = false;
        scheduleSearch();
    });
    liveSearchForm.addEventListener('submit', () => {
        window.clearTimeout(debounceTimer);
        rememberFocus();
    });
    liveSearchForm.addEventListener('live-search:cancel', () => {
        window.clearTimeout(debounceTimer);
    });
    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        searchMirror.value = '';
        clearButton.hidden = true;
        submitSearch();
    });

    try {
        if (window.sessionStorage.getItem(focusStorageKey) === '1') {
            window.sessionStorage.removeItem(focusStorageKey);
            window.setTimeout(() => {
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }, 0);
        }
    } catch (error) {
        // Focus restoration is an enhancement and should never block filtering.
    }

    return liveSearchForm;
}

function formHasVisibleFilterControls(form) {
    return Array.from(form.elements).some((control) => (
        (control instanceof HTMLInputElement && !['hidden', 'submit', 'button'].includes(control.type))
        || control instanceof HTMLSelectElement
        || control instanceof HTMLTextAreaElement
    ));
}

function initializeFilterModals() {
    const filterForms = document.querySelectorAll('form[method="get" i]:not([data-filter-panel="false"])');

    filterForms.forEach((form, index) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.filterPanelInitialized === 'true') {
            return;
        }

        // Only convert forms that actually collect filter values. This keeps simple GET actions untouched.
        if (!form.querySelector('input, select, textarea')) {
            return;
        }

        form.dataset.filterPanelInitialized = 'true';

        const panelId = `filter-modal-${index + 1}`;
        const originalParent = form.parentElement;
        const existingSidebar = form.closest('aside');
        const layout = existingSidebar?.parentElement;
        const standaloneContainer = existingSidebar ? null : findStandaloneFilterContainer(form);
        const controlsArea = document.createElement('div');
        controlsArea.className = 'data-table-controls';
        const liveSearch = createLiveTableSearch(form, index);
        const triggerArea = document.createElement('div');
        triggerArea.className = 'filter-modal-trigger-area';
        const trigger = document.createElement('button');

        trigger.type = 'button';
        trigger.className = 'filter-modal-trigger';
        trigger.setAttribute('aria-controls', panelId);
        trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = `
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10m-7 6h4" />
            </svg>
            <span>Filter</span>
        `;

        triggerArea.appendChild(trigger);

        if (liveSearch) {
            controlsArea.appendChild(liveSearch);
        }

        if (!formHasVisibleFilterControls(form)) {
            if (existingSidebar instanceof HTMLElement) {
                layout?.classList.add('filter-modal-layout');
                existingSidebar.before(controlsArea);
                existingSidebar.remove();
            } else if (standaloneContainer instanceof HTMLElement && standaloneContainer !== form) {
                standaloneContainer.replaceWith(controlsArea);
            } else if (originalParent instanceof HTMLElement) {
                originalParent.appendChild(controlsArea);
                form.remove();
            }

            return;
        }

        controlsArea.appendChild(triggerArea);

        const activeSummary = buildActiveFilterSummary(form);

        if (activeSummary) {
            triggerArea.classList.add('has-active-filters');
            triggerArea.appendChild(activeSummary);
        }

        const modal = document.createElement('div');
        modal.id = panelId;
        modal.className = 'filter-modal-shell';
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="filter-modal-backdrop" data-filter-modal-close></div>
            <section class="filter-modal-panel" role="dialog" aria-modal="true" aria-labelledby="${panelId}-title">
                <div class="filter-modal-header">
                    <div>
                        <p class="filter-modal-eyebrow">Refine results</p>
                        <h2 id="${panelId}-title" class="filter-modal-title">Filters</h2>
                    </div>
                    <button type="button" class="filter-modal-close" data-filter-modal-close aria-label="Close filters">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>
                <div class="filter-modal-body"></div>
            </section>
        `;

        const panelBody = modal.querySelector('.filter-modal-body');
        const closeButton = modal.querySelector('.filter-modal-close');

        if (!(panelBody instanceof HTMLElement) || !(closeButton instanceof HTMLButtonElement)) {
            return;
        }

        form.classList.add('filter-modal-form');
        panelBody.appendChild(form);
        document.body.appendChild(modal);

        if (existingSidebar instanceof HTMLElement) {
            layout?.classList.add('filter-modal-layout');
            layout?.before(controlsArea);
            existingSidebar.remove();
        } else if (standaloneContainer instanceof HTMLElement && standaloneContainer !== form) {
            standaloneContainer.replaceWith(controlsArea);
        } else if (originalParent instanceof HTMLElement) {
            originalParent.appendChild(controlsArea);
        }

        let returnFocusElement = null;

        const setOpen = (isOpen) => {
            modal.classList.toggle('is-open', isOpen);
            modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            document.body.classList.toggle('filter-modal-open', isOpen);

            if (isOpen) {
                returnFocusElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                window.setTimeout(() => {
                    const firstField = panelBody.querySelector('input:not([type="hidden"]), select, textarea, button');
                    firstField?.focus();
                }, 0);
            } else {
                returnFocusElement?.focus();
            }
        };

        trigger.addEventListener('click', () => {
            liveSearch?.dispatchEvent(new Event('live-search:cancel'));
            setOpen(true);
        });
        modal.querySelectorAll('[data-filter-modal-close]').forEach((button) => {
            button.addEventListener('click', () => setOpen(false));
        });
        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false);
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                event.preventDefault();
                setOpen(false);
            }
        });
    });
}

Alpine.data('actionConfirmationModal', () => ({
    open: false,
    form: null,
    submitter: null,
    returnFocusElement: null,
    title: 'Are you sure?',
    description: 'Do you want to continue?',
    eyebrow: 'Please confirm',
    eyebrowClass: 'text-tubigon',
    confirmLabel: 'Confirm',
    confirmTone: 'primary',
    requiresReason: false,
    reasonName: 'action_reason',
    reasonLabel: 'Reason for this action',
    reasonPlaceholder: 'Type your reason here.',
    reason: '',
    confirmationWord: '',
    confirmationPhrase: '',
    errorMessage: '',
    isSubmitting: false,
    submitHandler: null,

    init() {
        this.submitHandler = (event) => this.handleFormSubmission(event);
        document.addEventListener('submit', this.submitHandler, true);
    },

    destroy() {
        if (this.submitHandler) {
            document.removeEventListener('submit', this.submitHandler, true);
        }
    },

    handleFormSubmission(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.confirmBypass === '1') {
            return;
        }

        const submitter = event.submitter instanceof HTMLElement
            ? event.submitter
            : form.querySelector('button[type="submit"], input[type="submit"]');

        if (!this.shouldConfirm(form, submitter)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        this.present(form, submitter instanceof HTMLElement ? submitter : null);
    },

    shouldConfirm(form, submitter) {
        if (form.matches('[data-confirm-skip]') || submitter?.matches('[data-confirm-skip]')) {
            return false;
        }

        const action = this.resolveAction(form, submitter);
        const effectiveMethod = this.resolveMethod(form, submitter);

        if (form.hasAttribute('data-confirm')) {
            return true;
        }

        if (effectiveMethod === 'GET') {
            return false;
        }

        return ![
            /\/login\/?$/,
            /\/logout\/?$/,
            /\/register\/?$/,
            /\/forgot-password\/?$/,
            /\/reset-password\/?$/,
            /\/email\/verification-notification\/?$/,
            /\/notifications(?:\/|$)/,
        ].some((matcher) => matcher.test(action.pathname));
    },

    present(form, submitter) {
        const config = this.buildConfig(form, submitter);

        this.form = form;
        this.submitter = submitter;
        this.returnFocusElement = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        this.title = config.title;
        this.description = config.description;
        this.eyebrow = config.eyebrow;
        this.eyebrowClass = config.eyebrowClass;
        this.confirmLabel = config.confirmLabel;
        this.confirmTone = config.confirmTone;
        this.requiresReason = config.requiresReason;
        this.reasonName = config.reasonName;
        this.reasonLabel = config.reasonLabel;
        this.reasonPlaceholder = config.reasonPlaceholder;
        this.confirmationWord = config.confirmationWord;
        this.reason = this.readExistingValue(form, config.reasonName);
        this.confirmationPhrase = '';
        this.errorMessage = '';
        this.isSubmitting = false;
        this.open = true;

        this.$nextTick(() => {
            const field = this.requiresReason
                ? this.$refs.reasonInput
                : this.confirmationWord
                    ? this.$refs.phraseInput
                    : this.$refs.cancelButton;

            field?.focus();
        });
    },

    buildConfig(form, submitter) {
        const action = this.resolveAction(form, submitter);
        const path = action.pathname.toLowerCase();
        const method = this.resolveMethod(form, submitter);
        const submittedLabel = submitter?.textContent?.trim() || submitter?.getAttribute('value')?.trim() || 'Confirm';
        const recordName = this.inferRecordName(path);
        const explicitReasonName = form.dataset.confirmReasonName || submitter?.dataset.confirmReasonName;
        const inferredReasonName = this.inferReasonName(form);
        const config = {
            title: form.dataset.confirmTitle || `Save changes to this ${recordName}?`,
            description: form.dataset.confirmDescription || 'Your changes will be saved.',
            eyebrow: form.dataset.confirmEyebrow || 'Please confirm',
            eyebrowClass: 'text-tubigon',
            confirmLabel: form.dataset.confirmLabel || submittedLabel,
            confirmTone: 'primary',
            requiresReason: form.dataset.confirmReasonRequired === 'true',
            reasonName: explicitReasonName || inferredReasonName || 'action_reason',
            reasonLabel: form.dataset.confirmReasonLabel || 'Reason for this action',
            reasonPlaceholder: form.dataset.confirmReasonPlaceholder || 'Type your reason here.',
            confirmationWord: form.dataset.confirmWord || '',
        };

        if (path.includes('/reject')) {
            config.title = form.dataset.confirmTitle || `Reject this ${recordName}?`;
            config.description = form.dataset.confirmDescription || 'This request will not be approved.';
            config.eyebrow = form.dataset.confirmEyebrow || 'Please confirm';
            config.eyebrowClass = 'text-rose-700';
            config.confirmLabel = form.dataset.confirmLabel || 'Reject request';
            config.confirmTone = 'danger';
            config.requiresReason = true;
            config.reasonLabel = form.dataset.confirmReasonLabel || 'Reason for rejection';
            config.reasonPlaceholder = form.dataset.confirmReasonPlaceholder || 'Type the reason for rejecting this request.';
        } else if (path.includes('/approve')) {
            config.title = form.dataset.confirmTitle || `Approve this ${recordName}?`;
            config.description = form.dataset.confirmDescription || 'This request will be approved.';
            config.eyebrow = form.dataset.confirmEyebrow || 'Please confirm';
            config.eyebrowClass = 'text-emerald-700';
            config.confirmLabel = form.dataset.confirmLabel || 'Approve request';
            config.confirmTone = 'success';
        } else if (method === 'DELETE' || /(\/destroy|\/delete|\/purge|\/clear-old|\/revoke)/.test(path)) {
            if (path.includes('/clear-old')) {
                config.title = form.dataset.confirmTitle || 'Remove old logs?';
                config.description = form.dataset.confirmDescription || 'Old logs will be removed.';
            } else if (path.includes('/revoke-all')) {
                config.title = form.dataset.confirmTitle || 'Revoke all devices for this user?';
                config.description = form.dataset.confirmDescription || 'Those devices will no longer be able to use HealthLink.';
            } else if (path.includes('/revoke')) {
                config.title = form.dataset.confirmTitle || 'Revoke this device?';
                config.description = form.dataset.confirmDescription || 'This device will no longer be able to use HealthLink.';
            } else if (path.includes('/purge')) {
                config.title = form.dataset.confirmTitle || `Permanently delete this ${recordName}?`;
                config.description = form.dataset.confirmDescription || 'This cannot be undone.';
            } else {
                config.title = form.dataset.confirmTitle || `Delete this ${recordName}?`;
                config.description = form.dataset.confirmDescription || 'This cannot be undone.';
            }
            config.eyebrow = form.dataset.confirmEyebrow || 'Please confirm';
            config.eyebrowClass = 'text-rose-700';
            config.confirmLabel = form.dataset.confirmLabel || submittedLabel;
            config.confirmTone = 'danger';
        } else if (path.includes('/restore')) {
            config.title = form.dataset.confirmTitle || `Restore this ${recordName}?`;
            config.description = form.dataset.confirmDescription || `This ${recordName} will be active again.`;
            config.eyebrow = form.dataset.confirmEyebrow || 'Please confirm';
            config.eyebrowClass = 'text-amber-700';
            config.confirmTone = 'warning';
        } else if (/^(activate|deactivate)$/i.test(submittedLabel)) {
            const isActivating = /^activate$/i.test(submittedLabel);

            config.title = form.dataset.confirmTitle || `${submittedLabel} this ${recordName}?`;
            config.description = form.dataset.confirmDescription || (isActivating
                ? `This ${recordName} will be active again.`
                : `This ${recordName} will be marked inactive.`);
            config.eyebrow = form.dataset.confirmEyebrow || 'Please confirm';
            config.eyebrowClass = isActivating ? 'text-emerald-700' : 'text-amber-700';
            config.confirmTone = isActivating ? 'success' : 'warning';
        } else if (/(\/store|\/create|\/update|\/edit|\/save|\/submit|\/complete|\/escalate)/.test(path) || ['PUT', 'PATCH'].includes(method)) {
            config.title = form.dataset.confirmTitle || `Save changes to this ${recordName}?`;
            config.description = form.dataset.confirmDescription || 'Your changes will be saved.';
            config.eyebrow = form.dataset.confirmEyebrow || 'Please confirm';
        } else {
            config.title = form.dataset.confirmTitle || `Apply this ${recordName}?`;
            config.description = form.dataset.confirmDescription || 'This change will be applied.';
            config.eyebrow = form.dataset.confirmEyebrow || 'Please confirm';
        }

        return config;
    },

    resolveAction(form, submitter) {
        const action = (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)
            && submitter.hasAttribute('formaction')
            ? submitter.getAttribute('formaction')
            : form.action;

        return new URL(action || window.location.href, window.location.href);
    },

    resolveMethod(form, submitter) {
        const submitterMethod = (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)
            && submitter.hasAttribute('formmethod')
            ? submitter.getAttribute('formmethod')
            : '';
        const spoofedMethod = form.querySelector('input[name="_method"]')?.value;

        return String(submitterMethod || spoofedMethod || form.method || 'GET').toUpperCase();
    },

    inferRecordName(path) {
        if (path.includes('resident')) return 'resident';
        if (path.includes('household')) return 'household';
        if (path.includes('barangay')) return 'barangay';
        if (path.includes('purok')) return 'purok';
        if (path.includes('backup')) return 'backup';
        if (path.includes('archive')) return 'archive';
        if (path.includes('device')) return 'device';
        if (path.includes('release')) return 'mobile app release';
        if (path.includes('certificate')) return 'certificate';
        if (path.includes('campaign')) return 'campaign task';
        if (path.includes('triage')) return 'triage record';
        if (path.includes('encounter')) return 'consultation record';
        if (path.includes('measurement')) return 'measurement';
        if (path.includes('feeding')) return 'feeding record';
        if (path.includes('maternal')) return 'maternal record';
        if (path.includes('draft')) return 'draft';
        if (path.includes('request')) return 'request';
        if (path.includes('user') || path.includes('team')) return 'user';

        return 'record';
    },

    inferReasonName(form) {
        const existingField = form.querySelector('[data-confirm-reason-input], [name="approval_notes"], [name="review_notes"], [name="action_reason"]');

        return existingField instanceof HTMLInputElement || existingField instanceof HTMLTextAreaElement
            ? existingField.name
            : '';
    },

    readExistingValue(form, fieldName) {
        if (!fieldName) {
            return '';
        }

        const field = form.querySelector(`[name="${CSS.escape(fieldName)}"]`);

        return field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement
            ? field.value
            : '';
    },

    writeHiddenValue(fieldName, value) {
        if (!this.form || !fieldName) {
            return;
        }

        let field = this.form.querySelector(`[name="${CSS.escape(fieldName)}"]`);

        if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = fieldName;
            this.form.appendChild(field);
        }

        field.value = value;
    },

    confirm() {
        if (!this.form) {
            return;
        }

        if (this.requiresReason && !this.reason.trim()) {
            this.errorMessage = 'Provide a reason before continuing.';
            this.$nextTick(() => this.$refs.reasonInput?.focus());
            return;
        }

        if (this.confirmationWord && this.confirmationPhrase.trim().toUpperCase() !== this.confirmationWord.toUpperCase()) {
            this.errorMessage = `Type ${this.confirmationWord} exactly to continue.`;
            this.$nextTick(() => this.$refs.phraseInput?.focus());
            return;
        }

        this.errorMessage = '';
        this.isSubmitting = true;
        this.writeHiddenValue(this.reasonName, this.reason.trim());

        if (this.confirmationWord) {
            this.writeHiddenValue('confirmation_phrase', this.confirmationPhrase.trim());
        }

        const action = this.resolveAction(this.form, this.submitter);
        const method = this.resolveMethod(this.form, this.submitter);

        this.form.dataset.confirmBypass = '1';
        this.form.action = action.toString();
        this.form.method = method === 'GET' ? 'GET' : 'POST';

        HTMLFormElement.prototype.submit.call(this.form);
    },

    cancel() {
        this.open = false;
        this.errorMessage = '';
        this.isSubmitting = false;

        this.$nextTick(() => this.returnFocusElement?.focus());
    },

    trapFocus(event) {
        if (!this.open || event.key !== 'Tab' || !this.$refs.dialog) {
            return;
        }

        const focusable = [...this.$refs.dialog.querySelectorAll(
            'button:not([disabled]), textarea:not([disabled]), input:not([disabled])',
        )].filter((element) => element.offsetParent !== null);

        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
}));

initializeFilterModals();
Alpine.start();
initializeProgressivePurokFilters();
