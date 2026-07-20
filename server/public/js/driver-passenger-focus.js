/**
 * Nhấn card thông tin khách — đồng bộ màn khách (zoom đối phương / thu sheet + khoảng cách).
 */
(function () {
    'use strict';

    function focusCoordsFromPanel(panel) {
        if (!panel) {
            return null;
        }
        var lat = Number(panel.getAttribute('data-focus-lat'));
        var lng = Number(panel.getAttribute('data-focus-lng'));
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }
        return { lat: lat, lng: lng };
    }

    function onActivate(event, panel) {
        if (event.target.closest(
            '.driver-pax-card__actions, .driver-pax-card__chat, .trip-chat-toggle, .trip-chat-panel, .driver-pax-card__call, a, button, input, textarea'
        )) {
            return;
        }
        if (!window.DriverLiveMap || typeof window.DriverLiveMap.togglePassengerFocusCamera !== 'function') {
            return;
        }
        window.DriverLiveMap.togglePassengerFocusCamera(focusCoordsFromPanel(panel));
    }

    function bindPanel(panel) {
        if (!panel || panel.dataset.focusToggleBound === '1') {
            return;
        }
        panel.dataset.focusToggleBound = '1';

        panel.addEventListener('click', function (event) {
            onActivate(event, panel);
        });
        panel.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            onActivate(event, panel);
        });
    }

    function bindAll() {
        document.querySelectorAll('[data-passenger-focus]').forEach(bindPanel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAll);
    } else {
        bindAll();
    }
})();
