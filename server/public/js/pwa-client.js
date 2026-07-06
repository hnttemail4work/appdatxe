(function () {
    var cfg = window.__pwaConfig || {};
    var audience = cfg.audience || 'guest';
    var storageInstallKey = 'pwa_install_dismissed_' + audience;
    var storagePushKey = 'pwa_push_prompted_' + audience;
    var deferredPrompt = null;
    var swRegistration = null;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function browserId() {
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.getBrowserSessionId) {
            return window.BookingBrowserGuard.getBrowserSessionId();
        }
        return '';
    }

    function contactPhone() {
        var input = document.querySelector('#contact-phone, [name="contact_phone"]');
        return input && input.value ? String(input.value).trim() : '';
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function isIos() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    }

    function isDismissed(key) {
        try {
            return sessionStorage.getItem(key) === '1';
        } catch (e) {
            return false;
        }
    }

    function dismiss(key) {
        try {
            sessionStorage.setItem(key, '1');
        } catch (e) {
        }
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return Promise.resolve(null);
        }

        return navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then(function (registration) {
                swRegistration = registration;
                return registration;
            })
            .catch(function () {
                return null;
            });
    }

    function fetchVapid() {
        return fetch('/pwa/push/vapid-public-key', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function subscribePush() {
        if (!swRegistration || !window.PushManager) {
            return Promise.resolve(false);
        }

        return fetchVapid().then(function (data) {
            if (!data.enabled || !data.public_key) {
                return false;
            }

            return Notification.requestPermission().then(function (permission) {
                if (permission !== 'granted') {
                    return false;
                }

                return swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(data.public_key),
                }).then(function (subscription) {
                    var json = subscription.toJSON();
                    return fetch('/pwa/push/subscribe', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Booking-Browser-Id': browserId(),
                        },
                        body: JSON.stringify({
                            endpoint: json.endpoint,
                            keys: json.keys,
                            content_encoding: 'aesgcm',
                            browser_id: browserId(),
                            contact_phone: contactPhone(),
                        }),
                    });
                }).then(function (res) {
                    return res && res.ok;
                });
            });
        }).catch(function () {
            return false;
        });
    }

    function showInstallBanner() {
        if (isStandalone() || isDismissed(storageInstallKey)) {
            return;
        }

        var banner = document.getElementById('pwa-install-banner');
        if (!banner) {
            return;
        }

        var title = banner.querySelector('[data-pwa-install-title]');
        if (title && cfg.installTitle) {
            title.textContent = cfg.installTitle;
        }

        banner.classList.add('is-visible');
    }

    function hideInstallBanner() {
        var banner = document.getElementById('pwa-install-banner');
        if (banner) {
            banner.classList.remove('is-visible');
        }
    }

    function showIosHint() {
        if (!isIos() || isStandalone() || isDismissed(storageInstallKey)) {
            return;
        }

        var hint = document.getElementById('pwa-ios-hint');
        if (hint) {
            hint.classList.add('is-visible');
        }
    }

    function bindInstallUi() {
        var banner = document.getElementById('pwa-install-banner');
        if (!banner) {
            return;
        }

        var installBtn = banner.querySelector('[data-pwa-install]');
        var dismissBtn = banner.querySelector('[data-pwa-dismiss]');
        var pushBtn = banner.querySelector('[data-pwa-enable-push]');

        if (installBtn) {
            installBtn.addEventListener('click', function () {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function () {
                        deferredPrompt = null;
                        hideInstallBanner();
                        dismiss(storageInstallKey);
                        subscribePush();
                    });
                    return;
                }

                if (isIos()) {
                    hideInstallBanner();
                    showIosHint();
                }
            });
        }

        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                dismiss(storageInstallKey);
                hideInstallBanner();
                var hint = document.getElementById('pwa-ios-hint');
                if (hint) {
                    hint.classList.remove('is-visible');
                }
            });
        }

        if (pushBtn) {
            pushBtn.addEventListener('click', function () {
                subscribePush().then(function (ok) {
                    if (ok && window.AppDialog) {
                        window.AppDialog.alert('Đã bật thông báo cho ' + (cfg.audienceLabel || 'ứng dụng') + '.', {
                            variant: 'success',
                            title: 'Thông báo',
                        });
                    }
                });
            });
        }

        var iosClose = document.querySelector('[data-pwa-ios-dismiss]');
        if (iosClose) {
            iosClose.addEventListener('click', function () {
                dismiss(storageInstallKey);
                var hint = document.getElementById('pwa-ios-hint');
                if (hint) {
                    hint.classList.remove('is-visible');
                }
            });
        }
    }

    function maybePromptPushAfterBooking() {
        if (isDismissed(storagePushKey) || Notification.permission === 'granted') {
            return;
        }

        if (audience !== 'guest') {
            return;
        }

        dismiss(storagePushKey);

        if (window.AppDialog) {
            window.AppDialog.confirm({
                title: 'Bật thông báo chuyến đi',
                message: 'Nhận thông báo khi tài xế nhận chuyến, đang tới và hoàn tất.',
                confirmText: 'Bật',
                cancelText: 'Để sau',
            }).then(function (ok) {
                if (ok) {
                    subscribePush();
                }
            });
        }
    }

    window.PwaClient = {
        subscribePush: subscribePush,
        touchContactPhone: function (phone) {
            if (!phone) {
                return Promise.resolve();
            }
            return fetch('/pwa/push/touch-contact', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    browser_id: browserId(),
                    contact_phone: phone,
                }),
            });
        },
        afterBookingSuccess: maybePromptPushAfterBooking,
    };

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        showInstallBanner();
    });

    document.addEventListener('DOMContentLoaded', function () {
        bindInstallUi();
        registerServiceWorker().then(function () {
            if (isIos() && !isStandalone()) {
                window.setTimeout(showIosHint, 1500);
            } else if (!deferredPrompt && !isStandalone()) {
                window.setTimeout(showInstallBanner, 2500);
            }
        });
    });
})();
