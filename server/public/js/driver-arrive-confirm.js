/**
 * Xác nhận khi bấm «Đã đến» mà GPS còn cách xa điểm đón.
 * Dùng AppDialog / AppFlash màu vàng — không dùng window.confirm / alert.
 */
(function () {
    var DEFAULT_FAR_M = 800;

    function haversineMeters(lat1, lng1, lat2, lng2) {
        var R = 6371000;
        var toRad = Math.PI / 180;
        var dLat = (lat2 - lat1) * toRad;
        var dLng = (lng2 - lng1) * toRad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * toRad) * Math.cos(lat2 * toRad)
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * R * Math.asin(Math.sqrt(a));
    }

    function currentDriverPos() {
        if (window.DriverLiveMap && typeof window.DriverLiveMap.getLastPosition === 'function') {
            var p = window.DriverLiveMap.getLastPosition();
            if (p && Number.isFinite(p.lat) && Number.isFinite(p.lng)) {
                return p;
            }
        }
        return null;
    }

    function showWarningFlash(message, title) {
        if (window.AppFlash && typeof window.AppFlash.show === 'function') {
            window.AppFlash.show(message, {
                variant: 'warning',
                title: title || 'Cách điểm đón khá xa',
                autoDismiss: 8000,
            });
            return true;
        }
        return false;
    }

    /** Hộp thoại vàng (cùng kiểu thông báo cảnh báo trong app). */
    function confirmFar(message, title) {
        if (window.AppDialog && typeof window.AppDialog.confirm === 'function') {
            return window.AppDialog.confirm({
                title: title || 'Cách điểm đón khá xa',
                message: message,
                confirmText: 'Đã đến',
                cancelText: 'Huỷ',
                variant: 'warning',
            });
        }

        showWarningFlash(message, title);
        return Promise.resolve(false);
    }

    function proceedSubmit(form) {
        form.dataset.arriveConfirmed = '1';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-driver-arrive-confirm]');
        if (!form || form.dataset.arriveConfirmed === '1') {
            return;
        }

        var pickupLat = parseFloat(form.getAttribute('data-pickup-lat') || '');
        var pickupLng = parseFloat(form.getAttribute('data-pickup-lng') || '');
        var farMeters = parseFloat(form.getAttribute('data-far-meters') || '') || DEFAULT_FAR_M;
        if (!Number.isFinite(pickupLat) || !Number.isFinite(pickupLng)) {
            return;
        }

        var pos = currentDriverPos();
        if (!pos) {
            event.preventDefault();
            event.stopImmediatePropagation();
            confirmFar(
                'Không xác định được vị trí GPS.\n\nBạn xác nhận đã đến đúng điểm đón?',
                'Chưa có GPS'
            ).then(function (ok) {
                if (ok) {
                    proceedSubmit(form);
                }
            });
            return;
        }

        var dist = haversineMeters(pos.lat, pos.lng, pickupLat, pickupLng);
        if (dist <= farMeters) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        confirmFar('Bạn đang cách quá xa điểm đón. Bạn đã đến rồi chứ?', 'Cách điểm đón khá xa').then(function (ok) {
            if (ok) {
                proceedSubmit(form);
            }
        });
    }, true);
})();
