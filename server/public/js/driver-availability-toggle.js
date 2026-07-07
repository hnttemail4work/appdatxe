/**
 * Bật/tắt sẵn sàng — tắt thì xóa vị trí trên server.
 */
(function () {
    var url = window.__driverAvailabilityUrl;
    var toggle = document.getElementById('driver-availability-input');
    var locationBar = document.getElementById('driver-location-bar');
    var heroPill = document.getElementById('driver-hero-status-pill');
    var heroLabel = document.getElementById('driver-hero-status-label');
    var detailInput = document.getElementById('driver-location-detail');
    var fallbackDetailInput = document.getElementById('driver-location-fallback-detail');
    var fallbackSection = document.getElementById('driver-location-fallback');
    var latInput = document.getElementById('driver-location-lat');
    var lngInput = document.getElementById('driver-location-lng');
    var mapBtn = document.querySelector('#driver-location-fallback .driver-location-map-btn');

    if (!url || !toggle || !locationBar) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var sending = false;
    var suppressNextChange = false;
    var onTrip = locationBar.getAttribute('data-driver-trip-active') === '1';
    var tripUpcoming = locationBar.getAttribute('data-driver-trip-upcoming') === '1';

    function hasLocationCoords() {
        return !!(latInput && lngInput && String(latInput.value || '').trim() && String(lngInput.value || '').trim());
    }

    function isFallbackVisible() {
        return !!(fallbackSection && !fallbackSection.hidden && !fallbackSection.classList.contains('d-none'));
    }

    function showLocationFallback(message) {
        if (!fallbackSection || locationBar.getAttribute('data-driver-paused') === '1') {
            return;
        }

        var hint = document.getElementById('driver-location-fallback-hint');
        if (hint && message) {
            hint.textContent = message;
        }

        fallbackSection.classList.remove('d-none');
        fallbackSection.hidden = false;

        refreshHeroStatus();

        if (fallbackDetailInput) {
            fallbackDetailInput.disabled = false;
        }
        if (mapBtn) {
            mapBtn.disabled = false;
        }
    }

    function hideLocationFallback() {
        if (!fallbackSection) {
            return;
        }

        fallbackSection.classList.add('d-none');
        fallbackSection.hidden = true;

        if (fallbackDetailInput) {
            fallbackDetailInput.value = '';
        }
    }

    function clearLocationSharePrompt() {
        if (!locationBar) {
            return;
        }

        locationBar.setAttribute('data-needs-location', '0');
    }

    function requestAutoLocation() {
        if (locationBar.getAttribute('data-driver-paused') === '1') {
            return;
        }

        hideLocationFallback();

        if (window.DriverLocationGps && window.DriverLocationGps.shareCurrentLocation) {
            window.DriverLocationGps.shareCurrentLocation();
        }
    }

    function startGpsTracking() {
        if (window.DriverLocationGps && window.DriverLocationGps.startAutoTracking) {
            window.DriverLocationGps.startAutoTracking();
        }
    }

    function stopGpsTracking() {
        if (window.DriverLocationGps && window.DriverLocationGps.stopAutoTracking) {
            window.DriverLocationGps.stopAutoTracking();
        }
    }

    function refreshHeroStatus() {
        if (!heroPill || !heroLabel || onTrip || tripUpcoming) {
            return;
        }

        if (locationBar.getAttribute('data-driver-paused') === '1') {
            heroPill.className = 'driver-status-pill driver-status-pill--offline';
            heroLabel.textContent = 'Tạm nghỉ';
            return;
        }

        if (hasLocationCoords()) {
            heroPill.className = 'driver-status-pill driver-status-pill--online';
            heroLabel.textContent = 'Sẵn sàng';
            clearLocationSharePrompt();
            hideLocationFallback();
            return;
        }

        if (isFallbackVisible()) {
            heroPill.className = 'driver-status-pill driver-status-pill--offline';
            heroLabel.textContent = 'Chọn vị trí trên bản đồ';
            return;
        }

        heroPill.className = 'driver-status-pill driver-status-pill--offline';
        heroLabel.textContent = 'Đang lấy vị trí GPS…';
    }

    function setPaused(paused, options) {
        options = options || {};
        locationBar.setAttribute('data-driver-paused', paused ? '1' : '0');

        if (toggle) {
            toggle.checked = !paused;
        }

        if (fallbackDetailInput) {
            fallbackDetailInput.disabled = paused;
        }
        if (mapBtn) {
            mapBtn.disabled = paused;
        }

        if (heroPill && heroLabel) {
            if (onTrip) {
                heroPill.className = 'driver-status-pill driver-status-pill--busy';
                heroLabel.textContent = 'Đang chạy chuyến';
            } else if (tripUpcoming) {
                heroPill.className = 'driver-status-pill driver-status-pill--assigned';
                heroLabel.textContent = 'Đã nhận cuốc';
            } else if (paused) {
                heroPill.className = 'driver-status-pill driver-status-pill--offline';
                heroLabel.textContent = 'Tạm nghỉ';
                clearLocationSharePrompt();
                hideLocationFallback();
                stopGpsTracking();
            } else {
                refreshHeroStatus();
            }
        }

        if (paused) {
            stopGpsTracking();
            hideLocationFallback();
        } else {
            startGpsTracking();
        }
    }

    function clearLocationFields() {
        if (detailInput) {
            detailInput.value = '';
        }
        if (fallbackDetailInput) {
            fallbackDetailInput.value = '';
        }
        if (latInput) {
            latInput.value = '';
        }
        if (lngInput) {
            lngInput.value = '';
        }
    }

    function showToggleError(message) {
        var text = message || 'Không đổi được trạng thái Hoạt động.';
        if (window.AppFlash && window.AppFlash.show) {
            window.AppFlash.show(text, { variant: 'danger', title: 'Không đổi được trạng thái' });
        } else if (window.AppDialog && window.AppDialog.alert) {
            window.AppDialog.alert(text, { variant: 'danger' });
        }
    }

    // TODO (Fix Driver Toggle): Gửi PATCH bật/tắt app — tách khỏi event change để tránh kẹt UI.
    function submitAvailability(wantAvailable) {
        if (sending || !toggle) {
            return;
        }

        var previousChecked = toggle.checked;

        sending = true;
        toggle.disabled = true;
        suppressNextChange = true;
        toggle.checked = wantAvailable;

        fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                availability_status: wantAvailable ? 'available' : 'off_duty',
            }),
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data = {};
                    if (text) {
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            data = {};
                        }
                    }

                    return { ok: r.ok, data: data, status: r.status };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    suppressNextChange = true;
                    toggle.checked = previousChecked;
                    var message = result.data && result.data.message
                        ? result.data.message
                        : (result.status === 419
                            ? 'Phiên đăng nhập hết hạn — tải lại trang rồi thử lại.'
                            : 'Không đổi được trạng thái Hoạt động.');
                    showToggleError(message);
                    return;
                }

                if (!wantAvailable) {
                    clearLocationFields();
                    setPaused(true);
                    return;
                }

                hideLocationFallback();
                setPaused(false);
                if (!hasLocationCoords()) {
                    requestAutoLocation();
                } else {
                    refreshHeroStatus();
                }
            })
            .catch(function () {
                suppressNextChange = true;
                toggle.checked = previousChecked;
                showToggleError('Lỗi mạng — thử lại.');
            })
            .finally(function () {
                sending = false;
                toggle.disabled = false;
            });
    }

    if (toggle && locationBar.getAttribute('data-driver-paused') === '1') {
        setPaused(true);
        } else {
            startGpsTracking();
            if (!hasLocationCoords()) {
                requestAutoLocation();
            }
            if (!hasLocationCoords() && locationBar.getAttribute('data-needs-location') === '1') {
                refreshHeroStatus();
            }
        }

    if (toggle) {
        toggle.addEventListener('change', function () {
            if (suppressNextChange) {
                suppressNextChange = false;
                return;
            }

            if (sending) {
                toggle.checked = !toggle.checked;
                return;
            }

            submitAvailability(toggle.checked);
        });
    }

    var toggleLabel = document.getElementById('driver-activity-toggle-label');
    if (toggleLabel && toggle) {
        toggleLabel.addEventListener('click', function (event) {
            if (sending) {
                event.preventDefault();
                return;
            }

            if (event.target === toggle) {
                return;
            }

            event.preventDefault();
            submitAvailability(!toggle.checked);
        });
    }

    document.addEventListener('driver:availability-sync', function (event) {
        var detail = event.detail || {};
        var paused = detail.availability === 'off_duty';
        if (!paused && detail.availability !== 'available') {
            return;
        }
        if (!paused) {
            return;
        }
        clearLocationFields();
        setPaused(true);
    });

    window.DriverAvailabilityToggle = {
        setPaused: setPaused,
        clearLocationFields: clearLocationFields,
        clearLocationSharePrompt: clearLocationSharePrompt,
        refreshHeroStatus: refreshHeroStatus,
        requestAutoLocation: requestAutoLocation,
        showLocationFallback: showLocationFallback,
        hideLocationFallback: hideLocationFallback,
    };
})();
