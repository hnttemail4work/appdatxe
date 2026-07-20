/**
 * Driver dashboard — chuyển tab tức thì, cập nhật URL không reload.
 */
(function () {
    var root = document.querySelector('.driver-page[data-driver-tabs]');
    if (!root) {
        return;
    }

    var baseUrl = root.dataset.driverTabsBase || window.location.pathname;
    var validTabs = [
        'trips', 'history', 'earnings', 'wallet',
        'account', 'account-update',
        'invite', 'customers', 'inbox', 'settings',
    ];
    var activeTab = root.dataset.driverTabsActive || 'trips';

    function isValidTab(tab) {
        if (tab === 'requests' || tab === 'deposit' || tab === 'settings-docs' || tab === 'account-password') {
            return true;
        }

        return validTabs.indexOf(tab) !== -1;
    }

    function canOpenTab() {
        return true;
    }

    function normalizeTab(tab) {
        if (tab === 'requests') {
            return 'trips';
        }
        if (tab === 'deposit') {
            return 'earnings';
        }
        if (tab === 'settings-docs' || tab === 'account-profile') {
            return 'account-update';
        }
        if (tab === 'account-password') {
            return 'account';
        }
        return tab;
    }

    function tabFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var tab = normalizeTab(params.get('tab') || 'trips');
        return isValidTab(tab) ? tab : 'trips';
    }

    function buildUrl(tab) {
        tab = normalizeTab(tab);
        var params = new URLSearchParams(window.location.search);
        if (tab === 'trips') {
            params.delete('tab');
        } else {
            params.set('tab', tab);
        }
        var query = params.toString();
        return query ? (baseUrl + '?' + query) : baseUrl;
    }

    function setDrawerActive(tab) {
        document.querySelectorAll('.driver-drawer__link[data-driver-tab]').forEach(function (item) {
            item.classList.toggle('is-active', item.dataset.driverTab === tab);
        });
    }

    function setDockActive(tab) {
        var dockTab = (tab === 'invite' || tab === 'customers' || tab === 'wallet' || tab === 'settings')
            ? ''
            : (tab.indexOf('account') === 0 ? 'account' : tab);
        document.querySelectorAll('.driver-dock-item[data-driver-tab]').forEach(function (item) {
            var isActive = dockTab !== '' && item.dataset.driverTab === dockTab;
            item.classList.toggle('is-active', isActive);
            if (isActive) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });
    }

    function switchTab(tab, options) {
        options = options || {};
        tab = normalizeTab(tab);
        if (!isValidTab(tab)) {
            return;
        }

        if (!canOpenTab(tab)) {
            return;
        }

        if (tab === activeTab && options.force !== true) {
            if (window.DriverShell && window.DriverShell.syncOverlay) {
                window.DriverShell.syncOverlay(tab);
            }
            setDockActive(tab);
            root.dispatchEvent(new CustomEvent('drivertab:changed', {
                detail: { tab: tab },
            }));
            return;
        }

        activeTab = tab;
        root.dataset.driverTabsActive = tab;

        document.querySelectorAll('.driver-tab-pane').forEach(function (pane) {
            var isActive = pane.dataset.driverTab === tab;
            pane.classList.toggle('is-active', isActive);
            pane.hidden = !isActive;
        });

        setDrawerActive(tab);
        setDockActive(tab);

        if (options.pushState !== false) {
            window.history.pushState({ driverTab: tab }, '', buildUrl(tab));
        }

        root.dispatchEvent(new CustomEvent('drivertab:changed', {
            detail: { tab: tab },
        }));
    }

    document.querySelectorAll('[data-driver-tab]').forEach(function (trigger) {
        // Pane dùng data-driver-tab để nhận diện nội dung — không gắn click,
        // tránh bubble từ nút menu con (account-profile…) kéo về tab cha.
        if (trigger.classList.contains('driver-tab-pane')) {
            return;
        }

        trigger.addEventListener('click', function (event) {
            var tab = normalizeTab(trigger.dataset.driverTab || '');
            if (!isValidTab(tab)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            switchTab(tab);
        });
    });

    window.addEventListener('popstate', function (event) {
        var tab = (event.state && event.state.driverTab) || tabFromUrl();
        switchTab(tab, { pushState: false });
    });

    var initialTab = tabFromUrl();
    var forceOfferTab = !!(document.querySelector('.driver-page.is-offer-pending')
        || document.querySelector('[data-trip-request-id]:not([data-removing="1"])'));
    // Có cuốc chờ nhận / từ chối → luôn mở tab chuyến trước.
    if (forceOfferTab) {
        initialTab = 'trips';
    }
    if (initialTab !== activeTab) {
        switchTab(initialTab, { pushState: false });
    } else {
        setDrawerActive(activeTab);
        setDockActive(activeTab);
        if (window.DriverShell && window.DriverShell.syncOverlay) {
            window.DriverShell.syncOverlay(activeTab);
        }
    }

    if (forceOfferTab || !window.history.state || !window.history.state.driverTab) {
        window.history.replaceState({ driverTab: activeTab }, '', buildUrl(activeTab));
    }

    window.DriverTabs = {
        switchTab: switchTab,
        getActiveTab: function () {
            return activeTab;
        },
    };

    function updateTripDockBadge() {
        var panel = document.getElementById('driver-bottom-panel');
        var total = document.querySelectorAll('[data-trip-request-id]:not([data-removing="1"])').length
            + document.querySelectorAll('#driver-trips-list [data-schedule-id]').length;

        if (panel) {
            panel.dataset.hasLiveTrips = total > 0 ? '1' : '0';
        }

        if (window.DriverBottomPanel && window.DriverBottomPanel.refresh) {
            window.DriverBottomPanel.refresh();
        }

        var dockItem = document.querySelector('.driver-dock-item[data-driver-tab="trips"]');
        if (!dockItem) {
            return;
        }

        var badge = dockItem.querySelector('.driver-dock-badge');
        if (total > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'driver-dock-badge is-hot';
                dockItem.appendChild(badge);
            }
            badge.textContent = String(total);
            badge.classList.add('is-hot');
            badge.classList.remove('d-none');
            return;
        }

        if (badge) {
            badge.remove();
        }
    }

    window.__driverUpdateTripDockBadge = updateTripDockBadge;
    updateTripDockBadge();

    /** Sub-tab Hồ sơ / Giấy tờ trong «Cập nhật thông tin». */
    (function initDriverUpdateTabs() {
        var panel = document.querySelector('[data-driver-update-panel]');
        if (!panel) {
            return;
        }

        function showUpdateTab(key) {
            key = key === 'docs' ? 'docs' : 'profile';
            panel.querySelectorAll('[data-driver-update-tab]').forEach(function (btn) {
                var on = btn.getAttribute('data-driver-update-tab') === key;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panel.querySelectorAll('[data-driver-update-pane]').forEach(function (pane) {
                var on = pane.getAttribute('data-driver-update-pane') === key;
                pane.classList.toggle('is-active', on);
                pane.hidden = !on;
            });

            var params = new URLSearchParams(window.location.search);
            if (key === 'profile') {
                params.delete('update_tab');
            } else {
                params.set('update_tab', 'docs');
            }
            var query = params.toString();
            var url = (baseUrl || window.location.pathname) + (query ? ('?' + query) : '');
            window.history.replaceState(window.history.state || { driverTab: 'account-update' }, '', url);
        }

        panel.querySelectorAll('[data-driver-update-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                showUpdateTab(btn.getAttribute('data-driver-update-tab') || 'profile');
            });
        });
    })();
})();
