export function sidebarLayout(win, sidebarContext = 'default', desktopBreakpoint = 1024) {
    return {
        storageKey: 'healthlink.sidebar.desktop.open',
        scrollStoragePrefix: 'healthlink.sidebar.scroll',
        sidebarContext,
        isDesktop: win.innerWidth >= desktopBreakpoint,
        sidebarOpen: win.innerWidth >= desktopBreakpoint,
        resizeHandler: null,
        scrollHandler: null,
        pageHideHandler: null,
        pageShowHandler: null,
        scrollFrame: null,
        restoreFrame: null,
        lastDesktopScrollTop: null,
        destroyed: false,

        init() {
            this.sidebarOpen = this.resolveInitialSidebarState();
            this.resizeHandler = () => {
                const desktop = win.innerWidth >= desktopBreakpoint;
                if (desktop !== this.isDesktop) {
                    // Responsive layout may already have clamped the container at this point.
                    if (!desktop && this.lastDesktopScrollTop !== null) {
                        this.cancelScrollFrame();
                        this.writeSidebarScroll(this.lastDesktopScrollTop);
                    }
                    this.isDesktop = desktop;
                    this.sidebarOpen = desktop ? this.getStoredDesktopPreference() ?? true : false;
                } else if (desktop) {
                    this.sidebarOpen = this.getStoredDesktopPreference() ?? true;
                }
            };
            win.addEventListener('resize', this.resizeHandler);
            this.$watch('sidebarOpen', open => {
                if (open) this.$nextTick(() => this.scheduleScrollRestore());
                else {
                    this.cancelScrollFrame();
                    this.cancelScrollRestore();
                }
            });
            this.pageHideHandler = () => this.persistSidebarScroll();
            this.pageShowHandler = event => {
                if (!event.persisted) return;
                this.resizeHandler();
                this.$nextTick(() => this.restoreSidebarScroll());
            };
            win.addEventListener('pagehide', this.pageHideHandler);
            win.addEventListener('pageshow', this.pageShowHandler);
            this.$nextTick(() => {
                if (this.destroyed) return;
                this.attachScrollListener();
                this.restoreSidebarScroll();
                // Remove the bootstrap override only after x-show and the content margin agree.
                this.$el.setAttribute('data-sidebar-ready', '');
            });
        },

        destroy() {
            this.persistSidebarScroll();
            this.destroyed = true;
            this.cancelScrollFrame();
            this.cancelScrollRestore();
            win.removeEventListener('resize', this.resizeHandler);
            win.removeEventListener('pagehide', this.pageHideHandler);
            win.removeEventListener('pageshow', this.pageShowHandler);
            this.$refs.sidebarScroll?.removeEventListener('scroll', this.scrollHandler);
        },

        toggleSidebar() {
            if (this.sidebarOpen) this.persistSidebarScroll();
            this.sidebarOpen = !this.sidebarOpen;
            this.persistDesktopPreference();
        },

        closeSidebar() {
            if (!this.isDesktop) this.sidebarOpen = false;
        },

        handleNavClick(event) {
            if (!event.target.closest('a[href]')) return;
            this.persistSidebarScroll();
            if (!this.isDesktop) this.sidebarOpen = false;
        },

        resolveInitialSidebarState() {
            return this.isDesktop ? this.getStoredDesktopPreference() ?? true : false;
        },

        persistDesktopPreference() {
            if (!this.isDesktop) return;
            try { win.localStorage.setItem(this.storageKey, this.sidebarOpen ? '1' : '0'); }
            catch { /* Storage failure must not prevent navigation. */ }
        },

        getStoredDesktopPreference() {
            if (win.healthlinkSidebarDesktopPreference) return win.healthlinkSidebarDesktopPreference();
            try {
                const value = win.localStorage.getItem(this.storageKey);
                return value === null ? null : value === '1';
            } catch { return null; }
        },

        usefulDesktopContainer() {
            const nav = this.$refs.sidebarScroll;
            return !this.destroyed && this.isDesktop && win.innerWidth >= desktopBreakpoint && this.sidebarOpen && nav
                && nav.clientHeight > 0 && nav.getClientRects().length > 0 ? nav : null;
        },

        attachScrollListener() {
            const nav = this.$refs.sidebarScroll;
            if (!nav || this.scrollHandler) return;
            this.scrollHandler = () => {
                if (!this.usefulDesktopContainer()) return;
                this.lastDesktopScrollTop = nav.scrollTop;
                if (this.scrollFrame !== null) return;
                this.scrollFrame = win.requestAnimationFrame(() => {
                    this.scrollFrame = null;
                    this.persistSidebarScroll();
                });
            };
            nav.addEventListener('scroll', this.scrollHandler, { passive: true });
        },

        cancelScrollFrame() {
            if (this.scrollFrame === null) return;
            win.cancelAnimationFrame(this.scrollFrame);
            this.scrollFrame = null;
        },

        scheduleScrollRestore() {
            if (this.destroyed || !this.isDesktop || !this.sidebarOpen) return;
            this.cancelScrollRestore();
            // Alpine reveals a reopened x-show sidebar on a frame, after nextTick.
            this.restoreFrame = win.requestAnimationFrame(() => {
                this.restoreFrame = null;
                this.restoreSidebarScroll();
            });
        },

        cancelScrollRestore() {
            if (this.restoreFrame === null) return;
            win.cancelAnimationFrame(this.restoreFrame);
            this.restoreFrame = null;
        },

        persistSidebarScroll() {
            this.cancelScrollFrame();
            const nav = this.usefulDesktopContainer();
            if (!nav) return;
            this.lastDesktopScrollTop = nav.scrollTop;
            this.writeSidebarScroll(nav.scrollTop);
        },

        writeSidebarScroll(value) {
            try { win.sessionStorage.setItem(this.scrollStorageKey(), String(value)); }
            catch { /* Leave the last useful position intact when storage is unavailable. */ }
        },

        restoreSidebarScroll() {
            const nav = this.usefulDesktopContainer();
            if (!nav) return;
            const max = Math.max(0, nav.scrollHeight - nav.clientHeight);
            const saved = this.getStoredSidebarScroll();
            if (saved !== null) nav.scrollTop = Math.max(0, Math.min(saved, max));
            const active = nav.querySelector('[aria-current="page"]');
            if (active) {
                const viewport = nav.getBoundingClientRect();
                const item = active.getBoundingClientRect();
                if (item.top < viewport.top || item.height > nav.clientHeight) {
                    nav.scrollTop = Math.max(0, Math.min(max, nav.scrollTop + item.top - viewport.top));
                } else if (item.bottom > viewport.bottom) {
                    nav.scrollTop = Math.max(0, Math.min(max, nav.scrollTop + item.bottom - viewport.bottom));
                }
            }
            this.lastDesktopScrollTop = nav.scrollTop;
        },

        getStoredSidebarScroll() {
            try {
                const value = win.sessionStorage.getItem(this.scrollStorageKey());
                const parsed = value === null ? NaN : Number(value);
                return Number.isFinite(parsed) ? parsed : null;
            } catch { return null; }
        },

        scrollStorageKey() {
            return `${this.scrollStoragePrefix}.${this.sidebarContext}`;
        },
    };
}
