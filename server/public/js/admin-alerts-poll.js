/**
 * Poll thông báo admin (chờ duyệt hồ sơ / nạp ví / TX nhận cuốc) trên mọi trang console.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-admin-alerts-poll]');
    if (!root) {
        return;
    }

    var url = root.getAttribute('data-admin-alerts-poll') || '';
    if (!url) {
        return;
    }

    var intervalMs = parseInt(root.getAttribute('data-admin-alerts-interval') || '12000', 10);
    if (!intervalMs || intervalMs < 5000) {
        intervalMs = 12000;
    }

    var timer = null;
    var inFlight = false;

    function showAlerts(alerts) {
        if (!alerts || !alerts.length || !window.AppFlash || typeof window.AppFlash.show !== 'function') {
            return;
        }

        alerts.forEach(function (alert, index) {
            if (!alert || !alert.message) {
                return;
            }

            var opts = {
                variant: alert.variant || (alert.type === 'driver_accepted' ? 'success' : 'warning'),
                title: alert.title || 'Thông báo',
                autoDismiss: 14000,
                clear: index === 0,
            };

            window.AppFlash.show(alert.message, opts);

            // Click banner → mở trang duyệt tương ứng.
            if (alert.url) {
                var stack = document.getElementById('app-flash-stack');
                var banner = stack && stack.querySelector('.app-flash-banner:last-child');
                if (banner) {
                    banner.style.cursor = 'pointer';
                    banner.addEventListener('click', function (e) {
                        if (e.target && e.target.closest('[data-flash-close]')) {
                            return;
                        }
                        window.location.href = alert.url;
                    });
                }
            }
        });
    }

    function poll() {
        if (inFlight || document.hidden) {
            return;
        }
        inFlight = true;
        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('poll failed');
                }
                return res.json();
            })
            .then(function (data) {
                showAlerts(data && data.alerts);
            })
            .catch(function () {
                // im lặng — thử lại chu kỳ sau
            })
            .finally(function () {
                inFlight = false;
            });
    }

    function start() {
        poll();
        timer = window.setInterval(poll, intervalMs);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
