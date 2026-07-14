

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

Alpine.start();
