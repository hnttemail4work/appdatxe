/**
 * Hộp thư khách — Tin tức / Thông báo + trò chuyện với tài xế.
 */
(function () {
    var panel = document.querySelector('[data-customer-inbox-panel]');
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
                Accept: 'application/json',
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

    function showSystemView() {
        view = 'system';
        setHidden(headChat, true);
        setHidden(systemWrap, false);
        setHidden(chatsWrap, true);
        setHidden(threadWrap, true);
        setHidden(openChatsBtn, false);
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
        var ref = source.getAttribute('data-booking-reference') || '';
        var listUrl = source.getAttribute('data-chat-list-url') || '';
        var sendUrl = source.getAttribute('data-chat-send-url') || '';
        var peer = source.getAttribute('data-chat-peer') || 'Tài xế';
        var open = source.getAttribute('data-chat-open') === '1';
        if (!listUrl || !sendUrl || !ref) {
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
        chatPanel.dataset.chatMode = 'customer';

        source.classList.remove('is-unread');
        var itemBadge = source.querySelector('.customer-inbox-chat-item__badge');
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

    panel.querySelectorAll('.customer-inbox-tabs__btn').forEach(function (btn) {
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

    window.CustomerInbox = {
        updateBadges: updateBadges,
        markMessageRead: markMessageRead,
        showPane: showPane,
        showChatsView: showChatsView,
    };
})();
