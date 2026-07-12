/**
 * Lấy vị trí GPS liên tục khi tài xế bật Sẵn sàng / đang chạy chuyến.
 */
(function () {
    var reverseUrl = window.__geocodeReverseUrl || '';
    var latInput = document.getElementById('driver-location-lat');
    var lngInput = document.getElementById('driver-location-lng');
    var detailInput = document.getElementById('driver-location-detail');
    var locationBar = document.getElementById('driver-location-bar');
    var sharing = false;
    var periodicTimer = null;
    var autoTracking = false;
    var watchId = null;
    var lastSavedAt = 0;
    var lastSavedLat = null;
    var lastSavedLng = null;
    var PERIODIC_MS = 60 * 1000;
    var SAVE_MIN_INTERVAL_MS = 30 * 1000;
    var MOVE_MIN_METERS = 20;
    var STALE_NUDGE_MS = 2 * 60 * 1000;

    function isPaused() {
        return locationBar && locationBar.getAttribute('data-driver-paused') === '1';
    }

    function setLatLng(lat, lng) {
        if (latInput) {
            latInput.value = String(lat);
        }
        if (lngInput) {
            lngInput.value = String(lng);
        }
    }

    function distanceMeters(lat1, lng1, lat2, lng2) {
        var toRad = function (deg) {
            return deg * Math.PI / 180;
        };
        var earthRadius = 6371000;
        var dLat = toRad(lat2 - lat1);
        var dLng = toRad(lng2 - lng1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);

        return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function shouldPublishPosition(lat, lng, force) {
        if (force) {
            return true;
        }

        var now = Date.now();
        if (!lastSavedAt || now - lastSavedAt >= SAVE_MIN_INTERVAL_MS) {
            return true;
        }

        if (lastSavedLat == null || lastSavedLng == null) {
            return true;
        }

        return distanceMeters(lastSavedLat, lastSavedLng, lat, lng) >= MOVE_MIN_METERS;
    }

    function markPublished(lat, lng) {
        lastSavedAt = Date.now();
        lastSavedLat = lat;
        lastSavedLng = lng;
    }

    function reverseGeocode(lat, lng) {
        if (!reverseUrl) {
            return Promise.resolve('');
        }

        return fetch(reverseUrl + '?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) {
                    return '';
                }

                return String(data.address || data.display_name || data.label || '').trim();
            })
            .catch(function () { return ''; });
    }

    function dispatchApplied(lat, lng, address) {
        document.dispatchEvent(new CustomEvent('addressmap:applied', {
            detail: {
                targetInputId: detailInput ? detailInput.id : 'driver-location-detail',
                lat: lat,
                lng: lng,
                address: address,
            },
        }));
    }

    function geolocationErrorMessage(error) {
        if (!error || typeof error.code !== 'number') {
            return 'Không lấy được GPS. Chọn vị trí hiện tại trên bản đồ.';
        }

        if (error.code === 1) {
            return 'Trình duyệt đã chặn quyền vị trí. Vào Cài đặt trình duyệt → cho phép Vị trí cho trang này, rồi bấm «Lấy GPS».';
        }
        if (error.code === 2) {
            return 'Không xác định được vị trí. Ra ngoài trời hoặc chọn trên bản đồ.';
        }
        if (error.code === 3) {
            return 'GPS phản hồi quá lâu. Thử lại hoặc chọn trên bản đồ.';
        }

        return 'Không lấy được GPS. Chọn vị trí hiện tại trên bản đồ.';
    }

    function notifyGpsFailed(message) {
        if (latInput && lngInput && String(latInput.value || '').trim() && String(lngInput.value || '').trim()) {
            return;
        }

        if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.showLocationFallback) {
            window.DriverAvailabilityToggle.showLocationFallback(message);
        }

        if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.refreshHeroStatus) {
            window.DriverAvailabilityToggle.refreshHeroStatus();
        }
    }

    function notifyGpsSuccess() {
        if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.hideLocationFallback) {
            window.DriverAvailabilityToggle.hideLocationFallback();
        }

        if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.refreshHeroStatus) {
            window.DriverAvailabilityToggle.refreshHeroStatus();
        }
    }

    function applyPosition(lat, lng, options) {
        options = options || {};
        var force = !!options.force;
        var reverse = options.reverseGeocode !== false
            && (!detailInput || !String(detailInput.value || '').trim());

        if (!shouldPublishPosition(lat, lng, force)) {
            return Promise.resolve(false);
        }

        setLatLng(lat, lng);
        markPublished(lat, lng);

        if (reverse) {
            return reverseGeocode(lat, lng).then(function (address) {
                if (detailInput && address) {
                    detailInput.value = address;
                }

                dispatchApplied(lat, lng, address);
                notifyGpsSuccess();
                return true;
            });
        }

        dispatchApplied(lat, lng, detailInput ? detailInput.value : '');
        notifyGpsSuccess();
        return Promise.resolve(true);
    }

    function shareCurrentLocation(options) {
        options = options || {};

        if (sharing || isPaused()) {
            return Promise.resolve(false);
        }

        if (!navigator.geolocation) {
            notifyGpsFailed('Trình duyệt không hỗ trợ GPS. Chọn vị trí hiện tại trên bản đồ.');
            return Promise.resolve(false);
        }

        sharing = true;

        return new Promise(function (resolve) {
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    applyPosition(pos.coords.latitude, pos.coords.longitude, {
                        force: true,
                        reverseGeocode: options.reverseGeocode,
                        maximumAge: options.maximumAge,
                    }).then(function (ok) {
                        resolve(ok);
                    }).finally(function () {
                        sharing = false;
                    });
                },
                function (err) {
                    notifyGpsFailed(geolocationErrorMessage(err));
                    sharing = false;
                    resolve(false);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: options.maximumAge != null ? options.maximumAge : 10000,
                }
            );
        });
    }

    function onWatchPosition(pos) {
        if (isPaused()) {
            stopAutoTracking();
            return;
        }

        applyPosition(pos.coords.latitude, pos.coords.longitude, {
            reverseGeocode: false,
        });
    }

    function onWatchError(err) {
        if (!hasLocationCoords()) {
            notifyGpsFailed(geolocationErrorMessage(err));
        }
    }

    function hasLocationCoords() {
        return !!(latInput && lngInput && String(latInput.value || '').trim() && String(lngInput.value || '').trim());
    }

    function startWatch() {
        if (!navigator.geolocation || watchId != null) {
            return;
        }

        watchId = navigator.geolocation.watchPosition(
            onWatchPosition,
            onWatchError,
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 10000,
            }
        );
    }

    function startAutoTracking() {
        if (autoTracking || isPaused()) {
            return;
        }

        autoTracking = true;
        shareCurrentLocation({ force: true, reverseGeocode: true });
        startWatch();

        if (periodicTimer) {
            return;
        }

        periodicTimer = window.setInterval(function () {
            if (isPaused()) {
                stopAutoTracking();
                return;
            }

            var stale = !lastSavedAt || Date.now() - lastSavedAt >= STALE_NUDGE_MS;
            if (stale) {
                shareCurrentLocation({ maximumAge: 5000, reverseGeocode: false });
            }
        }, PERIODIC_MS);
    }

    function stopAutoTracking() {
        autoTracking = false;

        if (watchId != null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        if (periodicTimer) {
            window.clearInterval(periodicTimer);
            periodicTimer = null;
        }
    }

    function ensureFreshLocation() {
        if (!autoTracking || isPaused()) {
            return;
        }

        if (!lastSavedAt || Date.now() - lastSavedAt >= STALE_NUDGE_MS) {
            shareCurrentLocation({ maximumAge: 0, reverseGeocode: false });
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            ensureFreshLocation();
        }
    });

    var gpsBtn = document.getElementById('driver-location-gps-btn');
    if (gpsBtn) {
        gpsBtn.addEventListener('click', function () {
            shareCurrentLocation({ force: true, reverseGeocode: true });
        });
    }

    function getLastKnownCoords() {
        if (lastSavedLat != null && lastSavedLng != null) {
            return { lat: lastSavedLat, lng: lastSavedLng };
        }

        if (latInput && lngInput && latInput.value && lngInput.value) {
            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);
            if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                return { lat: lat, lng: lng };
            }
        }

        return null;
    }

    window.DriverLocationGps = {
        shareCurrentLocation: shareCurrentLocation,
        startAutoTracking: startAutoTracking,
        stopAutoTracking: stopAutoTracking,
        ensureFreshLocation: ensureFreshLocation,
        getLastKnownCoords: getLastKnownCoords,
    };
})();
