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

    function setCollapsed(collapsed) {
        sheet.classList.toggle('is-collapsed', !!collapsed);
        sheet.classList.toggle('is-expanded', !collapsed);
        handle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        if (window.GuestTripLiveMap && window.GuestTripLiveMap.resize) {
            window.requestAnimationFrame(function () {
                window.GuestTripLiveMap.resize();
                window.setTimeout(function () {
                    window.GuestTripLiveMap.resize();
                }, 300);
            });
        }
    }

    function isSearching() {
        return card.classList.contains('is-searching');
    }

    function syncForBookingMode() {
        if (!isSearching()) {
            setCollapsed(false);
            return;
        }
        // Đang tìm: mặc định mở sheet để thấy trạng thái + điểm đón/giá/hủy.
        // Người dùng vẫn kéo/chạm handle để thu map rộng hơn.
        if (!sheet.dataset.userToggled) {
            setCollapsed(false);
        }
    }

    handle.addEventListener('click', function () {
        if (!isSearching()) {
            return;
        }
        sheet.dataset.userToggled = '1';
        setCollapsed(!sheet.classList.contains('is-collapsed'));
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
        setCollapsed(delta > 0);
    }

    handle.addEventListener('touchstart', onPointerDown, { passive: true });
    handle.addEventListener('touchend', onPointerUp, { passive: true });
    handle.addEventListener('mousedown', onPointerDown);
    window.addEventListener('mouseup', onPointerUp);

    window.GuestTripSheet = {
        sync: syncForBookingMode,
        collapse: function () { setCollapsed(true); },
        expand: function () { setCollapsed(false); },
        resetUserToggle: function () {
            delete sheet.dataset.userToggled;
        },
    };
})();
