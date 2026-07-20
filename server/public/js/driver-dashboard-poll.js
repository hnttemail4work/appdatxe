/**
 * Poll trang tài xế — cập nhật badge chuyến, khoảng cách đón; reload khi có thay đổi lớn.
 */
(function () {
    var url = window.__driverDashboardPollUrl;

    if (!url || !window.IdlePoll) {
        return;
    }

    var lastFingerprint = null;
    var lastPendingTripRequests = null;

    function tripsDashboardUrl() {
        try {
            var next = new URL(window.location.href);
            next.searchParams.delete('tab');
            return next.pathname + (next.search ? next.search : '');
        } catch (e) {
            return window.location.pathname;
        }
    }

    /** Ưu tiên màn Nhận/Từ chối khi hệ thống gán cuốc. */
    function focusOfferScreen() {
        if (window.DriverTabs && window.DriverTabs.switchTab) {
            window.DriverTabs.switchTab('trips', { force: true });
        }
        if (window.DriverBottomPanel && window.DriverBottomPanel.syncOfferPending) {
            window.DriverBottomPanel.syncOfferPending();
        }
    }

    function updateDockBadge(count) {
        var dock = document.querySelector('.driver-app-dock');
        if (!dock) {
            return;
        }

        var tripsBtn = dock.querySelector('[data-driver-tab="trips"]');
        if (!tripsBtn) {
            return;
        }

        var badge = tripsBtn.querySelector('.driver-dock-badge');
        var value = Number(count) || 0;

        if (value < 1) {
            if (badge) {
                badge.remove();
            }
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'driver-dock-badge is-hot';
            tripsBtn.appendChild(badge);
        }

        badge.textContent = String(value);
        badge.classList.toggle('is-hot', value > 0);
    }

    function pollDashboard() {
        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) {
                    return;
                }

                var pendingTripRequests = typeof data.pending_trip_requests === 'number'
                    ? data.pending_trip_requests
                    : 0;

                if (typeof data.trip_action_count === 'number') {
                    updateDockBadge(data.trip_action_count);
                    if (window.DriverSounds && window.DriverSounds.onTripCount) {
                        window.DriverSounds.onTripCount(data.trip_action_count);
                    }
                }

                if (data.inbox_unread) {
                    if (window.DriverSounds && window.DriverSounds.onInboxUnread) {
                        window.DriverSounds.onInboxUnread(data.inbox_unread);
                    } else if (window.AppSounds && window.AppSounds.onInboxUnread) {
                        window.AppSounds.onInboxUnread(data.inbox_unread);
                    }
                    if (window.DriverInbox && window.DriverInbox.updateBadges) {
                        window.DriverInbox.updateBadges(data.inbox_unread);
                    }
                }

                // Badge icon app ngoài màn hình: ưu tiên số cuốc chờ + unread hộp thư.
                if (window.AppInboxBadge && window.AppInboxBadge.syncAppIconBadge) {
                    var tripCount = typeof data.trip_action_count === 'number' ? data.trip_action_count : 0;
                    var inboxTotal = data.inbox_unread && typeof data.inbox_unread.total === 'number'
                        ? data.inbox_unread.total
                        : 0;
                    window.AppInboxBadge.syncAppIconBadge(Math.max(tripCount, inboxTotal));
                }

                // TODO (Fix Driver Toggle): Đồng bộ switch Hoạt động từ server — tránh UI lệch sau poll/reload.
                if (window.DriverAvailabilityToggle
                    && data.availability_status
                    && !document.body.dataset.driverTripActionBusy) {
                    var paused = data.availability_status === 'off_duty';
                    var locationBar = document.getElementById('driver-location-bar');
                    var isPaused = locationBar && locationBar.getAttribute('data-driver-paused') === '1';
                    if (paused !== isPaused) {
                        window.DriverAvailabilityToggle.setPaused(paused);
                    }
                }

                if (window.DriverLocationSave && Object.prototype.hasOwnProperty.call(data, 'pickup_proximity_line')) {
                    window.DriverLocationSave.setPickupProximityLine(data.pickup_proximity_line || '');
                }

                if (window.DriverLocationGps && window.DriverLocationGps.setTripTracking) {
                    window.DriverLocationGps.setTripTracking(
                        !!(data.driver_trip_active || data.driver_trip_upcoming || data.availability_status === 'on_trip')
                    );
                }

                if (window.TripActionFabs && window.TripActionFabs.setInTrip) {
                    window.TripActionFabs.setInTrip(
                        !!(data.driver_trip_active
                            || data.driver_trip_upcoming
                            || data.availability_status === 'on_trip')
                    );
                }

                if ((data.availability_status === 'available' || data.availability_status === 'on_trip')
                    && window.DriverLocationGps
                    && window.DriverLocationGps.ensureFreshLocation) {
                    window.DriverLocationGps.ensureFreshLocation();
                }

                // Có cuốc chờ nhận mà đang ở tab khác → kéo về màn chấp nhận / từ chối.
                if (pendingTripRequests > 0
                    && window.DriverTabs
                    && window.DriverTabs.getActiveTab
                    && window.DriverTabs.getActiveTab() !== 'trips') {
                    focusOfferScreen();
                }

                if (lastFingerprint === null) {
                    lastFingerprint = data.fingerprint || null;
                    lastPendingTripRequests = pendingTripRequests;
                    return;
                }

                if (data.fingerprint && data.fingerprint !== lastFingerprint && !window.IdlePoll.isBlocked()) {
                    var pendingIncreased = lastPendingTripRequests !== null
                        && pendingTripRequests > lastPendingTripRequests;
                    lastFingerprint = data.fingerprint;
                    lastPendingTripRequests = pendingTripRequests;

                    // Cuốc mới gán: reload thẳng tab chuyến để hiện full màn Nhận/Từ chối.
                    if (pendingIncreased || pendingTripRequests > 0) {
                        var tripsUrl = tripsDashboardUrl();
                        if (window.location.pathname + window.location.search !== tripsUrl) {
                            window.location.assign(tripsUrl);
                            return;
                        }
                    }

                    window.location.reload();
                    return;
                }

                lastPendingTripRequests = pendingTripRequests;
            })
            .catch(function () {});
    }

    // Poll ngay lần đầu — SOS / badge không chờ IdlePoll (idle 5s+).
    pollDashboard();
    window.IdlePoll.create({ onPoll: pollDashboard }).start();
})();
