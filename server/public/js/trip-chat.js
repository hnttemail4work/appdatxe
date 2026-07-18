(function () {
    'use strict';

    var pollTimer = null;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function customerParams(panel) {
        return {
            booking_reference: panel.dataset.bookingReference || '',
        };
    }

    function requestParams(panel) {
        return panel.dataset.chatMode === 'customer' ? customerParams(panel) : {};
    }

    function setError(panel, message) {
        var el = panel.querySelector('[data-chat-error]');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('d-none', !message);
    }

    function appendMessage(panel, message) {
        var list = panel.querySelector('[data-chat-messages]');
        if (!list || list.querySelector('[data-message-id="' + message.id + '"]')) return;

        var mine = message.role === panel.dataset.chatMode;
        var item = document.createElement('div');
        item.className = 'trip-chat-message ' + (mine ? 'is-mine' : 'is-theirs');
        item.dataset.messageId = String(message.id);

        var body = document.createElement('div');
        body.className = 'trip-chat-message__body';
        body.textContent = message.body || '';

        var time = document.createElement('small');
        time.className = 'trip-chat-message__time';
        time.textContent = message.created_at || '';

        item.appendChild(body);
        item.appendChild(time);
        list.appendChild(item);
        list.scrollTop = list.scrollHeight;
        panel.dataset.lastMessageId = String(Math.max(Number(panel.dataset.lastMessageId || 0), Number(message.id || 0)));

        var empty = panel.querySelector('[data-chat-empty]');
        if (empty) empty.classList.add('d-none');
    }

    function applyResponse(panel, data) {
        panel.dataset.chatOpen = data.open ? '1' : '0';
        var status = panel.querySelector('[data-chat-status]');
        if (status && data.status_message) status.textContent = data.status_message;

        (data.messages || []).forEach(function (message) {
            appendMessage(panel, message);
        });

        var input = panel.querySelector('[data-chat-input]');
        var send = panel.querySelector('[data-chat-send]');
        if (input) input.disabled = !data.open;
        if (send) send.disabled = !data.open;
    }

    function loadMessages(panel) {
        var url = panel.dataset.chatListUrl;
        if (!url || panel.classList.contains('d-none') || panel.closest('[hidden]')) {
            return Promise.resolve();
        }

        var params = requestParams(panel);
        var afterId = Number(panel.dataset.lastMessageId || 0);
        if (afterId > 0) params.after_id = afterId;
        var query = new URLSearchParams(params).toString();

        return fetch(url + (query ? '?' + query : ''), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) throw new Error('Không tải được tin nhắn.');
            return response.json();
        }).then(function (data) {
            applyResponse(panel, data);
            setError(panel, '');
            if (data.unread && window.DriverInbox && window.DriverInbox.updateBadges) {
                window.DriverInbox.updateBadges(data.unread);
            }
        }).catch(function (error) {
            setError(panel, error.message || 'Không tải được tin nhắn.');
        });
    }

    function openDriverPanel(panel) {
        if (!panel) return;
        var body = panel.querySelector('[data-chat-body]');
        if (body) body.classList.remove('d-none');
        var toggle = panel.querySelector('[data-chat-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
        loadMessages(panel);
    }

    function sendMessage(panel, body) {
        var url = panel.dataset.chatSendUrl;
        var payload = requestParams(panel);
        payload.body = body;

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload),
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) throw new Error(data.message || 'Không gửi được tin nhắn.');
                return data;
            });
        }).then(function (data) {
            if (data.message) appendMessage(panel, data.message);
            setError(panel, '');
        });
    }

    function startPolling() {
        if (pollTimer) window.clearInterval(pollTimer);
        pollTimer = window.setInterval(function () {
            document.querySelectorAll('[data-trip-chat]:not(.d-none)').forEach(function (panel) {
                if (panel.closest('[hidden]')) return;
                var body = panel.querySelector('[data-chat-body]');
                if (body && !body.classList.contains('d-none')) loadMessages(panel);
            });
        }, 4000);
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-chat-toggle]');
        if (!toggle) return;
        var panel = toggle.closest('[data-trip-chat]');
        var body = panel && panel.querySelector('[data-chat-body]');
        if (!panel || !body) return;

        var opening = body.classList.contains('d-none');
        body.classList.toggle('d-none', !opening);
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) loadMessages(panel);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-chat-form]');
        if (!form) return;
        event.preventDefault();

        var panel = form.closest('[data-trip-chat]');
        var input = form.querySelector('[data-chat-input]');
        var value = input ? input.value.trim() : '';
        if (!panel || !value) return;

        var button = form.querySelector('[data-chat-send]');
        if (button) button.disabled = true;
        sendMessage(panel, value).then(function () {
            input.value = '';
        }).catch(function (error) {
            setError(panel, error.message || 'Không gửi được tin nhắn.');
        }).finally(function () {
            if (button && panel.dataset.chatOpen === '1') button.disabled = false;
            if (input) input.focus();
        });
    });

    window.TripChat = {
        openDriverPanel: openDriverPanel,
        setCustomerBooking: function (booking) {
            var panel = document.querySelector('[data-trip-chat][data-chat-mode="customer"]');
            if (!panel) return;

            var open = !!(booking && booking.chat && booking.chat.open);
            panel.classList.toggle('d-none', !open);
            panel.dataset.bookingReference = booking && booking.booking_reference
                ? String(booking.booking_reference)
                : '';
            panel.dataset.contactPhone = booking && booking.contact_phone
                ? String(booking.contact_phone)
                : '';
            panel.dataset.chatOpen = open ? '1' : '0';

            if (!open) {
                var body = panel.querySelector('[data-chat-body]');
                var toggle = panel.querySelector('[data-chat-toggle]');
                if (body) body.classList.add('d-none');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        },
    };

    startPolling();
})();
