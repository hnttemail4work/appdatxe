/**
 * Mobile app shell — viewport height + cố định dock/FAB đáy màn hình (khách + tài xế).
 */
(function () {
    var DOCK_SELECTORS = '.customer-scroll-dock, .driver-app-dock';
    var FAB_SELECTOR = '.customer-contact-fab--fixed';

    function isMobileApp() {
        return document.body.classList.contains('app-shell--mobile-app');
    }

    function syncViewportHeight() {
        if (!isMobileApp()) {
            return;
        }

        var height = window.visualViewport ? window.visualViewport.height : window.innerHeight;
        document.documentElement.style.setProperty('--app-vh', (height * 0.01) + 'px');
    }

    function keyboardLikelyOpen() {
        var vv = window.visualViewport;
        return !!(vv && vv.height < window.innerHeight * 0.82);
    }

    function dockLiftPx() {
        if (!keyboardLikelyOpen()) {
            return 0;
        }

        var vv = window.visualViewport;
        if (!vv) {
            return 0;
        }

        return Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop));
    }

    function syncFixedBottomChrome() {
        if (!isMobileApp() || window.innerWidth >= 768) {
            document.querySelectorAll(DOCK_SELECTORS).forEach(function (el) {
                el.style.bottom = '';
            });
            document.querySelectorAll(FAB_SELECTOR).forEach(function (el) {
                el.style.bottom = '';
            });
            return;
        }

        var lift = dockLiftPx();
        var dockBottom = lift > 0 ? (lift + 'px') : '0px';

        document.querySelectorAll(DOCK_SELECTORS).forEach(function (el) {
            el.style.bottom = dockBottom;
        });

        document.querySelectorAll(FAB_SELECTOR).forEach(function (el) {
            if (lift <= 0) {
                el.style.bottom = '';
                return;
            }

            var dock = document.querySelector(DOCK_SELECTORS);
            var dockHeight = dock ? dock.offsetHeight : 56;
            el.style.bottom = 'calc(' + dockBottom + ' + ' + dockHeight + 'px + 0.45rem)';
        });
    }

    function syncMobileChrome() {
        syncViewportHeight();
        syncFixedBottomChrome();
    }

    window.MobileAppChrome = {
        sync: syncMobileChrome,
    };

    syncMobileChrome();
    document.addEventListener('DOMContentLoaded', syncMobileChrome);
    window.addEventListener('load', syncMobileChrome);
    window.addEventListener('resize', syncMobileChrome);
    window.addEventListener('orientationchange', syncMobileChrome);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncMobileChrome);
    }
})();
