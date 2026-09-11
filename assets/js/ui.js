(function () {
    'use strict';

    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggle = document.getElementById('menuToggle');
    var closeButton = document.getElementById('sidebarClose');
    var dateEl = document.getElementById('headerDate');
    var mobileQuery = window.matchMedia('(max-width: 900px)');
    var storageKey = 'pcmsSidebarCollapsedV2';

    if (dateEl) {
        dateEl.textContent = new Date().toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function savedDesktopState() {
        try {
            var savedState = window.localStorage.getItem(storageKey);
            return savedState === null ? true : savedState === 'true';
        } catch (error) {
            return true;
        }
    }

    function saveDesktopState(isCollapsed) {
        try {
            window.localStorage.setItem(storageKey, isCollapsed ? 'true' : 'false');
        } catch (error) {
            // The navigation remains usable when browser storage is unavailable.
        }
    }

    function updateNavigationAccessibility(isOpen) {
        if (toggle) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggle.setAttribute('aria-label', isOpen ? 'Hide navigation' : 'Show navigation');
            toggle.setAttribute('title', isOpen ? 'Hide navigation' : 'Show navigation');
        }

        if (sidebar) {
            sidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            sidebar.inert = !isOpen;
        }

        if (overlay) {
            overlay.setAttribute('aria-hidden', isOpen && mobileQuery.matches ? 'false' : 'true');
        }
    }

    function isSidebarOpen() {
        if (!sidebar) return false;

        return mobileQuery.matches
            ? sidebar.classList.contains('open')
            : !document.body.classList.contains('sidebar-collapsed');
    }

    function closeSidebar() {
        if (!sidebar) return;

        if (mobileQuery.matches) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('visible');
            document.body.classList.remove('sidebar-mobile-open');
        } else {
            document.body.classList.add('sidebar-collapsed');
            saveDesktopState(true);
        }

        updateNavigationAccessibility(false);
    }

    function openSidebar() {
        if (!sidebar) return;

        if (mobileQuery.matches) {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('visible');
            document.body.classList.add('sidebar-mobile-open');
        } else {
            document.body.classList.remove('sidebar-collapsed');
            saveDesktopState(false);
        }

        updateNavigationAccessibility(true);
    }

    function applyViewportState() {
        if (!sidebar) return;

        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('visible');
        document.body.classList.remove('sidebar-mobile-open');

        if (mobileQuery.matches) {
            document.body.classList.remove('sidebar-collapsed');
            updateNavigationAccessibility(false);
            return;
        }

        document.body.classList.toggle('sidebar-collapsed', savedDesktopState());
        updateNavigationAccessibility(!document.body.classList.contains('sidebar-collapsed'));
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isSidebarOpen()) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    if (closeButton) {
        closeButton.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isSidebarOpen()) {
            closeSidebar();
            if (toggle) toggle.focus();
        }
    });

    if (sidebar) {
        sidebar.addEventListener('click', function (event) {
            if (mobileQuery.matches && event.target.closest('a')) {
                closeSidebar();
            }
        });
    }

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', applyViewportState);
    } else {
        mobileQuery.addListener(applyViewportState);
    }

    applyViewportState();
})();
