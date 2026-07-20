/**
 * Sheet đón khách (giai đoạn assigned) — vuốt lên xem đầy đủ, vuốt xuống thu nhỏ (peek).
 * Mỗi lần peek↔full: đẩy camera (mũi tên / ghim) vào giữa mép trên map ↔ mép trên popup.
 */
(function () {
    var sheet = document.querySelector('[data-driver-pickup-sheet]');
    var handle = sheet ? sheet.querySelector('[data-driver-pickup-sheet-handle]') : null;
    var panel = document.getElementById('driver-bottom-panel');
    if (!sheet || !handle || !panel) {
        return;
    }

    var THRESHOLD = 32;
    var startY = 0;
    var dragging = false;
    var syncTimers = [];

    function occludedBottomPx() {
        var top = sheet.getBoundingClientRect().top;
        return Math.max(0, Math.round(window.innerHeight - top));
    }

    function syncMapCamera() {
        if (window.DriverLiveMap && window.DriverLiveMap.setSheetState) {
            window.DriverLiveMap.setSheetState({
                peek: sheet.classList.contains('is-peek'),
                heightPx: occludedBottomPx(),
            });
        }
    }

    function clearSyncTimers() {
        syncTimers.forEach(function (id) {
            window.clearTimeout(id);
        });
        syncTimers = [];
    }

    function scheduleSyncMapCamera() {
        clearSyncTimers();
        window.requestAnimationFrame(function () {
            syncMapCamera();
            syncTimers.push(window.setTimeout(syncMapCamera, 120));
            syncTimers.push(window.setTimeout(syncMapCamera, 300));
        });
    }

    function setPeek(peek) {
        var wasPeek = sheet.classList.contains('is-peek');
        if (peek === wasPeek) {
            return;
        }
        sheet.classList.toggle('is-peek', peek);
        sheet.classList.toggle('is-full', !peek);
        handle.setAttribute('aria-expanded', peek ? 'false' : 'true');
        if (peek && window.TripChat && typeof window.TripChat.closeAllOpen === 'function') {
            window.TripChat.closeAllOpen();
        }
        scheduleSyncMapCamera();
    }

    handle.addEventListener('click', function () {
        setPeek(!sheet.classList.contains('is-peek'));
    });

    function onPointerDown(event) {
        dragging = true;
        startY = event.clientY != null ? event.clientY : (event.touches && event.touches[0] ? event.touches[0].clientY : 0);
    }

    function onPointerUp(event) {
        if (!dragging) {
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
        // Kéo xuống (delta > 0) → thu nhỏ; kéo lên → mở xem đầy đủ.
        setPeek(delta > 0);
    }

    handle.addEventListener('touchstart', onPointerDown, { passive: true });
    handle.addEventListener('touchend', onPointerUp, { passive: true });
    handle.addEventListener('mousedown', onPointerDown);
    window.addEventListener('mouseup', onPointerUp);

    if (typeof ResizeObserver !== 'undefined') {
        var ro = new ResizeObserver(function () {
            syncMapCamera();
        });
        ro.observe(sheet);
        ro.observe(panel);
    } else {
        window.addEventListener('resize', syncMapCamera);
    }

    sheet.addEventListener('transitionend', function (event) {
        if (event.target === sheet || sheet.contains(event.target)) {
            syncMapCamera();
        }
    });

    window.DriverPickupSheet = {
        collapse: function () { setPeek(true); },
        expand: function () { setPeek(false); },
        syncMapCamera: syncMapCamera,
    };

    window.requestAnimationFrame(syncMapCamera);
})();
