/**
 * Sheet thông tin chuyến — kéo xuống thu nhỏ, kéo lên xem chi tiết.
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

    /** Nút định vị luôn nằm ngay trên mép sheet thông tin. */
    function syncLocateFabLift() {
        var locateBtn = document.getElementById('guest-trip-locate-btn');
        if (!isSearching()) {
            root.style.removeProperty('--guest-trip-sheet-lift');
            if (locateBtn) {
                locateBtn.style.bottom = '';
            }
            return;
        }

        var height = sheet.getBoundingClientRect().height || 0;
        var gap = 10;
        var lift = Math.max(0, Math.round(height + gap));
        root.style.setProperty('--guest-trip-sheet-lift', lift + 'px');

        if (locateBtn && window.innerWidth < 768) {
            // Đáy sheet = trên dock; FAB cách mép sheet `gap`.
            locateBtn.style.bottom = 'calc(var(--customer-dock-height, 3.85rem) + ' + lift + 'px)';
        } else if (locateBtn) {
            locateBtn.style.bottom = '';
        }
    }

    function setCollapsed(collapsed, options) {
        options = options || {};
        var nextCollapsed = !!collapsed;
        var wasCollapsed = sheet.classList.contains('is-collapsed');
        // Poll/sync lặp lại cùng trạng thái — không resize/refit (tránh ghim camera).
        if (nextCollapsed === wasCollapsed && !options.force) {
            syncLocateFabLift();
            return;
        }

        sheet.classList.toggle('is-collapsed', nextCollapsed);
        sheet.classList.toggle('is-expanded', !nextCollapsed);
        handle.setAttribute('aria-expanded', nextCollapsed ? 'false' : 'true');
        window.requestAnimationFrame(function () {
            syncLocateFabLift();
            if (window.GuestTripLiveMap && window.GuestTripLiveMap.resize) {
                window.GuestTripLiveMap.resize();
                window.setTimeout(function () {
                    window.GuestTripLiveMap.resize();
                    syncLocateFabLift();
                    // Chỉ căn lại khi sheet thật sự đổi cao (thu/mở), không phải mỗi poll.
                    if (options.refitCamera && isSearching() && window.GuestTripLiveMap.refitSearchCamera) {
                        window.GuestTripLiveMap.refitSearchCamera();
                    }
                }, 300);
            }
        });
    }

    function isSearching() {
        return card.classList.contains('is-searching');
    }

    function syncForBookingMode() {
        if (!isSearching()) {
            setCollapsed(false, { refitCamera: false });
            return;
        }
        // Đang tìm: mặc định mở sheet một lần; không refit camera trên mỗi poll.
        if (!sheet.dataset.userToggled && sheet.classList.contains('is-collapsed')) {
            setCollapsed(false, { refitCamera: true });
        } else {
            syncLocateFabLift();
        }
    }

    handle.addEventListener('click', function () {
        if (!isSearching()) {
            return;
        }
        sheet.dataset.userToggled = '1';
        setCollapsed(!sheet.classList.contains('is-collapsed'), { refitCamera: true });
    });

    function onPointerDown(event) {
        if (!isSearching()) {
            return;
        }
        dragging = true;
        startY = event.clientY != null ? event.clientY : (event.touches && event.touches[0] ? event.touches[0].clientY : 0);
    }

    function onPointerUp(event) {
        if (!dragging || !isSearching()) {
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
        // Kéo xuống → thu nhỏ; kéo lên → mở xem.
        setCollapsed(delta > 0, { refitCamera: true });
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
        if (event.target === sheet) {
            syncLocateFabLift();
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
    };

    syncForBookingMode();
    syncLocateFabLift();
})();
