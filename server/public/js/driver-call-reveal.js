/**
 * Gọi khách qua tel: — sau 2 lần vẫn gọi thì hiện số để quay trực tiếp (kiểu Grab).
 */
(function () {
    var STORAGE_PREFIX = 'driver-call-attempts:';
    var REVEAL_AFTER = 2;

    function storageKey(bookingKey) {
        return STORAGE_PREFIX + (bookingKey || 'unknown');
    }

    function readAttempts(bookingKey) {
        try {
            return Math.max(0, parseInt(sessionStorage.getItem(storageKey(bookingKey)) || '0', 10) || 0);
        } catch (e) {
            return 0;
        }
    }

    function writeAttempts(bookingKey, count) {
        try {
            sessionStorage.setItem(storageKey(bookingKey), String(count));
        } catch (e) { /* noop */ }
    }

    function syncReveal(root) {
        var bookingKey = root.getAttribute('data-booking-key') || '';
        var attempts = readAttempts(bookingKey);
        var reveal = root.querySelector('[data-driver-call-reveal]');
        var label = root.querySelector('[data-driver-call-label]');
        var show = attempts >= REVEAL_AFTER;
        if (reveal) {
            reveal.classList.toggle('d-none', !show);
        }
        if (label) {
            label.textContent = show ? 'Gọi lại' : 'Gọi';
        }
        root.classList.toggle('is-call-revealed', show);
    }

    document.querySelectorAll('[data-driver-call-root]').forEach(syncReveal);

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-driver-call-btn]');
        if (!btn) {
            return;
        }
        var root = btn.closest('[data-driver-call-root]');
        if (!root) {
            return;
        }
        var bookingKey = root.getAttribute('data-booking-key') || '';
        writeAttempts(bookingKey, readAttempts(bookingKey) + 1);
        window.setTimeout(function () {
            syncReveal(root);
        }, 0);
    });
})();
