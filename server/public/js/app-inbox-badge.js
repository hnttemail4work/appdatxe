/**
 * Đồng bộ số chưa đọc hộp thư:
 * - Badge dock trong app (khách / tài xế)
 * - Badge icon app ngoài màn hình (Badging API + service worker)
 */
(function () {
    'use strict';

    function formatCount(n) {
        n = Math.max(0, Number(n) || 0);
        if (n < 1) {
            return '';
        }
        return n > 99 ? '99+' : String(n);
    }

    function syncAppIconBadge(total) {
        var count = Math.max(0, Number(total) || 0);
        try {
            if (navigator.setAppBadge) {
                if (count < 1 && navigator.clearAppBadge) {
                    navigator.clearAppBadge().catch(function () {});
                } else if (count > 0) {
                    navigator.setAppBadge(count).catch(function () {});
                }
            }
        } catch (e) { /* ignore */ }

        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: count < 1 ? 'clear-app-badge' : 'set-app-badge',
                count: count,
            });
        }
    }

    function setCustomerDockBadge(total) {
        var link = document.querySelector(
            '.customer-scroll-dock a[href*="hop-thu"], .customer-scroll-dock a[href*="inbox"], .customer-scroll-dock-item[title="Hộp thư"]'
        );
        if (!link) {
            // Fallback: item có badge hoặc label Hộp thư
            document.querySelectorAll('.customer-scroll-dock-item').forEach(function (item) {
                var label = item.querySelector('.customer-scroll-dock-label');
                if (label && label.textContent.trim() === 'Hộp thư') {
                    link = item;
                }
            });
        }
        if (!link) {
            return;
        }
        link.setAttribute('data-inbox-unread', String(Math.max(0, Number(total) || 0)));
        var badge = link.querySelector('.customer-scroll-dock-badge');
        var text = formatCount(total);
        if (!text) {
            if (badge) {
                badge.remove();
            }
            return;
        }
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'customer-scroll-dock-badge';
            link.appendChild(badge);
        }
        badge.textContent = text;
    }

    function setDriverDockBadge(total) {
        var dockBtn = document.querySelector('.driver-dock-item[data-driver-tab="inbox"]');
        if (!dockBtn) {
            return;
        }
        var badge = dockBtn.querySelector('.driver-dock-badge');
        var text = formatCount(total);
        if (!text) {
            if (badge) {
                badge.remove();
            }
            return;
        }
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'driver-dock-badge is-hot';
            dockBtn.appendChild(badge);
        }
        badge.textContent = text;
        badge.classList.add('is-hot');
    }

    /**
     * @param {number|object} unreadOrTotal
     */
    function apply(unreadOrTotal) {
        var total = 0;
        if (unreadOrTotal && typeof unreadOrTotal === 'object') {
            total = Number(unreadOrTotal.total) || 0;
        } else {
            total = Number(unreadOrTotal) || 0;
        }
        syncAppIconBadge(total);
        setCustomerDockBadge(total);
        setDriverDockBadge(total);
    }

    function bootFromDom() {
        var dock = document.querySelector('.customer-scroll-dock[data-inbox-unread]');
        if (dock) {
            apply(Number(dock.getAttribute('data-inbox-unread')) || 0);
            return;
        }
        var inboxLink = document.querySelector('.customer-scroll-dock-item[data-inbox-unread]');
        if (inboxLink) {
            apply(Number(inboxLink.getAttribute('data-inbox-unread')) || 0);
            return;
        }
        var driverPage = document.querySelector('[data-driver-inbox-unread]');
        if (driverPage) {
            apply(Number(driverPage.getAttribute('data-driver-inbox-unread')) || 0);
        }
    }

    window.AppInboxBadge = {
        apply: apply,
        syncAppIconBadge: syncAppIconBadge,
        setCustomerDockBadge: setCustomerDockBadge,
        setDriverDockBadge: setDriverDockBadge,
        bootFromDom: bootFromDom,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootFromDom);
    } else {
        bootFromDom();
    }
})();
