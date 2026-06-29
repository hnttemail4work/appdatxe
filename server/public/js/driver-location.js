/**
 * Gửi vị trí GPS định kỳ khi tài xế mở dashboard — phục vụ gán cuốc theo khoảng cách.
 */
(function () {
    var url = window.__driverLocationUrl;
    if (!url || !navigator.geolocation) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var sending = false;

    function send(lat, lng) {
        if (sending) {
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
            body: JSON.stringify({ lat: lat, lng: lng }),
            credentials: 'same-origin',
        }).catch(function () {}).finally(function () {
            sending = false;
        });
    }

    function tick() {
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                send(pos.coords.latitude, pos.coords.longitude);
            },
            function () {},
            { enableHighAccuracy: true, maximumAge: 90000, timeout: 20000 },
        );
    }

    tick();
    window.setInterval(tick, 75000);
})();
