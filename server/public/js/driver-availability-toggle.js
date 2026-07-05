/**
 * Bật/tắt sẵn sàng — tắt thì xóa vị trí trên server.
 */
(function () {
    var url = window.__driverAvailabilityUrl;
    var toggle = document.getElementById('driver-availability-input');
    var locationBar = document.getElementById('driver-location-bar');
    var heroPill = document.getElementById('driver-hero-status-pill');
    var heroLabel = document.getElementById('driver-hero-status-label');
    var statusPill = document.getElementById('driver-location-status');
    var addressLine = document.getElementById('driver-location-address');
    var metaLine = document.getElementById('driver-location-meta');
    var detailInput = document.getElementById('driver-location-detail');
    var latInput = document.getElementById('driver-location-lat');
    var lngInput = document.getElementById('driver-location-lng');
    var actionsWrap = document.querySelector('.driver-location-sheet-actions');
    var mapBtn = document.querySelector('.driver-location-map-btn');

    if (!url || !toggle || !locationBar) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var sending = false;
    var onTrip = locationBar.getAttribute('data-driver-trip-active') === '1';
    var tripUpcoming = locationBar.getAttribute('data-driver-trip-upcoming') === '1';
    var sharePromptMessage = tripUpcoming
        ? 'Chia sẻ vị trí để khách biết bạn còn bao nhiêu km đến điểm đón.'
        : '';

    function hasLocationCoords() {
        return !!(latInput && lngInput && String(latInput.value || '').trim() && String(lngInput.value || '').trim());
    }

    function ensureSharePromptElement() {
        if (!tripUpcoming || !sharePromptMessage) {
            return null;
        }

        var prompt = document.getElementById('driver-location-share-prompt');
        if (prompt) {
            prompt.hidden = false;
            return prompt;
        }

        prompt = document.createElement('div');
        prompt.id = 'driver-location-share-prompt';
        prompt.className = 'driver-location-share-prompt';
        prompt.setAttribute('role', 'status');
        prompt.textContent = sharePromptMessage;
        locationBar.insertBefore(prompt, locationBar.firstChild);

        return prompt;
    }

    function focusLocationShare(options) {
        var opts = options || {};

        if (onTrip || locationBar.getAttribute('data-driver-paused') === '1' || hasLocationCoords()) {
            return;
        }

        locationBar.setAttribute('data-needs-location', '1');
        locationBar.classList.add('driver-location-sheet--needs-share');

        if (tripUpcoming) {
            ensureSharePromptElement();

            if (metaLine) {
                metaLine.textContent = sharePromptMessage;
            }
        }

        if (opts.scroll !== false) {
            locationBar.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        window.setTimeout(function () {
            if (mapBtn && !mapBtn.disabled) {
                mapBtn.focus({ preventScroll: true });
            } else if (detailInput && !detailInput.disabled) {
                detailInput.focus({ preventScroll: true });
            }
        }, opts.scroll === false ? 50 : 420);
    }

    function clearLocationSharePrompt() {
        if (!locationBar) {
            return;
        }

        locationBar.setAttribute('data-needs-location', '0');
        locationBar.classList.remove('driver-location-sheet--needs-share');

        var prompt = document.getElementById('driver-location-share-prompt');
        if (prompt) {
            prompt.hidden = true;
        }
    }

    function setPaused(paused, options) {
        options = options || {};
        locationBar.setAttribute('data-driver-paused', paused ? '1' : '0');

        if (toggle && !onTrip) {
            toggle.checked = !paused;
        }

        if (actionsWrap) {
            actionsWrap.classList.toggle('is-disabled', paused);
        }

        if (detailInput) {
            detailInput.disabled = paused;
            detailInput.placeholder = paused
                ? 'Bật sẵn sàng để cập nhật vị trí'
                : '';
        }

        if (mapBtn) {
            mapBtn.disabled = paused;
        }

        if (statusPill) {
            statusPill.textContent = paused ? 'Tạm nghỉ' : 'Chưa có';
            statusPill.className = 'driver-location-status driver-location-status--idle';
        }

        if (metaLine) {
            metaLine.textContent = options.message || '';
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
            } else {
                heroPill.className = 'driver-status-pill driver-status-pill--offline';
                heroLabel.textContent = 'Cập nhật vị trí để nhận chuyến';
            }
        }

        if (!paused && !hasLocationCoords()) {
            focusLocationShare({ scroll: false });
        }
    }

    function clearLocationFields() {
        if (detailInput) {
            detailInput.value = '';
        }
        if (latInput) {
            latInput.value = '';
        }
        if (lngInput) {
            lngInput.value = '';
        }
        if (addressLine) {
            addressLine.textContent = '';
            addressLine.classList.add('is-empty');
        }
    }

    if (toggle && locationBar.getAttribute('data-driver-paused') === '1') {
        setPaused(true);
    } else if (locationBar.getAttribute('data-needs-location') === '1') {
        focusLocationShare({ scroll: false });
    }

    toggle.addEventListener('change', function () {
        if (onTrip || sending) {
            toggle.checked = true;
            return;
        }

        var wantAvailable = toggle.checked;
        var previousChecked = !wantAvailable;

        sending = true;
        toggle.disabled = true;

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
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    toggle.checked = previousChecked;
                    var message = (result.data && result.data.message) || 'Không cập nhật được trạng thái.';
                    if (metaLine && !onTrip) {
                        metaLine.textContent = message;
                    }
                    return;
                }

                if (!wantAvailable) {
                    clearLocationFields();
                    setPaused(true);
                    return;
                }

                clearLocationFields();
                setPaused(false);
                focusLocationShare({ scroll: true });
            })
            .catch(function () {
                toggle.checked = previousChecked;
                if (metaLine) {
                    metaLine.textContent = 'Lỗi mạng — thử lại.';
                }
            })
            .finally(function () {
                sending = false;
                if (!onTrip) {
                    toggle.disabled = false;
                }
            });
    });

    document.addEventListener('driver:availability-sync', function (event) {
        if (onTrip) {
            return;
        }
        var detail = event.detail || {};
        var paused = detail.availability === 'off_duty';
        if (!paused && detail.availability !== 'available') {
            return;
        }
        if (!paused) {
            return;
        }
        clearLocationFields();
        setPaused(true, {
            message: detail.message || 'Đã tắt Hoạt động — bật lại để nhận cuốc mới.',
        });
    });

    window.DriverAvailabilityToggle = {
        setPaused: setPaused,
        clearLocationFields: clearLocationFields,
        focusLocationShare: focusLocationShare,
        clearLocationSharePrompt: clearLocationSharePrompt,
    };
})();

