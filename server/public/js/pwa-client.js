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

    function isIosSafari() {
        var ua = window.navigator.userAgent || '';
        if (!isIos()) {
            return false;
        }
        if (/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua)) {
            return false;
        }
        if (/FBAN|FBAV|Instagram|Line\/|Zalo|MicroMessenger|GSA/i.test(ua)) {
            return false;
        }

        return /Version\/[\d.]+/.test(ua) && /Safari/i.test(ua);
    }

    function iosInstallBlockReason() {
        var ua = window.navigator.userAgent || '';
        if (!isIos()) {
            return '';
        }
        if (/FBAN|FBAV|Instagram|Line\/|Zalo|MicroMessenger/i.test(ua)) {
            return 'Trình duyệt trong app (Zalo/Facebook/…) không ghim được. Mở link bằng Safari.';
        }
        if (/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua)) {
            return 'Chrome/Firefox trên iPhone không ghim app đúng cách. Hãy mở lại bằng Safari.';
        }

        return '';
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

    function isAndroid() {
        return /android/i.test(window.navigator.userAgent);
    }

    function showManualInstallHelp() {
        var message;

        if (isIos()) {
            if (!isIosSafari()) {
                message = iosInstallBlockReason() || 'Mở trang bằng Safari để ghim app lên màn hình chính.';
            } else {
                message = 'Trên Safari: nhấn Chia sẻ (mũi tên lên, thanh dưới) → cuộn xuống Thêm vào Màn hình chính → Thêm.';
            }
        } else if (isAndroid()) {
            message = 'Nhấn menu ⋮ trên trình duyệt → chọn Cài ứng dụng hoặc Thêm vào Màn hình chính.';
        } else {
            message = 'Nhấn biểu tượng cài đặt (⊕ hoặc máy tính) trên thanh địa chỉ Chrome/Edge → chọn Cài đặt ứng dụng.';
        }

        if (window.AppFlash && window.AppFlash.show) {
            window.AppFlash.show(message, {
                variant: 'info',
                title: 'Ghim ' + (cfg.audienceLabel || 'ứng dụng'),
            });
        } else if (window.AppDialog && window.AppDialog.alert) {
            window.AppDialog.alert(message, {
                variant: 'info',
                title: 'Ghim ' + (cfg.audienceLabel || 'ứng dụng'),
            });
        }
    }

    function runNativeInstall(installBtn) {
        if (!deferredPrompt) {
            return Promise.resolve(false);
        }

        var bannerBtn = installBtn && installBtn.matches('[data-pwa-install]') ? installBtn : null;
        if (bannerBtn) {
            bannerBtn.disabled = true;
            bannerBtn.textContent = 'Đang mở...';
        }

        var promptEvent = deferredPrompt;
        deferredPrompt = null;

        try {
            var promptResult = promptEvent.prompt();
            var choicePromise = promptEvent.userChoice;

            return Promise.resolve(promptResult).then(function () {
                return choicePromise;
            }).then(function (choice) {
            hideInstallBanner();
            dismiss(storageInstallKey);

            if (choice && choice.outcome === 'accepted') {
                subscribePush();
            }

            syncInstallTriggers();
            return true;
            }).catch(function () {
                return false;
            }).finally(function () {
                if (bannerBtn) {
                    bannerBtn.disabled = false;
                    bannerBtn.textContent = 'Cài đặt';
                }
            });
        } catch (err) {
            if (bannerBtn) {
                bannerBtn.disabled = false;
                bannerBtn.textContent = 'Cài đặt';
            }

            return Promise.resolve(false);
        }
    }

    function showInstallBanner() {
        if (isStandalone() || isDismissed(storageInstallKey) || isIos()) {
            return;
        }

        if (!deferredPrompt && !isIos()) {
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
        if (!hint) {
            return;
        }

        var warning = hint.querySelector('[data-pwa-ios-warning]');
        var blockReason = iosInstallBlockReason();
        if (warning) {
            if (blockReason) {
                warning.textContent = blockReason;
                warning.hidden = false;
            } else {
                warning.textContent = '';
                warning.hidden = true;
            }
        }

        hint.classList.add('is-visible');
    }

    function syncInstallTriggers() {
        var installed = isStandalone();
        document.querySelectorAll('[data-pwa-install-trigger]').forEach(function (btn) {
            btn.classList.toggle('is-installed', installed);
            btn.setAttribute('aria-disabled', installed ? 'true' : 'false');
            var meta = btn.querySelector('[data-pwa-install-meta]');
            if (meta) {
                meta.textContent = installed
                    ? 'Đã ghim'
                    : '';
                meta.hidden = !installed;
            }
            var label = btn.querySelector('[data-pwa-install-label], .driver-drawer__link-text');
            if (label) {
                label.textContent = installed ? 'Đã ghim' : 'Ghim';
            }
            var caption = installed ? 'Đã ghim' : 'Ghim';
            btn.setAttribute('aria-label', caption);
            btn.setAttribute('title', caption);
            btn.classList.toggle('is-on', installed);
        });
    }

    function promptInstall(installBtn) {
        if (isStandalone()) {
            if (window.AppFlash && window.AppFlash.show) {
                window.AppFlash.show('App đã được ghim trên màn hình chính.', {
                    variant: 'success',
                    title: 'Đã ghim',
                });
            }
            syncInstallTriggers();
            return Promise.resolve(true);
        }

        if (deferredPrompt) {
            return runNativeInstall(installBtn).then(function (ok) {
                if (!ok) {
                    showManualInstallHelp();
                }
                syncInstallTriggers();
                return ok;
            });
        }

        hideInstallBanner();

        if (isIos()) {
            showIosHint();
            if (!isIosSafari()) {
                showManualInstallHelp();
            } else if (window.AppFlash && window.AppFlash.show) {
                window.AppFlash.show(
                    'Trên Safari: Chia sẻ → Thêm vào Màn hình chính. App mở thẳng màn sẵn sàng nhận cuốc.',
                    { variant: 'info', title: 'Ghim app Tài xế' }
                );
            } else {
                showManualInstallHelp();
            }
            return Promise.resolve(false);
        }

        showManualInstallHelp();
        return Promise.resolve(false);
    }

    function bindInstallUi() {
        var banner = document.getElementById('pwa-install-banner');
        var installBtn = banner ? banner.querySelector('[data-pwa-install]') : null;
        var dismissBtn = banner ? banner.querySelector('[data-pwa-dismiss]') : null;

        if (installBtn) {
            if (isIos()) {
                installBtn.textContent = 'Hướng dẫn';
            }

            installBtn.addEventListener('click', function () {
                promptInstall(installBtn);
            });
        }

        document.querySelectorAll('[data-pwa-install-trigger]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                promptInstall(trigger);
            });
        });

        syncInstallTriggers();

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

        document.querySelectorAll('[data-pwa-enable-push]').forEach(function (btn) {
            if (btn.getAttribute('data-pwa-push-bound') === '1') {
                return;
            }
            btn.setAttribute('data-pwa-push-bound', '1');
            btn.addEventListener('click', function () {
                var enabled = btn.getAttribute('data-pwa-push-enabled') === '1';
                btn.disabled = true;
                var action = enabled ? unsubscribePush() : subscribePush();
                action.then(function (ok) {
                    btn.disabled = false;
                    syncPushUi();
                    if (enabled) {
                        if (ok && window.AppFlash && window.AppFlash.show) {
                            window.AppFlash.show('Đã tắt thông báo đẩy.', {
                                variant: 'info',
                                title: 'Thông báo',
                            });
                        }
                        return;
                    }
                    if (ok) {
                        if (window.AppFlash && window.AppFlash.show) {
                            window.AppFlash.show('Đã bật thông báo đẩy.', {
                                variant: 'success',
                                title: 'Thông báo',
                            });
                        } else if (window.AppDialog && window.AppDialog.alert) {
                            window.AppDialog.alert('Đã bật thông báo đẩy.', {
                                variant: 'success',
                                title: 'Thông báo',
                            });
                        }
                    } else if (window.Notification && Notification.permission === 'denied') {
                        if (window.AppFlash && window.AppFlash.show) {
                            window.AppFlash.show('Chrome đang chặn thông báo. Mở ổ khóa URL → Thông báo → Cho phép.', {
                                variant: 'warning',
                                title: 'Thông báo bị chặn',
                            });
                        }
                    }
                });
            });
        });

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

    /** Tài xế: đăng ký lại nếu đã cho phép; hỏi 1 lần nếu chưa — để nghe cuốc khi Chrome đang nền. */
    function ensureDriverPush() {
        if (audience !== 'driver' || !window.Notification || !window.PushManager) {
            return Promise.resolve(false);
        }

        if (Notification.permission === 'granted') {
            return subscribePush();
        }

        if (Notification.permission === 'denied' || isDismissed(storagePushKey)) {
            return Promise.resolve(false);
        }

        dismiss(storagePushKey);

        var ask = function () {
            if (window.AppDialog && window.AppDialog.confirm) {
                return window.AppDialog.confirm({
                    title: 'Bật thông báo cuốc mới',
                    message: 'Để nghe chuông khi đang dùng app khác hoặc Chrome đang nền, hãy bật thông báo đẩy.',
                    confirmText: 'Bật thông báo',
                    cancelText: 'Để sau',
                });
            }
            return Promise.resolve(window.confirm(
                'Bật thông báo cuốc mới để nghe chuông khi Chrome đang nền?'
            ));
        };

        return ask().then(function (ok) {
            if (!ok) {
                return false;
            }
            return subscribePush();
        });
    }

    function unsubscribePush() {
        if (!swRegistration || !window.PushManager) {
            return Promise.resolve(false);
        }

        return swRegistration.pushManager.getSubscription().then(function (subscription) {
            if (!subscription) {
                return true;
            }

            var endpoint = subscription.endpoint || '';
            return subscription.unsubscribe().then(function () {
                if (!endpoint) {
                    return true;
                }
                return fetch('/pwa/push/unsubscribe', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ endpoint: endpoint }),
                }).then(function (res) {
                    return !!(res && res.ok);
                }).catch(function () {
                    return true;
                });
            });
        }).catch(function () {
            return false;
        });
    }

    function currentPushSubscription() {
        if (!swRegistration || !window.PushManager) {
            return Promise.resolve(null);
        }
        return swRegistration.pushManager.getSubscription().catch(function () {
            return null;
        });
    }

    function syncPushUi() {
        currentPushSubscription().then(function (subscription) {
            var granted = window.Notification && Notification.permission === 'granted';
            var enabled = !!(subscription && granted);

            document.querySelectorAll('[data-pwa-push-status]').forEach(function (el) {
                el.textContent = enabled
                    ? 'Đã bật'
                    : (window.Notification && Notification.permission === 'denied'
                        ? 'Thông báo bị chặn'
                        : 'Chưa bật');
            });

            document.querySelectorAll('[data-pwa-enable-push]').forEach(function (btn) {
                btn.classList.remove('d-none');
                btn.disabled = false;
                btn.setAttribute('data-pwa-push-enabled', enabled ? '1' : '0');
                btn.classList.toggle('is-on', enabled);
                var onLabel = btn.getAttribute('data-pwa-push-label-on') || 'Tắt thông báo';
                var offLabel = btn.getAttribute('data-pwa-push-label-off') || 'Bật thông báo';
                var caption = enabled ? onLabel : offLabel;
                var label = btn.querySelector('[data-pwa-push-label]');
                if (label) {
                    label.textContent = enabled ? 'Tắt' : 'Bật';
                } else if (!btn.querySelector('svg')) {
                    btn.textContent = enabled ? 'Tắt' : 'Bật';
                }
                btn.setAttribute('aria-label', caption);
                btn.setAttribute('title', caption);
            });
        });
    }

    function onPushMessage(event) {
        var msg = event.data || {};
        if (msg.type !== 'push-event') {
            return;
        }
        var payload = msg.payload || {};
        var data = payload.data && typeof payload.data === 'object' ? payload.data : {};
        var clientEvent = data.client_event || payload.client_event || '';
        var eventKey = data.event_key || payload.event_key || '';
        var isTrip = clientEvent === 'driver_trip_request_created'
            || eventKey === 'driver.new_trip_request';

        if (isTrip && window.DriverSounds && window.DriverSounds.playTrip) {
            window.DriverSounds.playTrip();
        } else if (window.AppSounds && window.AppSounds.play) {
            window.AppSounds.play();
        }

        // Cuốc mới: ưu tiên mở màn chấp nhận / từ chối (tab chuyến).
        if (isTrip && window.DriverTabs && window.DriverTabs.switchTab) {
            window.DriverTabs.switchTab('trips', { force: true });
            if (window.DriverBottomPanel && window.DriverBottomPanel.syncOfferPending) {
                window.DriverBottomPanel.syncOfferPending();
            }
        }

        // Banner ngắn trên header khi app đang mở (kèm ảnh nếu có).
        if (!isTrip && window.AppFlash && window.AppFlash.show) {
            var title = payload.title || data.title || '';
            var body = payload.body || data.body || '';
            var imageUrl = data.image_url || payload.image || data.image || '';
            if (title || body) {
                window.AppFlash.show(body || title, {
                    title: title || undefined,
                    variant: 'info',
                    imageUrl: imageUrl || undefined,
                    autoDismiss: 6000,
                    scroll: false,
                });
            }
        }
    }

    window.PwaClient = {
        subscribePush: subscribePush,
        unsubscribePush: unsubscribePush,
        ensureDriverPush: ensureDriverPush,
        syncPushUi: syncPushUi,
        isStandalone: isStandalone,
        promptInstall: promptInstall,
        syncInstallTriggers: syncInstallTriggers,
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
        syncInstallTriggers();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        hideInstallBanner();
        syncInstallTriggers();
    });

    window.addEventListener('pwa-install-available', function () {
        if (!deferredPrompt && window.__pwaDeferredInstallPrompt) {
            deferredPrompt = window.__pwaDeferredInstallPrompt;
            showInstallBanner();
            syncInstallTriggers();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (!deferredPrompt && window.__pwaDeferredInstallPrompt) {
            deferredPrompt = window.__pwaDeferredInstallPrompt;
        }

        bindInstallUi();
        syncPushUi();

        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', onPushMessage);
        }

        registerServiceWorker().then(function () {
            if (audience === 'driver') {
                window.setTimeout(function () {
                    ensureDriverPush().finally(syncPushUi);
                }, 1200);
            }

            if (isStandalone()) {
                return;
            }

            if (deferredPrompt) {
                showInstallBanner();
                return;
            }

            if (isIos()) {
                window.setTimeout(showIosHint, 1500);
            }
        });
    });
})();
