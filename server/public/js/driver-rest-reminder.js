/**
 * Nhắc nghỉ sau 4 giờ hoạt động liên tục (online/on-trip).
 */
(function () {
    var KEY_START = 'appdatxe:driverDutyStartedAt';
    var KEY_SNOOZE = 'appdatxe:driverRestSnoozeUntil';
    var FOUR_H_MS = 4 * 60 * 60 * 1000;
    var CHECK_MS = 60 * 1000;

    function now() {
        return Date.now();
    }

    function isOnDuty() {
        var page = document.querySelector('[data-driver-tabs]');
        if (!page) {
            return false;
        }
        var bar = document.getElementById('driver-location-bar');
        if (bar && bar.getAttribute('data-driver-paused') === '1') {
            return false;
        }
        return true;
    }

    function ensureStart() {
        try {
            if (!window.localStorage.getItem(KEY_START)) {
                window.localStorage.setItem(KEY_START, String(now()));
            }
        } catch (e) {}
    }

    function clearStart() {
        try {
            window.localStorage.removeItem(KEY_START);
        } catch (e) {}
    }

    function snoozed() {
        try {
            var until = Number(window.localStorage.getItem(KEY_SNOOZE) || 0);
            return until > now();
        } catch (e) {
            return false;
        }
    }

    function showModal() {
        if (document.getElementById('driver-rest-reminder-modal')) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.id = 'driver-rest-reminder-modal';
        wrap.className = 'driver-rest-reminder';
        wrap.innerHTML =
            '<div class="driver-rest-reminder__card" role="alertdialog" aria-modal="true">'
            + '<h3 class="driver-rest-reminder__title">Nên nghỉ ngơi</h3>'
            + '<p class="driver-rest-reminder__body">Bạn đã hoạt động liên tục hơn 4 giờ. Hãy nghỉ ngơi để đảm bảo an toàn. Mọi vấn đề phát sinh do mệt mỏi, bạn tự chịu trách nhiệm.</p>'
            + '<button type="button" class="btn btn-primary w-100" data-rest-dismiss>Bỏ qua nhắc nhở</button>'
            + '</div>';
        document.body.appendChild(wrap);
        wrap.querySelector('[data-rest-dismiss]').addEventListener('click', function () {
            try {
                window.localStorage.setItem(KEY_SNOOZE, String(now() + FOUR_H_MS));
                window.localStorage.setItem(KEY_START, String(now()));
            } catch (e) {}
            wrap.remove();
        });
    }

    function tick() {
        if (!isOnDuty()) {
            clearStart();
            return;
        }
        ensureStart();
        if (snoozed()) {
            return;
        }
        try {
            var started = Number(window.localStorage.getItem(KEY_START) || 0);
            if (started && (now() - started) >= FOUR_H_MS) {
                showModal();
            }
        } catch (e) {}
    }

    tick();
    window.setInterval(tick, CHECK_MS);
})();
