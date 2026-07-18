/**
 * Hộp thư khách — Thông báo / Thông tin + đánh dấu đã đọc.
 */
(function () {
    var panel = document.querySelector('[data-customer-inbox-panel]');
    if (!panel) {
        return;
    }

    var markUrl = panel.getAttribute('data-inbox-mark-url') || '';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? csrf.getAttribute('content') : '';

    function activeCategory() {
        var btn = panel.querySelector('.customer-inbox-tabs__btn.is-active');
        return (btn && btn.getAttribute('data-inbox-tab')) || 'notice';
    }

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
        var total = Number(unread.total) || 0;
        var tabLink = document.querySelector('.customer-account-tab[data-customer-tab="inbox"]');
        if (tabLink) {
            var navBadge = tabLink.querySelector('.customer-account-tab__badge');
            if (total < 1) {
                if (navBadge) {
                    navBadge.remove();
                }
            } else {
                if (!navBadge) {
                    navBadge = document.createElement('span');
                    navBadge.className = 'customer-account-tab__badge';
                    tabLink.appendChild(navBadge);
                }
                navBadge.textContent = total > 99 ? '99+' : String(total);
            }
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

        panel.querySelectorAll('.customer-inbox-list__item.is-unread').forEach(function (item) {
            var pane = item.closest('[data-inbox-pane]');
            if (pane && pane.getAttribute('data-inbox-pane') === activeCategory()) {
                item.classList.remove('is-unread');
            }
        });
    }

    function markRead(category) {
        if (!markUrl) {
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
            body: JSON.stringify({ category: category || 'all' }),
            credentials: 'same-origin',
        })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (data && data.unread) {
                    updateBadges(data.unread);
                }
            })
            .catch(function () { /* ignore */ });
    }

    panel.querySelectorAll('.customer-inbox-tabs__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var category = btn.getAttribute('data-inbox-tab') || 'notice';
            showPane(category);
            markRead(category);
        });
    });

    function inboxPanelActive() {
        var section = panel.closest('[data-customer-panel="inbox"]');
        return !section || section.classList.contains('is-active');
    }

    document.querySelectorAll('.customer-account-tab[data-customer-tab="inbox"]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            window.setTimeout(function () {
                if (inboxPanelActive()) {
                    markRead(activeCategory());
                }
            }, 0);
        });
    });

    if (inboxPanelActive()) {
        markRead(activeCategory());
    }
})();
