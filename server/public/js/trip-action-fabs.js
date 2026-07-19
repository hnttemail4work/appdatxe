/**
 * FAB khẩn cấp (phải trên) + trò chuyện (trái dưới) — chỉ khi đang trong chuyến.
 */
(function () {
    'use strict';

    function setVisible(el, show) {
        if (!el) {
            return;
        }
        el.hidden = !show;
        el.classList.toggle('d-none', !show);
    }

    function setInTrip(show) {
        var on = !!show;
        document.querySelectorAll('[data-trip-sos-fab]').forEach(function (el) {
            setVisible(el, on);
        });
        document.querySelectorAll('[data-trip-chat-fab]').forEach(function (el) {
            setVisible(el, on);
        });
        document.body.classList.toggle('is-trip-fabs-active', on);
    }

    function openCustomerChat() {
        var panel = document.querySelector('[data-trip-chat][data-chat-mode="customer"]:not(.d-none)')
            || document.querySelector('[data-customer-inbox-panel] [data-trip-chat]');
        if (panel && window.TripChat && window.TripChat.openDriverPanel) {
            panel.classList.remove('d-none');
            window.TripChat.openDriverPanel(panel);
            if (window.CustomerInbox && window.CustomerInbox.showChatsView && panel.closest('[data-customer-inbox-panel]')) {
                /* inbox embed path */
            }
            try {
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } catch (e) { /* ignore */ }
            return;
        }
        if (window.CustomerInbox && window.CustomerInbox.showChatsView) {
            window.CustomerInbox.showChatsView();
            return;
        }
        var inboxUrl = document.querySelector('.customer-scroll-dock-item[title="Hộp thư"]');
        if (inboxUrl && inboxUrl.href) {
            window.location.href = inboxUrl.href;
        }
    }

    function openDriverChat() {
        var source = document.querySelector('[data-driver-open-chat]:not(.is-disabled)');
        if (source && window.DriverInbox && window.DriverInbox.openChat) {
            window.DriverInbox.openChat(source);
            return;
        }
        if (window.DriverTabs && window.DriverTabs.switchTab) {
            window.DriverTabs.switchTab('inbox');
        }
        if (window.DriverInbox && window.DriverInbox.showChatsView) {
            window.DriverInbox.showChatsView();
        }
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-trip-chat-fab]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        if (document.querySelector('.driver-page--map')) {
            openDriverChat();
            return;
        }
        openCustomerChat();
    });

    window.TripActionFabs = {
        setInTrip: setInTrip,
    };

    // Đồng bộ class body theo markup server (tài xế đang trong chuyến).
    var bootSos = document.querySelector('[data-trip-sos-fab]');
    if (bootSos && !bootSos.classList.contains('d-none') && !bootSos.hidden) {
        document.body.classList.add('is-trip-fabs-active');
    }
})();
