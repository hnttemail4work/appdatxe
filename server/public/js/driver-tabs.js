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
        'account', 'account-profile', 'account-update', 'account-password',
        'invite', 'customers', 'inbox', 'settings',
    ];
    var activeTab = root.dataset.driverTabsActive || 'trips';
    var mustChangePassword = root.dataset.mustChangePassword === '1';

    function isValidTab(tab) {
        if (tab === 'requests' || tab === 'deposit' || tab === 'settings-docs') {
            return true;
        }

        return validTabs.indexOf(tab) !== -1;
    }

    function canOpenTab(tab) {
        tab = normalizeTab(tab);
        if (!mustChangePassword) {
            return true;
        }

        return tab === 'account' || tab === 'account-password';
    }

    function normalizeTab(tab) {
        if (tab === 'requests') {
            return 'trips';
        }
        if (tab === 'deposit') {
            return 'earnings';
        }
        if (tab === 'settings-docs') {
            return 'account-update';
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
            if (window.AppFlash && window.AppFlash.show) {
                window.AppFlash.show('Vui lòng đổi mật khẩu trước khi tiếp tục.', {
                    variant: 'warning',
                    title: 'Cần đổi mật khẩu',
                });
            }
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
    if (initialTab !== activeTab) {
        switchTab(initialTab, { pushState: false });
    } else {
        setDrawerActive(activeTab);
        setDockActive(activeTab);
        if (window.DriverShell && window.DriverShell.syncOverlay) {
            window.DriverShell.syncOverlay(activeTab);
        }
    }

    if (!window.history.state || !window.history.state.driverTab) {
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
})();
