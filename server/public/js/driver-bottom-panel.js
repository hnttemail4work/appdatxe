/**
 * Bottom panel idle / trip sheet — opens only when live trips exist.
 */
(function () {
    var panel = document.getElementById('driver-bottom-panel');
    if (!panel) {
        return;
    }

    var idleEl = document.getElementById('driver-bottom-idle');
    var tripSheet = document.getElementById('driver-trip-sheet');
    var liveEl = document.getElementById('driver-trip-live');
    var readyCta = document.getElementById('driver-ready-cta');
    var readyInput = document.getElementById('driver-availability-input');
    var readyLabel = readyCta ? readyCta.querySelector('[data-ready-label]') : null;
    var page = document.querySelector('.driver-page--map');

    function countLiveTrips() {
        var pending = document.querySelectorAll('[data-trip-request-id]:not([data-removing="1"])').length;
        var schedules = document.querySelectorAll('#driver-trips-list [data-schedule-id]').length;
        return pending + schedules;
    }

    function hasLiveTrips() {
        return countLiveTrips() > 0;
    }

    /** Thoát màn nhận cuốc full-screen ngay khi hết offer — tránh nút Bật/Tắt bị đẩy lên và map biến mất ~1s. */
    function syncOfferPending() {
        if (!page) {
            return;
        }
        var pending = document.querySelectorAll('[data-trip-request-id]:not([data-removing="1"])').length;
        var schedules = document.querySelectorAll('#driver-trips-list [data-schedule-id]').length;
        page.classList.toggle('is-offer-pending', pending > 0 && schedules === 0);
    }

    function syncReadyCta() {
        if (!readyCta || !readyInput) {
            return;
        }
        var on = !!readyInput.checked;
        readyCta.classList.toggle('is-on', on);
        readyCta.classList.toggle('is-off', !on);
        readyCta.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (readyLabel) {
            readyLabel.textContent = on ? 'TẮT SẴN SÀNG' : 'BẬT SẴN SÀNG';
        }

        if (page) {
            page.classList.toggle('is-duty-on', on);
            page.classList.toggle('is-duty-off', !on);
        }
    }

    function syncMapFabLift() {
        if (!page) {
            return;
        }
        var height = panel.getBoundingClientRect().height || 0;
        var gap = 10;
        page.style.setProperty('--driver-map-fab-lift', Math.round(height + gap) + 'px');
        // SOS nằm trong .driver-map-hero__fabs — cùng bottom với vị trí / GG Maps, không set inline riêng.
    }

    function setExpanded(expanded) {
        var overlayOpen = page && page.classList.contains('is-panel-open');
        if (overlayOpen) {
            expanded = false;
        }

        panel.classList.toggle('is-expanded', expanded);
        panel.classList.toggle('is-idle', !expanded);
        panel.classList.toggle('is-trip', expanded);

        if (idleEl) {
            idleEl.hidden = expanded;
        }
        if (tripSheet) {
            tripSheet.hidden = !expanded;
            tripSheet.classList.toggle('is-open', expanded);
            tripSheet.classList.toggle('is-visible', expanded || hasLiveTrips());
        }

        if (liveEl) {
            liveEl.hidden = !hasLiveTrips();
        }

        if (!expanded && window.TripChat && typeof window.TripChat.closeAllOpen === 'function') {
            window.TripChat.closeAllOpen();
        }

        requestAnimationFrame(function () {
            syncMapFabLift();
            requestAnimationFrame(syncMapFabLift);
        });
    }

    function refreshPanelMode() {
        syncOfferPending();
        setExpanded(hasLiveTrips());
    }

    if (readyCta && readyInput) {
        readyCta.addEventListener('click', function () {
            if (readyInput.disabled) {
                return;
            }
            readyInput.checked = !readyInput.checked;
            readyInput.dispatchEvent(new Event('change', { bubbles: true }));
            syncReadyCta();
        });
        readyInput.addEventListener('change', syncReadyCta);
        syncReadyCta();
    }

    document.addEventListener('driver:availability-sync', syncReadyCta);

    if (page) {
        page.addEventListener('drivertab:changed', function () {
            refreshPanelMode();
        });
    }

    var originalUpdate = window.__driverUpdateTripDockBadge;
    window.__driverUpdateTripDockBadge = function () {
        if (typeof originalUpdate === 'function') {
            originalUpdate();
        }
        panel.dataset.hasLiveTrips = hasLiveTrips() ? '1' : '0';
        refreshPanelMode();
    };

    window.DriverBottomPanel = {
        refresh: refreshPanelMode,
        syncReadyCta: syncReadyCta,
        syncMapFabLift: syncMapFabLift,
        syncOfferPending: syncOfferPending,
    };

    if (typeof ResizeObserver !== 'undefined') {
        var ro = new ResizeObserver(function () {
            syncMapFabLift();
        });
        ro.observe(panel);
    } else {
        window.addEventListener('resize', syncMapFabLift);
    }

    panel.addEventListener('transitionend', function (event) {
        if (event.target === panel && (event.propertyName === 'max-height' || event.propertyName === 'padding')) {
            syncMapFabLift();
        }
    });

    refreshPanelMode();
    syncMapFabLift();
})();
