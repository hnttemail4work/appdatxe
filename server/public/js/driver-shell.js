/**
 * Driver map shell — drawer menu, inbox modal, overlay panels.
 */
(function () {
    var page = document.querySelector('.driver-page--map');
    if (!page) {
        return;
    }

    var drawer = document.getElementById('driver-drawer');
    var drawerBackdrop = document.getElementById('driver-drawer-backdrop');
    var drawerOpenBtn = document.getElementById('driver-drawer-open');
    var drawerCloseBtn = document.getElementById('driver-drawer-close');

    var inbox = document.getElementById('driver-inbox-modal');
    var inboxBackdrop = document.getElementById('driver-inbox-backdrop');
    var inboxOpenBtn = document.getElementById('driver-inbox-open');
    var inboxCloseBtn = document.getElementById('driver-inbox-close');

    var overlay = document.getElementById('driver-overlay-panels');
    var overlayTitle = document.getElementById('driver-overlay-title');
    var tripSheet = document.getElementById('driver-trip-sheet');

    function setDrawerOpen(open) {
        if (!drawer || !drawerBackdrop) {
            return;
        }
        drawer.hidden = !open;
        drawerBackdrop.hidden = !open;
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('driver-drawer-open', open);
        if (drawerOpenBtn) {
            drawerOpenBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function setInboxOpen(open) {
        if (!inbox || !inboxBackdrop) {
            return;
        }
        inbox.hidden = !open;
        inboxBackdrop.hidden = !open;
        if (inboxOpenBtn) {
            inboxOpenBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function syncOverlay(tab) {
        var showOverlay = tab && tab !== 'trips';
        var backTab = (tab && tab.indexOf('account-') === 0) ? 'account' : 'trips';

        if (overlay) {
            overlay.hidden = !showOverlay;
            overlay.classList.toggle('is-open', showOverlay);
        }

        var overlayBack = overlay && overlay.querySelector('.driver-overlay-panels__back');
        if (overlayBack) {
            overlayBack.dataset.driverTab = backTab;
            overlayBack.setAttribute('aria-label', 'Quay lại');
        }
        if (overlayTitle) {
            overlayTitle.textContent = 'Quay lại';
            overlayTitle.dataset.driverTab = backTab;
        }

        page.classList.toggle('is-panel-open', showOverlay);

        if (window.DriverBottomPanel && window.DriverBottomPanel.refresh) {
            window.DriverBottomPanel.refresh();
            return;
        }

        if (tripSheet) {
            var hasContent = tripSheet.classList.contains('is-visible')
                || tripSheet.querySelector('[data-trip-request-id], [data-schedule-id]');
            var showSheet = !showOverlay && hasContent;
            tripSheet.hidden = !showSheet;
            tripSheet.classList.toggle('is-open', showSheet);
        }
    }

    if (drawerOpenBtn) {
        drawerOpenBtn.addEventListener('click', function () {
            setInboxOpen(false);
            setDrawerOpen(true);
        });
    }
    if (drawerCloseBtn) {
        drawerCloseBtn.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    }
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    }
    document.querySelectorAll('[data-driver-drawer-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    });

    // Chuông mở tab hộp thư qua data-driver-tab (driver-tabs.js).
    if (inboxCloseBtn) {
        inboxCloseBtn.addEventListener('click', function () {
            setInboxOpen(false);
        });
    }
    if (inboxBackdrop) {
        inboxBackdrop.addEventListener('click', function () {
            setInboxOpen(false);
        });
    }
    document.querySelectorAll('[data-driver-inbox-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            setInboxOpen(false);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        setDrawerOpen(false);
        setInboxOpen(false);
    });

    page.addEventListener('drivertab:changed', function (event) {
        var tab = (event.detail && event.detail.tab) || 'trips';
        setDrawerOpen(false);
        setInboxOpen(false);
        syncOverlay(tab);
    });

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-driver-open-chat]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        if (window.DriverInbox && window.DriverInbox.openChat) {
            window.DriverInbox.openChat(btn);
            return;
        }
        var ref = btn.getAttribute('data-driver-open-chat');
        if (!ref) {
            return;
        }
        var panel = document.querySelector('.trip-chat-panel[data-booking-reference="' + ref + '"]');
        if (!panel) {
            return;
        }
        var toggle = panel.querySelector('[data-chat-toggle]');
        if (toggle) {
            toggle.click();
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    var initialTab = page.dataset.driverTabsActive || 'trips';
    syncOverlay(initialTab);

    document.querySelectorAll('[data-driver-file-field]').forEach(function (wrap) {
        var input = wrap.querySelector('input[type="file"]');
        var nameEl = wrap.querySelector('[data-file-name]');
        var trigger = wrap.querySelector('[data-file-trigger]');
        if (!input || !nameEl) {
            return;
        }

        var fallback = input.getAttribute('data-file-default') || 'Chưa có ảnh';

        if (trigger) {
            trigger.addEventListener('click', function () {
                input.click();
            });
        }

        input.addEventListener('change', function () {
            var files = input.files;
            if (files && files.length > 1) {
                nameEl.textContent = files.length + ' ảnh';
                return;
            }
            if (files && files[0]) {
                nameEl.textContent = files[0].name;
                return;
            }
            nameEl.textContent = fallback;
        });
    });

    window.DriverShell = {
        setDrawerOpen: setDrawerOpen,
        setInboxOpen: setInboxOpen,
        syncOverlay: syncOverlay,
    };
})();
