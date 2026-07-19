(function () {
    var root = document.getElementById('admin-bookings-sync-root');
    if (!root) {
        return;
    }

    var syncUrl = root.dataset.adminBookingsSyncUrl;
    if (!syncUrl) {
        return;
    }

    var listPanel = document.getElementById('admin-bookings-list-panel');
    var syncStatus = document.getElementById('admin-bookings-sync-status');
    var pollMs = parseInt(root.dataset.adminBookingsPollMs || '15000', 10) || 15000;
    var syncing = false;
    var timerId = null;

    function currentList() {
        return root.dataset.bookingList || 'active';
    }

    function buildSyncUrl() {
        var params = new URLSearchParams(window.location.search);
        if (!params.get('list')) {
            params.set('list', currentList());
        }
        var qs = params.toString();

        return qs ? (syncUrl + '?' + qs) : syncUrl;
    }

    function shouldPauseSync() {
        if (document.hidden) {
            return true;
        }

        var active = document.activeElement;
        if (!active || !root.contains(active)) {
            return false;
        }

        var tag = (active.tagName || '').toLowerCase();
        if (tag === 'select' || tag === 'input' || tag === 'textarea' || tag === 'button') {
            return true;
        }

        return !!active.closest('.admin-booking-action-form, .admin-booking-actions, [data-confirm]');
    }

    function updateTabBadges(counts) {
        if (!counts) {
            return;
        }

        Object.keys(counts).forEach(function (key) {
            var tab = document.querySelector('.admin-booking-list-tabs [data-booking-tab="' + key + '"]');
            if (!tab) {
                return;
            }

            var count = parseInt(counts[key], 10) || 0;
            var badge = tab.querySelector('[data-booking-tab-count]');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'status-pill status-pill--' + (key === currentList() ? 'accent' : 'neutral') + ' ms-1';
                    badge.setAttribute('data-booking-tab-count', '');
                    tab.appendChild(badge);
                }
                badge.textContent = String(count);
                badge.classList.remove('d-none');
            } else if (badge) {
                badge.remove();
            }
        });
    }

    function updateAlert(id, count, templateHtml) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }

        if (count > 0) {
            el.innerHTML = templateHtml.replace(':count', String(count));
            el.classList.remove('d-none');
            return;
        }

        el.classList.add('d-none');
        el.innerHTML = '';
    }

    function updateAlerts(data) {
        if (currentList() !== 'active') {
            ['admin-bookings-alert-offduty', 'admin-bookings-alert-late'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.classList.add('d-none');
                }
            });
            return;
        }

        updateAlert(
            'admin-bookings-alert-offduty',
            data.catalog_off_duty_count || 0,
            '<span class="fw-semibold">⚠</span><div><strong>:count đơn</strong> khách chọn tài xế nhưng tài xế đó <strong>chưa bật Sẵn sàng</strong> — xem cột <strong>Thời gian chờ</strong>.</div>',
        );

        updateAlert(
            'admin-bookings-alert-late',
            data.late_pickup_alert_count || 0,
            '<span class="fw-semibold">⏱</span><div><strong>:count chuyến</strong> có nguy cơ trễ hoặc đã quá giờ đón — xem cột <strong>Cảnh báo</strong> hoặc <strong>Thời gian chờ</strong>.</div>',
        );
    }

    function bindBulkDeleteControls() {
        document.dispatchEvent(new CustomEvent('admin-bookings:bulk-controls'));
    }

    function applySync(data) {
        if (!data || data.list !== currentList()) {
            return;
        }

        updateTabBadges(data.counts);
        updateAlerts(data);

        if (listPanel && typeof data.html === 'string') {
            listPanel.innerHTML = data.html;
            bindBulkDeleteControls();
        }

        if (syncStatus && data.synced_at) {
            syncStatus.textContent = 'Cập nhật ' + data.synced_at;
        }
    }

    function pollBookings() {
        if (syncing || shouldPauseSync()) {
            return;
        }

        syncing = true;
        if (syncStatus) {
            syncStatus.classList.add('is-syncing');
        }

        fetch(buildSyncUrl(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('sync failed');
                }
                return response.json();
            })
            .then(applySync)
            .catch(function () {
                /* im lặng — thử lại vòng sau */
            })
            .finally(function () {
                syncing = false;
                if (syncStatus) {
                    syncStatus.classList.remove('is-syncing');
                }
            });
    }

    function schedulePoll() {
        if (timerId) {
            window.clearInterval(timerId);
        }
        timerId = window.setInterval(pollBookings, pollMs);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            pollBookings();
        }
    });

    schedulePoll();
    window.setTimeout(pollBookings, 2000);
})();
