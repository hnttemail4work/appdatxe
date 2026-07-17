/**
 * Driver dashboard — chuyển tab tức thì, cập nhật URL không reload.
 */
(function () {
    var root = document.querySelector('.driver-page[data-driver-tabs]');
    if (!root) {
        return;
    }

    var baseUrl = root.dataset.driverTabsBase || window.location.pathname;
    var validTabs = ['trips', 'history', 'deposit', 'account'];
    var activeTab = root.dataset.driverTabsActive || 'trips';
    var mustChangePassword = root.dataset.mustChangePassword === '1';

    function isValidTab(tab) {
        if (tab === 'requests') {
            return true;
        }

        return validTabs.indexOf(tab) !== -1;
    }

    function canOpenTab(tab) {
        tab = normalizeTab(tab);
        if (!mustChangePassword) {
            return true;
        }

        return tab === 'account';
    }

    function normalizeTab(tab) {
        return tab === 'requests' ? 'trips' : tab;
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

    function setDockActive(tab) {
        document.querySelectorAll('.driver-dock-item[data-driver-tab]').forEach(function (item) {
            var isActive = item.dataset.driverTab === tab;
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
        if (!isValidTab(tab) || tab === activeTab) {
            return;
        }

        tab = normalizeTab(tab);
        if (!canOpenTab(tab)) {
            if (window.AppFlash && window.AppFlash.show) {
                window.AppFlash.show('Vui lòng đổi mật khẩu trước khi tiếp tục.', {
                    variant: 'warning',
                    title: 'Cần đổi mật khẩu',
                });
            }
            return;
        }
        activeTab = tab;
        root.dataset.driverTabsActive = tab;

        document.querySelectorAll('.driver-tab-pane').forEach(function (pane) {
            var isActive = pane.dataset.driverTab === tab;
            pane.classList.toggle('is-active', isActive);
            pane.hidden = !isActive;
        });

        setDockActive(tab);

        if (options.pushState !== false) {
            window.history.pushState({ driverTab: tab }, '', buildUrl(tab));
        }

        root.dispatchEvent(new CustomEvent('drivertab:changed', {
            detail: { tab: tab },
        }));
    }

    document.querySelectorAll('[data-driver-tab]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            var tab = normalizeTab(trigger.dataset.driverTab || '');
            if (!isValidTab(tab)) {
                return;
            }
            event.preventDefault();
            switchTab(tab);
        });
    });

    window.addEventListener('popstate', function (event) {
        var tab = (event.state && event.state.driverTab) || tabFromUrl();
        switchTab(tab, { pushState: false });
    });

    // Đồng bộ URL lần đầu (deep link ?tab=...)
    var initialTab = tabFromUrl();
    if (initialTab !== activeTab) {
        switchTab(initialTab, { pushState: false });
    } else {
        setDockActive(activeTab);
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
        var tab = document.querySelector('.driver-dock-item[data-driver-tab="trips"]');
        if (!tab) {
            return;
        }

        var badge = tab.querySelector('.driver-dock-badge');
        var total = document.querySelectorAll('[data-trip-request-id]:not([data-removing="1"])').length
            + document.querySelectorAll('#driver-trips-list [data-schedule-id]').length;

        if (total > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'driver-dock-badge is-hot';
                tab.appendChild(badge);
            }
            badge.textContent = String(total);
            badge.classList.remove('d-none');
            badge.classList.add('is-hot');
            return;
        }

        if (badge) {
            badge.remove();
        }
    }

    window.__driverUpdateTripDockBadge = updateTripDockBadge;
    updateTripDockBadge();
})();
