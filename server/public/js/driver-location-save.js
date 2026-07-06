/**
 * Lưu tọa độ tài xế sau khi chọn/tìm trên bản đồ (dùng chung address-map-picker).
 */
(function () {
    var url = window.__driverLocationUrl;
    if (!url) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var detailInput = document.getElementById('driver-location-detail');
    var addressLine = document.getElementById('driver-location-address');
    var metaLine = document.getElementById('driver-location-meta');
    var locationBar = document.getElementById('driver-location-bar');
    var sending = false;

    function isPaused() {
        return locationBar && locationBar.getAttribute('data-driver-paused') === '1';
    }

    function setHeroReady(ready) {
        if (!ready || isPaused()) {
            return;
        }
        if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.refreshHeroStatus) {
            window.DriverAvailabilityToggle.refreshHeroStatus(false);
        }
    }

    function setAddressLine(text) {
        if (!addressLine) {
            return;
        }
        var value = String(text || '').trim();
        addressLine.textContent = value;
        addressLine.classList.toggle('is-empty', value === '');
    }

    function setMetaLine(text) {
        if (metaLine) {
            metaLine.textContent = text || '';
        }
    }

    window.DriverLocationSave = {
        setMetaLine: setMetaLine,
        setAddressLine: setAddressLine,
    };

    function saveLocation(lat, lng, address) {
        if (sending || isPaused() || lat == null || lng == null || lat === '' || lng === '') {
            return;
        }

        sending = true;
        setMetaLine('Đang lưu…');

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
                    setMetaLine('Chưa lưu được — thử lại.');
                    return;
                }
                var data = result.data;
                setAddressLine(data.address || address);
                var meta = data.updated_at ? ('Cập nhật ' + data.updated_at) : '';
                if (data.pickup_proximity_hint) {
                    meta = (meta ? meta + ' · ' : '') + data.pickup_proximity_hint;
                } else if (data.pickup_distance_label) {
                    meta = (meta ? meta + ' · ' : '') + 'Cách điểm đón ~' + data.pickup_distance_label;
                    if (data.pickup_eta_label) {
                        meta += ' · dự kiến ' + data.pickup_eta_label;
                    }
                }
                setMetaLine(meta);
                setHeroReady(true);
                if (window.DriverAvailabilityToggle && window.DriverAvailabilityToggle.clearLocationSharePrompt) {
                    window.DriverAvailabilityToggle.clearLocationSharePrompt();
                }
            })
            .catch(function () {
                setMetaLine('Lỗi mạng — thử lại.');
            })
            .finally(function () {
                sending = false;
            });
    }

    document.addEventListener('addressmap:applied', function (e) {
        var detail = e.detail || {};
        if (detailInput && detail.targetInputId !== detailInput.id) {
            return;
        }
        if (!detailInput && detail.targetInputId !== 'driver-location-detail') {
            return;
        }
        if (detail.lat == null || detail.lng == null) {
            setMetaLine('Chưa có tọa độ — thử chia sẻ GPS lại');
            return;
        }
        saveLocation(detail.lat, detail.lng, detail.address);
    });
})();
