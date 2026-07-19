/**
 * Hộp thư khách — chỉ đánh dấu đã đọc khi bấm từng dòng.
 * Đồng bộ badge tab / dock / icon app.
 */
(function () {
    var panel = document.querySelector('[data-customer-inbox-panel]');
    if (!panel) {
        return;
    }

    var markUrl = panel.getAttribute('data-inbox-mark-url') || '';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? csrf.getAttribute('content') : '';

    function showPane(category) {
        panel.querySelectorAll('.customer-inbox-tabs__btn').forEach(function (btn) {
            var on = btn.getAttribute('data-inbox-tab') === category;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panel.querySelectorAll('[data-inbox-pane]').forEach(function (pane) {
            var on = pane.getAttribute('data-inbox-pane') === category;
            pane.classList.toggle('is-active', on);
            pane.hidden = !on;
        });
    }

    function updateBadges(unread) {
        if (!unread) {
            return;
        }

        if (window.AppInboxBadge && window.AppInboxBadge.apply) {
            window.AppInboxBadge.apply(unread);
        }

        ['notice', 'info'].forEach(function (cat) {
            var btn = panel.querySelector('.customer-inbox-tabs__btn[data-inbox-tab="' + cat + '"]');
            if (!btn) {
                return;
            }
            var count = Number(unread[cat]) || 0;
            var tabBadge = btn.querySelector('.customer-inbox-tabs__badge');
            if (count < 1) {
                if (tabBadge) {
                    tabBadge.remove();
                }
                return;
            }
            if (!tabBadge) {
                tabBadge = document.createElement('span');
                tabBadge.className = 'customer-inbox-tabs__badge';
                btn.appendChild(tabBadge);
            }
            tabBadge.textContent = String(count);
        });
    }

    function markMessageRead(messageId, itemEl) {
        if (!markUrl || !messageId) {
            return;
        }
        fetch(markUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message_id: Number(messageId) }),
            credentials: 'same-origin',
        })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (itemEl) {
                    itemEl.classList.remove('is-unread');
                }
                if (data && data.unread) {
                    updateBadges(data.unread);
                }
            })
            .catch(function () { /* ignore */ });
    }

    panel.querySelectorAll('.customer-inbox-tabs__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showPane(btn.getAttribute('data-inbox-tab') || 'notice');
        });
    });

    panel.querySelectorAll('[data-inbox-message-id]').forEach(function (item) {
        function openItem() {
            var id = item.getAttribute('data-inbox-message-id');
            if (!id) {
                return;
            }
            if (item.classList.contains('is-unread')) {
                markMessageRead(id, item);
            }
        }
        item.addEventListener('click', openItem);
        item.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openItem();
            }
        });
    });

    window.CustomerInbox = {
        updateBadges: updateBadges,
        markMessageRead: markMessageRead,
    };
})();
