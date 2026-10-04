<script>
    window.healthlinkSidebarDesktopPreference = function () {
        try {
            const value = window.localStorage.getItem('healthlink.sidebar.desktop.open');
            return value === null ? null : value === '1';
        } catch (error) {
            return null;
        }
    };
    document.documentElement.dataset.sidebarDesktopOpen =
        (window.healthlinkSidebarDesktopPreference() ?? true) ? '1' : '0';
</script>
