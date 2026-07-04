/**
 * Lưu phiên đặt chuyến (sessionStorage): mã chuyến + QR giới thiệu.
 * Còn section này = chưa đặt được cuốc mới; mất section = có thể đặt lại.
 */
(function () {
    var STORAGE_KEY = 'guest_booking_session';
    var QR_SMALL = 72;
    var QR_LARGE = 220;
    var qrLibLoading = false;
    var rootEl = null;
    var currentReferralUrl = '';
    var overlayBound = false;

    function normalizePayload(raw) {
        if (!raw || !raw.trip_code) {
            return null;
        }

        var referralCode = raw.referral_code || raw.code || '';
        var referralUrl = raw.referral_url || raw.url || '';

        if (!referralUrl && referralCode) {
            referralUrl = window.location.origin + '/?ref=' + encodeURIComponent(referralCode);
        }

        return {
            trip_code: String(raw.trip_code),
            booking_reference: raw.booking_reference ? String(raw.booking_reference) : '',
            contact_phone: raw.contact_phone ? String(raw.contact_phone) : '',
            referral_code: referralCode ? String(referralCode) : '',
            referral_url: referralUrl,
            referral_discount_percent: Number(raw.referral_discount_percent || raw.discount_percent) || 0,
            booking: raw.booking && typeof raw.booking === 'object' ? raw.booking : null,
        };
    }

    function load() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            return normalizePayload(JSON.parse(raw));
        } catch (e) {
            return null;
        }

    }

    function save(raw) {
        var payload = normalizePayload(raw);
        if (!payload) {
            return null;
        }

        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {}

        render(payload);
        return payload;
    }

    function clear() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
        closeQrOverlay();
        hide();
    }

    function hide() {
        if (!rootEl) {
            rootEl = document.getElementById('booking-active-session');
        }
        if (rootEl) {
            rootEl.classList.add('d-none');
        }
    }

    function loadQrLib(cb) {
        if (typeof QRCode !== 'undefined') {
            cb();
            return;
        }
        if (qrLibLoading) {
            var timer = setInterval(function () {
                if (typeof QRCode !== 'undefined') {
                    clearInterval(timer);
                    cb();
                }
            }, 50);
            return;
        }
        qrLibLoading = true;
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
        script.onload = cb;
        document.head.appendChild(script);
    }

    function drawQr(container, url, size) {
        if (!container || !url) {
            return;
        }
        container.innerHTML = '';
        new QRCode(container, {
            text: url,
            width: size,
            height: size,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }

    function renderQr(url) {
        var wrap = document.getElementById('booking-active-referral-qr');
        if (!wrap || !url) {
            return;
        }

        currentReferralUrl = url;
        loadQrLib(function () {
            drawQr(wrap, url, QR_SMALL);
        });
    }

    function renderLargeQr(url) {
        var wrap = document.getElementById('booking-active-referral-qr-large');
        if (!wrap || !url) {
            return;
        }

        loadQrLib(function () {
            drawQr(wrap, url, QR_LARGE);
        });
    }

    function openQrOverlay() {
        if (!currentReferralUrl) {
            return;
        }

        var overlay = document.getElementById('booking-active-referral-qr-overlay');
        if (!overlay) {
            return;
        }

        renderLargeQr(currentReferralUrl);
        overlay.classList.remove('d-none');
        overlay.removeAttribute('hidden');
        document.body.classList.add('booking-active-referral-qr-open');
    }

    function closeQrOverlay() {
        var overlay = document.getElementById('booking-active-referral-qr-overlay');
        if (!overlay) {
            return;
        }

        overlay.classList.add('d-none');
        overlay.setAttribute('hidden', 'hidden');
        document.body.classList.remove('booking-active-referral-qr-open');

        var large = document.getElementById('booking-active-referral-qr-large');
        if (large) {
            large.innerHTML = '';
        }
    }

    function bindOverlay() {
        if (overlayBound) {
            return;
        }
        overlayBound = true;

        var openBtn = document.getElementById('booking-active-referral-qr-btn');
        if (openBtn) {
            openBtn.addEventListener('click', openQrOverlay);
        }

        document.querySelectorAll('[data-close-referral-qr]').forEach(function (el) {
            el.addEventListener('click', closeQrOverlay);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeQrOverlay();
            }
        });
    }

    function updateNotes(phone) {
        var contactNote = document.getElementById('booking-active-session-note-contact');
        var safePhone = phone ? String(phone) : '';

        if (contactNote) {
            if (safePhone) {
                contactNote.innerHTML = 'Chúng tôi sẽ liên hệ bạn sớm nhất qua số điện thoại <strong>'
                    + safePhone
                    + '</strong>.';
            } else {
                contactNote.textContent = 'Chúng tôi sẽ liên hệ bạn sớm nhất qua số điện thoại của bạn.';
            }
        }
    }

    function setRow(rowId, valueId, text, visible) {
        var row = document.getElementById(rowId);
        var value = document.getElementById(valueId);
        if (!row || !value) {
            return;
        }
        if (!visible || !text) {
            row.hidden = true;
            value.textContent = '—';
            return;
        }
        value.textContent = text;
        row.hidden = false;
    }

    function updateDriverPanel(booking) {
        var panel = document.getElementById('booking-active-driver-panel');
        if (!panel) {
            return;
        }

        var driver = booking && booking.driver ? booking.driver : null;
        if (!driver || !booking.has_driver) {
            panel.classList.add('d-none');
            return;
        }

        var nameText = driver.name || '—';
        if (driver.code) {
            nameText += ' (' + driver.code + ')';
        }

        setRow('booking-active-driver-vehicle-name-row', 'booking-active-driver-vehicle-name', driver.vehicle_name, !!driver.vehicle_name);
        setRow('booking-active-driver-type-row', 'booking-active-driver-vehicle-type', driver.vehicle_type_label, !!driver.vehicle_type_label);
        setRow('booking-active-driver-plate-row', 'booking-active-driver-vehicle-plate', driver.vehicle_plate, !!driver.vehicle_plate);

        var proximity = driver.proximity_hint
            || (booking.driver_proximity_hint)
            || (booking.driver_distance_label && booking.driver_eta_label
                ? ('Còn ~' + booking.driver_distance_label + ' · dự kiến ' + booking.driver_eta_label)
                : (booking.driver_distance_label ? ('Còn ~' + booking.driver_distance_label) : (booking.driver_eta_label ? ('Dự kiến ' + booking.driver_eta_label) : '')));

        setRow('booking-active-driver-proximity-row', 'booking-active-driver-proximity', proximity, !!proximity);

        var nameEl = document.getElementById('booking-active-driver-name');
        if (nameEl) {
            nameEl.textContent = nameText;
        }

        panel.classList.remove('d-none');
    }

    function updateBookingSnapshot(booking) {
        updateDriverPanel(booking || null);
    }

    function render(payload) {
        payload = payload || load();
        if (!payload) {
            hide();
            return;
        }

        rootEl = document.getElementById('booking-active-session');
        if (!rootEl) {
            return;
        }

        var tripCodeEl = document.getElementById('booking-active-trip-code');
        var referralWrap = document.getElementById('booking-active-referral-wrap');

        if (tripCodeEl) {
            tripCodeEl.textContent = payload.trip_code;
        }

        updateNotes(payload.contact_phone);
        updateBookingSnapshot(payload.booking || null);

        if (payload.referral_code && payload.referral_url && referralWrap) {
            referralWrap.classList.remove('d-none');
            renderQr(payload.referral_url);
            bindOverlay();
        } else if (referralWrap) {
            referralWrap.classList.add('d-none');
        }

        rootEl.classList.remove('d-none');
    }

    function saveFromBooking(booking, extra) {
        if (!booking) {
            return null;
        }

        return save({
            trip_code: booking.trip_code,
            booking_reference: booking.booking_reference || (extra && extra.booking_reference) || '',
            contact_phone: (extra && extra.contact_phone) || '',
            referral_code: (extra && extra.referral_code) || '',
            referral_url: (extra && extra.referral_url) || '',
            referral_discount_percent: (extra && extra.referral_discount_percent) || 0,
            booking: booking,
        });
    }

    function syncWithCheckResult(data) {
        if (data && data.duplicate && data.reason === 'browser_cancel') {
            return;
        }

        if (data && data.duplicate && data.booking && (data.reason === 'browser' || data.reason === 'phone')) {
            var existing = load() || {};
            saveFromBooking(data.booking, {
                booking_reference: data.booking.booking_reference || existing.booking_reference,
                contact_phone: existing.contact_phone,
                referral_code: existing.referral_code,
                referral_url: existing.referral_url,
                referral_discount_percent: existing.referral_discount_percent,
            });
            updateBookingSnapshot(data.booking);
            return;
        }

        if (!data || !data.duplicate) {
            clear();
        }
    }

    function hasActiveSession() {
        return !!load();
    }

    function pollActiveBookingStatus() {
        var checkUrl = window.__bookingCheckDuplicateUrl;
        if (!checkUrl || !hasActiveSession()) {
            return;
        }

        var payload = load();
        if (!payload) {
            return;
        }

        var params = new URLSearchParams();
        if (payload.contact_phone) {
            params.set('contact_phone', payload.contact_phone);
        }
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.getBrowserSessionId) {
            var browserId = window.BookingBrowserGuard.getBrowserSessionId();
            if (browserId) {
                params.set('booking_browser_id', browserId);
            }
        }

        fetch(checkUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.duplicate && data.booking) {
                    updateBookingSnapshot(data.booking);
                    var stored = load();
                    if (stored) {
                        stored.booking = data.booking;
                        try {
                            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
                        } catch (e) {}
                    }
                    return;
                }

                if (!data || !data.duplicate) {
                    clear();
                }
            })
            .catch(function () {});
    }

    function init() {
        bindOverlay();
        var flashPayload = window.__bookingSuccess;
        if (flashPayload) {
            save(flashPayload);
        } else {
            render();
        }

        pollActiveBookingStatus();
        window.setInterval(pollActiveBookingStatus, 15000);
    }

    window.BookingActiveSession = {
        STORAGE_KEY: STORAGE_KEY,
        load: load,
        save: save,
        clear: clear,
        render: render,
        hasActiveSession: hasActiveSession,
        syncWithCheckResult: syncWithCheckResult,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
