/**
 * Pull-to-refresh nhẹ cho trang ví khách / tài xế.
 */
(function () {
    var THRESHOLD = 72;
    var MAX_PULL = 120;
    var targets = document.querySelectorAll('[data-wallet-ptr]');
    if (!targets.length) {
        return;
    }

    targets.forEach(function (el) {
        var startY = 0;
        var pulling = false;
        var indicator = el.querySelector('[data-wallet-ptr-indicator]');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'wallet-ptr-indicator';
            indicator.setAttribute('data-wallet-ptr-indicator', '');
            indicator.textContent = 'Kéo để làm mới';
            el.insertBefore(indicator, el.firstChild);
        }

        function setPull(px) {
            var shown = Math.max(0, Math.min(MAX_PULL, px));
            indicator.style.height = shown ? Math.round(shown * 0.5) + 'px' : '0';
            indicator.style.opacity = shown > 8 ? '1' : '0';
            indicator.textContent = shown >= THRESHOLD ? 'Thả để làm mới' : 'Kéo để làm mới';
        }

        function canPull() {
            var scroller = el.closest('.driver-overlay-panels') || el.closest('.customer-page') || el;
            return (scroller.scrollTop || 0) <= 0 && (window.scrollY || document.documentElement.scrollTop || 0) <= 0;
        }

        el.addEventListener('touchstart', function (e) {
            if (!canPull() || !e.touches || !e.touches[0]) {
                pulling = false;
                return;
            }
            pulling = true;
            startY = e.touches[0].clientY;
        }, { passive: true });

        el.addEventListener('touchmove', function (e) {
            if (!pulling || !e.touches || !e.touches[0]) {
                return;
            }
            var dy = e.touches[0].clientY - startY;
            if (dy <= 0) {
                setPull(0);
                return;
            }
            setPull(dy);
        }, { passive: true });

        el.addEventListener('touchend', function (e) {
            if (!pulling) {
                return;
            }
            pulling = false;
            var dy = e.changedTouches && e.changedTouches[0]
                ? e.changedTouches[0].clientY - startY
                : 0;
            setPull(0);
            if (dy < THRESHOLD) {
                return;
            }
            indicator.textContent = 'Đang làm mới…';
            indicator.style.height = '28px';
            indicator.style.opacity = '1';
            var url = el.getAttribute('data-wallet-ptr') || window.location.href;
            window.setTimeout(function () {
                window.location.href = url;
            }, 120);
        }, { passive: true });
    });
})();
