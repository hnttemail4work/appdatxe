/**
 * Lưu tọa độ tài xế sau khi chọn/tìm trên bản đồ (dùng chung address-map-picker).
 */
(function () {
    var url = window.__driverLocationUrl;
    if (!url) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var targetId = 'driver-location-detail';
    var addressLine = document.getElementById('driver-location-address');
    var metaLine = document.getElementById('driver-location-meta');
    var statusPill = document.getElementById('driver-location-status');
    var sending = false;

    function setStatus(state, text) {
        if (!statusPill) {
            return;
        }
        statusPill.textContent = text;
        statusPill.className = 'driver-location-status driver-location-status--' + state;
    }

    function setAddressLine(text) {
        if (!addressLine) {
            return;
        }
        var value = String(text || '').trim();
        addressLine.textContent = value || 'Chưa chọn trên bản đồ';
        addressLine.classList.toggle('text-muted', value === '');
    }

    function setMetaLine(text) {
        if (metaLine) {
            metaLine.textContent = text || '';
        }
    }

    function saveLocation(lat, lng, address) {
        if (sending || lat == null || lng == null || lat === '' || lng === '') {
            return;
        }

        sending = true;
        setStatus('pending', 'Đang lưu…');

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
                    setStatus('warn', 'Chưa lưu được');
                    return;
                }
                var data = result.data;
                setAddressLine(data.address || address);
                setMetaLine(data.updated_at ? ('Cập nhật ' + data.updated_at) : '');
                setStatus('ok', 'Sẵn sàng gán cuốc');
            })
            .catch(function () {
                setStatus('warn', 'Lỗi mạng');
            })
            .finally(function () {
                sending = false;
            });
    }

    document.addEventListener('addressmap:applied', function (e) {
        var detail = e.detail || {};
        if (detail.targetInputId !== targetId) {
            return;
        }
        if (detail.lat == null || detail.lng == null) {
            setStatus('warn', 'Chưa có tọa độ — chọn trên bản đồ');
            return;
        }
        saveLocation(detail.lat, detail.lng, detail.address);
    });
})();
