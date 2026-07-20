/**
 * FAB khẩn cấp (chuông, phải trên) — chỉ khi đang trong chuyến. Chat nằm trong panel chuyến.
 */
(function () {
    'use strict';

    function setVisible(el, show) {
        if (!el) {
            return;
        }
        el.hidden = !show;
        el.classList.toggle('d-none', !show);
    }

    function setInTrip(show) {
        var on = !!show;
        document.querySelectorAll('[data-trip-sos-fab]').forEach(function (el) {
            setVisible(el, on);
        });
        document.body.classList.toggle('is-trip-fabs-active', on);
    }

    function detectDriverInTrip() {
        var locationBar = document.getElementById('driver-location-bar');
        if (locationBar) {
            if (locationBar.getAttribute('data-driver-on-trip') === '1'
                || locationBar.getAttribute('data-driver-trip-active') === '1'
                || locationBar.getAttribute('data-driver-trip-upcoming') === '1') {
                return true;
            }
        }

        // Fallback: đã có chuyến trên sheet — không chờ IdlePoll 5–10s.
        if (document.querySelector('#driver-trips-list [data-schedule-id]')) {
            return true;
        }

        var bootSos = document.querySelector('[data-trip-sos-fab]');
        return !!(bootSos && !bootSos.classList.contains('d-none') && !bootSos.hidden);
    }

    window.TripActionFabs = {
        setInTrip: setInTrip,
    };

    // Boot ngay — không phụ thuộc poll idle.
    if (document.getElementById('driver-location-bar')
        || document.querySelector('#driver-trips-list')
        || document.querySelector('[data-trip-sos-fab]')) {
        setInTrip(detectDriverInTrip());
    }
})();
