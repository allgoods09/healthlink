

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

Alpine.start();
initializeProgressivePurokFilters();
