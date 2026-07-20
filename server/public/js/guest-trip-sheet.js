/**
 * Sheet thông tin chuyến — kéo xuống thu nhỏ, kéo lên xem chi tiết (tìm TX + theo dõi chuyến).
 * Mỗi lần user vuốt/chạm: căn lại camera vào giữa mép trên map ↔ mép trên popup.
 */
(function () {
    var sheet = document.getElementById('guest-trip-info-sheet');
    var handle = document.getElementById('guest-trip-info-sheet-handle');
    var card = document.getElementById('guest-trip-card');
    if (!sheet || !handle || !card) {
        return;
    }

    var startY = 0;
    var dragging = false;
    var THRESHOLD = 36;
    var root = document.documentElement;
    var refitTimers = [];

    function isSheetMode() {
        return card.classList.contains('is-searching') || card.classList.contains('is-tracking');
    }

    /** Nút định vị sát mép trên sheet; chuông SOS xếp ngay phía trên — đồng nhất tìm TX / đang đi đón. */
    function syncLocateFabLift() {
        var locateBtn = document.getElementById('guest-trip-locate-btn');
        var sosFab = document.querySelector('[data-trip-sos-fab]');
        var STACK_GAP = 10;

        if (!isSheetMode()) {
            root.style.removeProperty('--guest-trip-sheet-lift');
            root.style.removeProperty('--guest-trip-fab-bottom');
            if (locateBtn) {
                locateBtn.style.bottom = '';
            }
            if (sosFab) {
                sosFab.style.bottom = '';
            }
            return;
        }

        var sheetRect = sheet.getBoundingClientRect();
        var viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
        var locateBottom = Math.max(0, Math.round(viewportH - sheetRect.top + STACK_GAP));
        var fabH = locateBtn && locateBtn.offsetHeight
            ? locateBtn.offsetHeight
            : 52;
        var sosBottom = locateBottom + fabH + STACK_GAP;

        root.style.setProperty('--guest-trip-fab-bottom', locateBottom + 'px');
        root.style.setProperty('--guest-trip-sheet-lift', locateBottom + 'px');

        if (locateBtn) {
            locateBtn.style.bottom = locateBottom + 'px';
        }
        if (sosFab) {
            sosFab.style.bottom = sosBottom + 'px';
        }
    }

    function clearRefitTimers() {
        refitTimers.forEach(function (id) {
            window.clearTimeout(id);
        });
        refitTimers = [];
    }

    function runSheetCameraRefit() {
        syncLocateFabLift();
        if (!window.GuestTripLiveMap) {
            return;
        }
        if (window.GuestTripLiveMap.resize) {
            window.GuestTripLiveMap.resize();
        }
        if (window.GuestTripLiveMap.refitSheetCamera) {
            window.GuestTripLiveMap.refitSheetCamera();
        }
    }

    /** Refit sau layout + sau transition (không chỉ dựa transitionend). */
    function scheduleSheetCameraRefit() {
        clearRefitTimers();
        window.requestAnimationFrame(function () {
            runSheetCameraRefit();
            refitTimers.push(window.setTimeout(runSheetCameraRefit, 140));
            refitTimers.push(window.setTimeout(runSheetCameraRefit, 320));
        });
    }

    function setCollapsed(collapsed, options) {
        options = options || {};
        var nextCollapsed = !!collapsed;
        var wasCollapsed = sheet.classList.contains('is-collapsed');
        if (nextCollapsed === wasCollapsed && !options.force) {
            syncLocateFabLift();
            return;
        }

        sheet.classList.toggle('is-collapsed', nextCollapsed);
        sheet.classList.toggle('is-expanded', !nextCollapsed);
        handle.setAttribute('aria-expanded', nextCollapsed ? 'false' : 'true');

        if (nextCollapsed && window.TripChat && typeof window.TripChat.closeAllOpen === 'function') {
            window.TripChat.closeAllOpen();
        }

        window.requestAnimationFrame(function () {
            syncLocateFabLift();
            if (window.GuestTripLiveMap && window.GuestTripLiveMap.resize) {
                window.GuestTripLiveMap.resize();
            }
        });

        if (options.refitCamera && options.userGesture) {
            scheduleSheetCameraRefit();
        }
    }

    function syncForBookingMode() {
        if (!isSheetMode()) {
            setCollapsed(false, { refitCamera: false });
            return;
        }
        if (!sheet.dataset.userToggled) {
            setCollapsed(true, { force: true, refitCamera: false });
        } else {
            syncLocateFabLift();
        }
    }

    handle.addEventListener('click', function () {
        if (!isSheetMode()) {
            return;
        }
        sheet.dataset.userToggled = '1';
        setCollapsed(!sheet.classList.contains('is-collapsed'), { refitCamera: true, userGesture: true });
    });

    function onPointerDown(event) {
        if (!isSheetMode()) {
            return;
        }
        dragging = true;
        startY = event.clientY != null ? event.clientY : (event.touches && event.touches[0] ? event.touches[0].clientY : 0);
    }

    function onPointerUp(event) {
        if (!dragging || !isSheetMode()) {
            dragging = false;
            return;
        }
        dragging = false;
        var endY = event.clientY != null
            ? event.clientY
            : (event.changedTouches && event.changedTouches[0] ? event.changedTouches[0].clientY : startY);
        var delta = endY - startY;
        if (Math.abs(delta) < THRESHOLD) {
            return;
        }
        sheet.dataset.userToggled = '1';
        setCollapsed(delta > 0, { refitCamera: true, userGesture: true });
    }

    handle.addEventListener('touchstart', onPointerDown, { passive: true });
    handle.addEventListener('touchend', onPointerUp, { passive: true });
    handle.addEventListener('mousedown', onPointerDown);
    window.addEventListener('mouseup', onPointerUp);

    if (typeof ResizeObserver !== 'undefined') {
        var ro = new ResizeObserver(function () {
            syncLocateFabLift();
        });
        ro.observe(sheet);
    } else {
        window.addEventListener('resize', syncLocateFabLift);
    }

    sheet.addEventListener('transitionend', function (event) {
        if (event.target !== sheet) {
            return;
        }
        syncLocateFabLift();
        if (sheet.dataset.userToggled === '1'
            && window.GuestTripLiveMap
            && window.GuestTripLiveMap.refitSheetCamera) {
            runSheetCameraRefit();
        }
    });

    window.GuestTripSheet = {
        sync: syncForBookingMode,
        collapse: function () { setCollapsed(true); },
        expand: function () { setCollapsed(false); },
        resetUserToggle: function () {
            delete sheet.dataset.userToggled;
        },
        syncLocateFabLift: syncLocateFabLift,
        refitCamera: scheduleSheetCameraRefit,
    };

    syncForBookingMode();
    syncLocateFabLift();
})();
