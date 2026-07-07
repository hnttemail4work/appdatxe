/**
 * Lưu tọa độ tài xế sau GPS hoặc chọn trên bản đồ.
 */
(function () {
    var url = window.__driverLocationUrl;
    if (!url) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var detailInput = document.getElementById('driver-location-detail');
    var fallbackDetailInput = document.getElementById('driver-location-fallback-detail');
    var locationBar = document.getElementById('driver-location-bar');
    var sending = false;
    var pendingLocation = null;

    function isPaused() {
        return locationBar && locationBar.getAttribute('data-driver-paused') === '1';
    }

    function isDriverLocationTarget(targetInputId) {
        return targetInputId === 'driver-location-detail'
            || targetInputId === 'driver-location-fallback-detail';
    }

    function setHeroReady() {
        if (isPaused()) {
            return;
        }
        if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.refreshHeroStatus) {
            window.DriverAvailabilityToggle.refreshHeroStatus();
        }
        if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.hideLocationFallback) {
            window.DriverAvailabilityToggle.hideLocationFallback();
        }
    }

    function setPickupProximityLine(text) {
        var section = document.getElementById('driver-pickup-proximity-sheet');
        var line = document.getElementById('driver-pickup-proximity-line');
        if (!section || !line) {
            return;
        }
        var value = String(text || '').trim();
        if (value) {
            line.textContent = value;
            section.classList.remove('d-none');
            section.hidden = false;
        } else {
            line.textContent = '';
            section.classList.add('d-none');
            section.hidden = true;
        }
    }

    window.DriverLocationSave = {
        setPickupProximityLine: setPickupProximityLine,
    };

    function flushPendingLocation() {
        if (!pendingLocation || sending) {
            return;
        }

        var next = pendingLocation;
        pendingLocation = null;
        saveLocation(next.lat, next.lng, next.address);
    }

    function saveLocation(lat, lng, address) {
        if (isPaused() || lat == null || lng == null || lat === '' || lng === '') {
            return;
        }

        if (sending) {
            pendingLocation = { lat: lat, lng: lng, address: address };
            return;
        }

        sending = true;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                lat: Number(lat),
                lng: Number(lng),
                address: String(address || '').trim(),
            }),
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data || !result.data.ok) {
                    if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.showLocationFallback) {
                        window.DriverAvailabilityToggle.showLocationFallback('Chưa lưu được vị trí — chọn lại trên bản đồ.');
                    }
                    return;
                }
                var data = result.data;
                if (detailInput) {
                    detailInput.value = data.address || address || '';
                }
                setPickupProximityLine(data.pickup_proximity_line || '');
                setHeroReady();
                if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.clearLocationSharePrompt) {
                    window.DriverAvailabilityToggle.clearLocationSharePrompt();
                }
            })
            .catch(function () {
                if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.showLocationFallback) {
                    window.DriverAvailabilityToggle.showLocationFallback('Lỗi mạng — chọn lại vị trí trên bản đồ.');
                }
            })
            .finally(function () {
                sending = false;
                flushPendingLocation();
            });
    }

    document.addEventListener('addressmap:applied', function (e) {
        var detail = e.detail || {};
        if (!isDriverLocationTarget(detail.targetInputId || '')) {
            return;
        }
        if (detail.lat == null || detail.lng == null) {
            if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.showLocationFallback) {
                window.DriverAvailabilityToggle.showLocationFallback('Chưa có tọa độ — chọn lại trên bản đồ.');
            }
            return;
        }

        if (detailInput) {
            detailInput.value = String(detail.address || '').trim();
        }
        if (fallbackDetailInput && detail.targetInputId === 'driver-location-fallback-detail') {
            fallbackDetailInput.value = String(detail.address || '').trim();
        }

        saveLocation(detail.lat, detail.lng, detail.address);
    });
})();
