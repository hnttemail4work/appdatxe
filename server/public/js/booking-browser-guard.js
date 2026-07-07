/**

 * Chặn đặt cuốc theo phiên trình duyệt: một cuốc đang chạy / hủy quá nhiều lần.

 */

(function () {

    var STORAGE_ID_KEY = 'booking_browser_session_id';

    var STORAGE_CANCEL_KEY = 'guest_browser_cancel_count';

    var BLOCK_LIMIT = Number(window.__guestBrowserCancelBlockLimit) || 3;



    var blockState = {

        cancelBlocked: false,

        activeBlocked: false,

        message: '',

        booking: null,

    };



    function getBrowserSessionId() {

        var id = '';

        try {

            id = sessionStorage.getItem(STORAGE_ID_KEY) || '';

            if (!id) {

                id = (window.crypto && window.crypto.randomUUID)

                    ? window.crypto.randomUUID()

                    : ('bb-' + Date.now() + '-' + Math.random().toString(16).slice(2));

                sessionStorage.setItem(STORAGE_ID_KEY, id);

            }

        } catch (e) {

            id = 'bb-fallback';

        }



        return id;

    }



    function getCancelCount() {

        var serverCount = Number(window.__guestBrowserCancelCount) || 0;

        var localCount = 0;

        try {

            localCount = Number(sessionStorage.getItem(STORAGE_CANCEL_KEY)) || 0;

        } catch (e) {

            localCount = 0;

        }



        return Math.max(serverCount, localCount);

    }



    function setCancelCount(count) {

        window.__guestBrowserCancelCount = count;

        try {

            sessionStorage.setItem(STORAGE_CANCEL_KEY, String(count));

        } catch (e) {}

        refreshBlockStateFromLocal();

        syncBanner();

    }



    function recordCancelSuccess(count) {

        if (typeof count === 'number' && count > 0) {

            setCancelCount(count);

            return;

        }

        setCancelCount(getCancelCount() + 1);

    }



    function cancelBlockMessage() {

        return 'Đã hủy quá nhiều lần trên trình duyệt này, vui lòng thử lại sau hoặc liên hệ tổng đài để biết thêm thông tin chi tiết.';

    }



    function activeBookingBlockMessage() {

        return 'Đang có chuyến chưa hoàn thành, vui lòng hoàn tất chuyến.';

    }



    function refreshBlockStateFromLocal() {

        blockState.cancelBlocked = getCancelCount() >= BLOCK_LIMIT;

        if (blockState.cancelBlocked) {

            blockState.message = cancelBlockMessage();

        } else if (!blockState.activeBlocked) {

            blockState.message = '';

        }

    }



    function applyCheckResult(data) {

        blockState.booking = null;

        blockState.activeBlocked = false;

        refreshBlockStateFromLocal();



        if (!data || !data.duplicate) {

            if (window.BookingActiveSession && window.BookingActiveSession.syncWithCheckResult) {

                window.BookingActiveSession.syncWithCheckResult(data);

            }

            syncBanner();

            return data;

        }



        if (data.reason === 'browser_cancel') {

            blockState.cancelBlocked = true;

            blockState.message = data.message || cancelBlockMessage();

        } else if (data.reason === 'browser' || data.reason === 'phone') {

            blockState.activeBlocked = true;

            blockState.booking = data.booking || null;

            blockState.message = data.message || activeBookingBlockMessage();

        }

        if (window.BookingActiveSession && window.BookingActiveSession.syncWithCheckResult) {

            window.BookingActiveSession.syncWithCheckResult(data);

        }

        syncBanner();

        return data;

    }



    function hasActiveBlock() {

        return blockState.activeBlocked;

    }



    function isBookingBlocked() {

        refreshBlockStateFromLocal();

        return blockState.cancelBlocked || blockState.activeBlocked;

    }



    function blockMessage() {

        refreshBlockStateFromLocal();

        if (blockState.message) {

            return blockState.message;

        }

        if (blockState.cancelBlocked) {

            return cancelBlockMessage();

        }

        if (blockState.activeBlocked) {

            return activeBookingBlockMessage();

        }

        return '';

    }



    function appendHeaders(headers) {

        headers = headers || {};

        headers['X-Booking-Browser-Id'] = getBrowserSessionId();

        return headers;

    }



    function checkBookingEligibility(contactPhone) {

        var url = window.__bookingCheckDuplicateUrl;

        if (!url) {

            return Promise.resolve(null);

        }



        var params = new URLSearchParams();

        params.set('booking_browser_id', getBrowserSessionId());

        if (contactPhone) {

            params.set('contact_phone', contactPhone);

        }



        return fetch(url + '?' + params.toString(), {

            headers: appendHeaders({ Accept: 'application/json' }),

        })

            .then(function (r) {

                if (!r.ok) {

                    throw new Error('duplicate_check_failed');

                }

                return r.json();

            })

            .then(applyCheckResult)

            .catch(function () {

                return null;

            });

    }



    function syncBanner() {

        var banner = document.getElementById('booking-browser-guard-banner');

        var flashStack = document.getElementById('app-flash-stack');

        if (!isBookingBlocked()) {

            if (banner) {

                banner.classList.add('d-none');

            }

            if (flashStack && window.AppFlash && window.AppFlash.clear) {

                window.AppFlash.clear(flashStack);

            }

            return;

        }

        var msg = blockMessage();

        if (banner) {

            if (flashStack && window.AppFlash && window.AppFlash.clear) {

                window.AppFlash.clear(flashStack);

            }

            banner.classList.remove('d-none');

            var textEl = banner.querySelector('.booking-browser-guard-text');

            if (textEl) {

                textEl.textContent = msg;

            }

            return;

        }

        if (window.AppFlash && window.AppFlash.show && flashStack) {

            window.AppFlash.show(msg, {

                variant: 'warning',

                title: 'Chưa thể đặt cuốc mới',

                target: '#app-flash-stack',

                autoDismiss: 0,

            });

        }

    }



    function alertIfBlocked() {

        if (!isBookingBlocked()) {

            return false;

        }

        syncBanner();

        var focusTarget = document.getElementById('booking-browser-guard-banner')

            || document.querySelector('#app-flash-stack .app-flash-banner');

        if (focusTarget) {

            window.requestAnimationFrame(function () {

                focusTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            });

        }

        return true;

    }



    function ensureHiddenInput(form) {

        if (!form) {

            return null;

        }

        var input = form.querySelector('#booking-browser-id');

        if (!input) {

            input = document.createElement('input');

            input.type = 'hidden';

            input.name = 'booking_browser_id';

            input.id = 'booking-browser-id';

            form.appendChild(input);

        }

        input.value = getBrowserSessionId();

        return input;

    }



    window.BookingBrowserGuard = {

        getBrowserSessionId: getBrowserSessionId,

        getCancelCount: getCancelCount,

        recordCancelSuccess: recordCancelSuccess,

        isBookingBlocked: isBookingBlocked,

        blockMessage: blockMessage,

        appendHeaders: appendHeaders,

        checkBookingEligibility: checkBookingEligibility,

        applyCheckResult: applyCheckResult,

        hasActiveBlock: hasActiveBlock,

        syncBanner: syncBanner,

        alertIfBlocked: alertIfBlocked,

        ensureHiddenInput: ensureHiddenInput,

        BLOCK_LIMIT: BLOCK_LIMIT,

    };



    refreshBlockStateFromLocal();



    function initGuard() {

        syncBanner();

        checkBookingEligibility('');

    }



    if (document.readyState === 'loading') {

        document.addEventListener('DOMContentLoaded', initGuard);

    } else {

        initGuard();

    }

})();

