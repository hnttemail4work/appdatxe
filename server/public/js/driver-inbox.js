/**
 * Hộp thư tài xế — Tin tức / Thông báo + tin nhắn khách.
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
    var headChat = panel.querySelector('[data-inbox-head-chat]');
    var systemWrap = panel.querySelector('[data-inbox-system]');
    var chatsWrap = panel.querySelector('[data-inbox-chats]');
    var threadWrap = panel.querySelector('[data-inbox-thread]');
    var chatTitle = panel.querySelector('[data-inbox-chat-title]');
    var chatBadge = panel.querySelector('[data-inbox-chat-badge]');
    var openChatsBtn = panel.querySelector('[data-inbox-open-chats]');

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

        if (window.AppInboxBadge && window.AppInboxBadge.apply) {
            window.AppInboxBadge.apply(unread);
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

    function markMessageRead(messageId, itemEl) {
        if (!markUrl || !messageId) {
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
            body: JSON.stringify({ message_id: Number(messageId) }),
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (itemEl) {
                    itemEl.classList.remove('is-unread');
                }
                if (data && data.unread) {
                    updateBadges(data.unread);
                }
            })
            .catch(function () {});
    }

    function showSystemView() {
        view = 'system';
        setHidden(headChat, true);
        setHidden(systemWrap, false);
        setHidden(chatsWrap, true);
        setHidden(threadWrap, true);
        setHidden(openChatsBtn, false);
        panel.querySelectorAll('[data-inbox-system-notice]').forEach(function (el) {
            setHidden(el, false);
        });
    }

    function showChatsView() {
        view = 'chats';
        setHidden(headChat, false);
        setHidden(systemWrap, true);
        setHidden(chatsWrap, false);
        setHidden(threadWrap, true);
        setHidden(openChatsBtn, true);
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
        setHidden(headChat, false);
        setHidden(systemWrap, true);
        setHidden(chatsWrap, true);
        setHidden(threadWrap, false);
        setHidden(openChatsBtn, true);
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
            showPane(btn.getAttribute('data-inbox-tab') || 'info');
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
            // Không auto-đọc cả tab — chỉ đọc khi bấm từng dòng.
        } else if (view !== 'system') {
            showSystemView();
        }
    });

    window.DriverInbox = {
        updateBadges: updateBadges,
        markMessageRead: markMessageRead,
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
})();
