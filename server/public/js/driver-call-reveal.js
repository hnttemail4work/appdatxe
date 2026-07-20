/**
 * Gọi qua app: 2 lần đầu chọn kết quả → lưu tin nhắn;
 * lần 3+ = tel: hiện số thật.
 * Dùng chung tài xế ([data-driver-call-*]) và khách ([data-guest-call-*]).
 */
(function () {
    var APP_CALL_LIMIT = 2;

    function isGuestRoot(root) {
        return root.hasAttribute('data-guest-call-root');
    }

    function storagePrefix(root) {
        return isGuestRoot(root) ? 'guest-call-attempts:' : 'driver-call-attempts:';
    }

    function storageKey(root, bookingKey) {
        return storagePrefix(root) + (bookingKey || 'unknown');
    }

    function readAttempts(root, bookingKey) {
        try {
            return Math.max(0, parseInt(sessionStorage.getItem(storageKey(root, bookingKey)) || '0', 10) || 0);
        } catch (e) {
            return 0;
        }
    }

    function writeAttempts(root, bookingKey, count) {
        try {
            sessionStorage.setItem(storageKey(root, bookingKey), String(count));
        } catch (e) { /* noop */ }
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function callBtn(root) {
        return root.querySelector('[data-driver-call-btn], [data-guest-call-btn]');
    }

    function callReveal(root) {
        return root.querySelector('[data-driver-call-reveal], [data-guest-call-reveal]');
    }

    function callLabel(root) {
        return root.querySelector('[data-driver-call-label], [data-guest-call-label]');
    }

    function syncReveal(root) {
        var bookingKey = root.getAttribute('data-booking-key') || '';
        var attempts = readAttempts(root, bookingKey);
        var reveal = callReveal(root);
        var label = callLabel(root);
        var btn = callBtn(root);
        var phoneTel = root.getAttribute('data-phone-tel') || '';
        var phoneReady = attempts >= APP_CALL_LIMIT;
        var peer = isGuestRoot(root) ? 'tài xế' : 'khách';

        if (reveal) {
            reveal.classList.toggle('d-none', !phoneReady);
        }
        if (label) {
            label.textContent = phoneReady ? 'Gọi số' : 'Gọi';
        }
        if (btn) {
            if (phoneReady && phoneTel) {
                btn.setAttribute('href', 'tel:' + phoneTel);
                btn.setAttribute('title', 'Gọi số điện thoại ' + peer);
                btn.setAttribute('aria-label', 'Gọi số điện thoại ' + peer);
            } else {
                btn.setAttribute('href', '#');
                btn.setAttribute('title', 'Gọi ' + peer + ' qua app');
                btn.setAttribute('aria-label', 'Gọi ' + peer + ' qua app');
            }
        }
        root.classList.toggle('is-call-revealed', phoneReady);
    }

    function pickAppCallOutcome(root) {
        var peer = isGuestRoot(root) ? 'tài xế' : 'khách';
        if (window.AppDialog && typeof window.AppDialog.choose === 'function') {
            return window.AppDialog.choose({
                title: 'Gọi ' + peer + ' qua app',
                message: 'Sau khi gọi, chọn kết quả để lưu vào tin nhắn chuyến.',
                confirmText: 'Đã nghe máy',
                cancelText: 'Cuộc gọi nhỡ',
                variant: 'warning',
            }).then(function (choice) {
                if (choice === 'confirm') {
                    return 'answered';
                }
                if (choice === 'cancel') {
                    return 'missed';
                }
                return null;
            });
        }
        return Promise.resolve(null);
    }

    function postCallLog(root, url, outcome) {
        var body = { outcome: outcome };
        var ref = root.getAttribute('data-booking-reference') || '';
        if (isGuestRoot(root) && ref) {
            body.booking_reference = ref;
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) {
                    throw new Error((data && data.message) || 'Không lưu được lịch sử gọi.');
                }
                return data;
            });
        });
    }

    function injectChatMessage(root, message) {
        if (!message || !window.TripChat || typeof window.TripChat.injectMessage !== 'function') {
            return;
        }
        var ref = root.getAttribute('data-booking-reference') || '';
        window.TripChat.injectMessage(ref, message, isGuestRoot(root) ? 'customer' : 'driver');
    }

    function showError(message) {
        if (window.AppFlash && window.AppFlash.show) {
            window.AppFlash.show(message, { variant: 'danger', title: 'Không gọi được' });
            return;
        }
        if (window.AppDialog && window.AppDialog.alert) {
            window.AppDialog.alert(message, { variant: 'danger' });
        }
    }

    function syncAll() {
        document.querySelectorAll('[data-driver-call-root], [data-guest-call-root]').forEach(syncReveal);
    }

    syncAll();

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-driver-call-btn], [data-guest-call-btn]');
        if (!btn) {
            return;
        }
        var root = btn.closest('[data-driver-call-root], [data-guest-call-root]');
        if (!root) {
            return;
        }

        var bookingKey = root.getAttribute('data-booking-key') || '';
        var attempts = readAttempts(root, bookingKey);
        var logUrl = root.getAttribute('data-call-log-url') || '';

        if (attempts >= APP_CALL_LIMIT) {
            writeAttempts(root, bookingKey, attempts + 1);
            window.setTimeout(function () {
                syncReveal(root);
            }, 0);
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (!logUrl) {
            showError('Thiếu cấu hình lưu lịch sử gọi.');
            return;
        }
        if (isGuestRoot(root) && !root.getAttribute('data-booking-reference')) {
            showError('Chưa có mã chuyến để lưu lịch sử gọi.');
            return;
        }

        pickAppCallOutcome(root).then(function (outcome) {
            if (!outcome) {
                return null;
            }
            return postCallLog(root, logUrl, outcome).then(function (data) {
                writeAttempts(root, bookingKey, attempts + 1);
                syncReveal(root);
                injectChatMessage(root, data && data.message);
            });
        }).catch(function (err) {
            showError(err.message || 'Không lưu được lịch sử gọi.');
        });
    });

    window.CallReveal = { sync: syncReveal, syncAll: syncAll };
})();
