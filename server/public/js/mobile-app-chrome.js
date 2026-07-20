/**
 * Mobile app shell — viewport height + cố định dock/FAB đáy màn hình (khách + tài xế).
 */
(function () {
    var DOCK_SELECTORS = '.customer-scroll-dock, .driver-app-dock';
    var FAB_SELECTOR = '.trip-chat-fab, .trip-locate-fab, .trip-sos-fab';
    var FAB_STACK_GAP = '3.8rem'; /* locate 3.25rem + gap .55rem — SOS ngồi trên locate */

    function isMobileApp() {
        return document.body.classList.contains('app-shell--mobile-app');
    }

    function isGuestTripSheetMode() {
        return document.body.classList.contains('guest-trip-searching')
            || document.body.classList.contains('guest-trip-tracking');
    }

    function isGuestTripFab(el) {
        return el.classList.contains('trip-locate-fab')
            || el.classList.contains('trip-sos-fab');
    }

    function syncGuestTripFabs() {
        if (window.GuestTripSheet && window.GuestTripSheet.syncLocateFabLift) {
            window.GuestTripSheet.syncLocateFabLift();
        }
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
                // Giữ neo sheet chuyến — đừng xoá bottom của locate/SOS.
                if (isGuestTripFab(el) && isGuestTripSheetMode()) {
                    return;
                }
                el.style.bottom = '';
            });
            if (isGuestTripSheetMode()) {
                syncGuestTripFabs();
            }
            return;
        }

        var lift = dockLiftPx();
        var dockBottom = lift > 0 ? (lift + 'px') : '0px';

        document.querySelectorAll(DOCK_SELECTORS).forEach(function (el) {
            el.style.bottom = dockBottom;
        });

        document.querySelectorAll(FAB_SELECTOR).forEach(function (el) {
            // Nút locate neo trong map booking — không đẩy theo dock/keyboard.
            if (el.classList.contains('be-step__map-locate')) {
                el.style.bottom = '';
                return;
            }

            // Locate + SOS khi đang tìm/theo dõi chuyến: neo theo mép sheet.
            if (isGuestTripFab(el) && isGuestTripSheetMode()) {
                syncGuestTripFabs();
                return;
            }

            if (lift <= 0) {
                el.style.bottom = '';
                return;
            }

            var dock = document.querySelector(DOCK_SELECTORS);
            var dockHeight = dock ? dock.offsetHeight : 56;
            if (el.classList.contains('trip-sos-fab')) {
                el.style.bottom = 'calc(' + dockBottom + ' + ' + dockHeight + 'px + 0.45rem + ' + FAB_STACK_GAP + ')';
            } else {
                el.style.bottom = 'calc(' + dockBottom + ' + ' + dockHeight + 'px + 0.45rem)';
            }
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
