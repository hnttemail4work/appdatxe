/**
 * Splash: lần đầu trong ngày — hoặc sau khi đăng xuất.
 * Reload / chuyển trang trong ngày không hiện lại.
 */
(function () {
    var STORAGE_KEY = 'appdatxe:splashDay';
    var SHOW_MS = 900;
    var overlay = document.getElementById('app-splash');

    function todayKey() {
        var d = new Date();
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function clearDayFlag() {
        try {
            window.localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
    }

    function alreadyShownToday() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === todayKey();
        } catch (e) {
            return false;
        }
    }

    function markShownToday() {
        try {
            window.localStorage.setItem(STORAGE_KEY, todayKey());
        } catch (e) {}
    }

    window.AppSplash = {
        /** Gọi khi đăng xuất để lần vào sau hiện splash lại. */
        resetForLogout: clearDayFlag,
    };

    if (!overlay) {
        return;
    }

    // Ẩn sẵn — tránh flash khi reload.
    overlay.hidden = true;
    overlay.classList.add('d-none');
    overlay.classList.remove('is-visible', 'is-hiding');

    function hide() {
        overlay.classList.add('is-hiding');
        window.setTimeout(function () {
            overlay.hidden = true;
            overlay.classList.add('d-none');
            overlay.classList.remove('is-hiding', 'is-visible');
        }, 320);
    }

    function show() {
        overlay.hidden = false;
        overlay.classList.remove('d-none', 'is-hiding');
        overlay.classList.add('is-visible');
        window.setTimeout(hide, SHOW_MS);
    }

    if (alreadyShownToday()) {
        return;
    }

    markShownToday();
    show();
})();
