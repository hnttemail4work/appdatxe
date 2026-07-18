/**
 * Poll trang tài xế — cập nhật badge chuyến, khoảng cách đón; reload khi có thay đổi lớn.
 */
(function () {
    var url = window.__driverDashboardPollUrl;

    if (!url || !window.IdlePoll) {
        return;
    }

    var lastFingerprint = null;

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

                if (typeof data.trip_action_count === 'number') {
                    updateDockBadge(data.trip_action_count);
                    if (window.DriverSounds && window.DriverSounds.onTripCount) {
                        window.DriverSounds.onTripCount(data.trip_action_count);
                    }
                }

                if (data.inbox_unread && window.DriverInbox && window.DriverInbox.updateBadges) {
                    window.DriverInbox.updateBadges(data.inbox_unread);
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

                if ((data.availability_status === 'available' || data.availability_status === 'on_trip')
                    && window.DriverLocationGps
                    && window.DriverLocationGps.ensureFreshLocation) {
                    window.DriverLocationGps.ensureFreshLocation();
                }

                if (lastFingerprint === null) {
                    lastFingerprint = data.fingerprint || null;
                    return;
                }

                if (data.fingerprint && data.fingerprint !== lastFingerprint && !window.IdlePoll.isBlocked()) {
                    window.location.reload();
                }
            })
            .catch(function () {});
    }

    window.IdlePoll.create({ onPoll: pollDashboard }).start();
})();
