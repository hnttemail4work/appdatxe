/**
 * Hộp thư tài xế — Thông báo / Thông tin + tin nhắn khách.
 */
(function () {
    var panel = document.querySelector('[data-driver-inbox-panel]');
    if (!panel) {
        return;
    }

    var markUrl = panel.getAttribute('data-inbox-mark-url') || '';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? csrf.getAttribute('content') : '';
    var view = 'system';
    var headMain = panel.querySelector('[data-inbox-head-main]');
    var headChat = panel.querySelector('[data-inbox-head-chat]');
    var systemWrap = panel.querySelector('[data-inbox-system]');
    var chatsWrap = panel.querySelector('[data-inbox-chats]');
    var threadWrap = panel.querySelector('[data-inbox-thread]');
    var chatTitle = panel.querySelector('[data-inbox-chat-title]');
    var chatBadge = panel.querySelector('[data-inbox-chat-badge]');

    function activeCategory() {
        var btn = panel.querySelector('.driver-inbox-tabs__btn.is-active');
        return (btn && btn.getAttribute('data-inbox-tab')) || 'notice';
    }

    function setHidden(el, hidden) {
        if (!el) return;
        el.hidden = hidden;
        el.classList.toggle('d-none', hidden);
    }

    function showPane(category) {
        panel.querySelectorAll('.driver-inbox-tabs__btn').forEach(function (btn) {
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
        var chat = Number(unread.chat) || 0;
        var bell = document.querySelector('[data-inbox-bell-badge]');
        if (bell) {
            if (total > 0) {
                bell.hidden = false;
                bell.textContent = total > 99 ? '99+' : String(total);
                var dot = document.querySelector('.driver-map-chrome__bell-dot');
                if (dot) {
                    dot.remove();
                }
            } else {
                bell.hidden = true;
            }
        }

        var dockBtn = document.querySelector('.driver-dock-item[data-driver-tab="inbox"]');
        if (dockBtn) {
            var badge = dockBtn.querySelector('.driver-dock-badge');
            if (total < 1) {
                if (badge) {
                    badge.remove();
                }
            } else {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'driver-dock-badge is-hot';
                    dockBtn.appendChild(badge);
                }
                badge.textContent = String(total);
                badge.classList.add('is-hot');
            }
        }

        if (chatBadge) {
            if (chat < 1) {
                chatBadge.classList.add('d-none');
                chatBadge.textContent = '0';
            } else {
                chatBadge.classList.remove('d-none');
                chatBadge.textContent = chat > 99 ? '99+' : String(chat);
            }
        }

        ['notice', 'info'].forEach(function (cat) {
            var btn = panel.querySelector('.driver-inbox-tabs__btn[data-inbox-tab="' + cat + '"]');
            if (!btn) {
                return;
            }
            var count = Number(unread[cat]) || 0;
            var tabBadge = btn.querySelector('.driver-inbox-tabs__badge');
            if (count < 1) {
                if (tabBadge) {
                    tabBadge.remove();
                }
                return;
            }
            if (!tabBadge) {
                tabBadge = document.createElement('span');
                tabBadge.className = 'driver-inbox-tabs__badge';
                btn.appendChild(tabBadge);
            }
            tabBadge.textContent = String(count);
        });
    }

    function markRead(category) {
        if (!markUrl) {
            return;
        }
        fetch(markUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ category: category || 'all' }),
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.unread) {
                    return;
                }
                updateBadges(data.unread);
                if (category && category !== 'all') {
                    panel.querySelectorAll('[data-inbox-pane="' + category + '"] .is-unread').forEach(function (el) {
                        el.classList.remove('is-unread');
                    });
                } else {
                    panel.querySelectorAll('[data-inbox-system] .is-unread').forEach(function (el) {
                        el.classList.remove('is-unread');
                    });
                }
            })
            .catch(function () {});
    }

    function showSystemView() {
        view = 'system';
        setHidden(headMain, false);
        setHidden(headChat, true);
        setHidden(systemWrap, false);
        setHidden(chatsWrap, true);
        setHidden(threadWrap, true);
        panel.querySelectorAll('[data-inbox-system-notice]').forEach(function (el) {
            setHidden(el, false);
        });
    }

    function showChatsView() {
        view = 'chats';
        setHidden(headMain, true);
        setHidden(headChat, false);
        setHidden(systemWrap, true);
        setHidden(chatsWrap, false);
        setHidden(threadWrap, true);
        if (chatTitle) {
            chatTitle.textContent = 'Tin nhắn';
        }
        panel.querySelectorAll('[data-inbox-system-notice]').forEach(function (el) {
            setHidden(el, true);
        });
    }

    function clearThreadMessages(chatPanel) {
        if (!chatPanel) return;
        var list = chatPanel.querySelector('[data-chat-messages]');
        if (list) list.innerHTML = '';
        chatPanel.dataset.lastMessageId = '0';
        var empty = chatPanel.querySelector('[data-chat-empty]');
        if (empty) empty.classList.remove('d-none');
        var err = chatPanel.querySelector('[data-chat-error]');
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
    }

    function openThread(source) {
        if (!source) return;
        var ref = source.getAttribute('data-booking-reference')
            || source.getAttribute('data-driver-open-chat')
            || '';
        var listUrl = source.getAttribute('data-chat-list-url') || '';
        var sendUrl = source.getAttribute('data-chat-send-url') || '';
        var peer = source.getAttribute('data-chat-peer') || 'Hành khách';
        var open = source.getAttribute('data-chat-open') === '1';
        if (!listUrl || !sendUrl) {
            return;
        }

        view = 'thread';
        setHidden(headMain, true);
        setHidden(headChat, false);
        setHidden(systemWrap, true);
        setHidden(chatsWrap, true);
        setHidden(threadWrap, false);
        if (chatTitle) {
            chatTitle.textContent = peer;
        }
        panel.querySelectorAll('[data-inbox-system-notice]').forEach(function (el) {
            setHidden(el, true);
        });

        var chatPanel = threadWrap && threadWrap.querySelector('[data-trip-chat]');
        if (!chatPanel) {
            return;
        }

        clearThreadMessages(chatPanel);
        chatPanel.classList.remove('d-none');
        chatPanel.dataset.bookingReference = ref;
        chatPanel.dataset.chatListUrl = listUrl;
        chatPanel.dataset.chatSendUrl = sendUrl;
        chatPanel.dataset.chatOpen = open ? '1' : '0';
        chatPanel.dataset.chatMode = 'driver';

        source.classList.remove('is-unread');
        var itemBadge = source.querySelector('.driver-inbox-chat-item__badge');
        if (itemBadge) {
            itemBadge.remove();
        }

        if (window.TripChat && window.TripChat.openDriverPanel) {
            window.TripChat.openDriverPanel(chatPanel);
        }
    }

    function chatBack() {
        if (view === 'thread') {
            showChatsView();
            return;
        }
        showSystemView();
    }

    panel.querySelectorAll('[data-inbox-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var category = btn.getAttribute('data-inbox-tab') || 'notice';
            showPane(category);
            markRead(category);
        });
    });

    var openChatsBtn = panel.querySelector('[data-inbox-open-chats]');
    if (openChatsBtn) {
        openChatsBtn.addEventListener('click', function () {
            showChatsView();
        });
    }

    var backBtn = panel.querySelector('[data-inbox-chat-back]');
    if (backBtn) {
        backBtn.addEventListener('click', chatBack);
    }

    panel.querySelectorAll('[data-inbox-chat-item]').forEach(function (item) {
        item.addEventListener('click', function () {
            openThread(item);
        });
    });

    document.addEventListener('drivertab:changed', function (event) {
        var tab = (event.detail && event.detail.tab) || '';
        if (tab === 'inbox') {
            if (view === 'system') {
                markRead(activeCategory());
            }
        } else if (view !== 'system') {
            showSystemView();
        }
    });

    window.DriverInbox = {
        updateBadges: updateBadges,
        markRead: markRead,
        showPane: showPane,
        showChatsView: showChatsView,
        openChat: function (source) {
            if (window.DriverTabs && window.DriverTabs.switchTab) {
                window.DriverTabs.switchTab('inbox');
            }
            if (typeof source === 'string') {
                var item = panel.querySelector('[data-inbox-chat-item][data-booking-reference="' + source + '"]');
                if (item) {
                    openThread(item);
                    return;
                }
                showChatsView();
                return;
            }
            openThread(source);
        },
    };

    var page = document.querySelector('.driver-page[data-driver-tabs]');
    if (page && page.dataset.driverTabsActive === 'inbox') {
        markRead(activeCategory());
    }
})();
