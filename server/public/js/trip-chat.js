(function () {
    'use strict';

    var pollTimer = null;
    var homeMarkers = new WeakMap();
    var MESSAGE_LIMIT = 10;
    var pendingImages = new WeakMap();
    var previewUrls = new WeakMap();

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

    function isEmbed(panel) {
        return !!(panel && (panel.classList.contains('trip-chat-panel--embed') || panel.getAttribute('data-chat-embed') === '1'));
    }

    function setError(panel, message) {
        var el = panel.querySelector('[data-chat-error]');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('d-none', !message);
    }

    function clearMessageList(panel) {
        var list = panel.querySelector('[data-chat-messages]');
        if (list) {
            list.innerHTML = '';
        }
        panel.dataset.lastMessageId = '0';
        var empty = panel.querySelector('[data-chat-empty]');
        if (empty) {
            empty.classList.remove('d-none');
        }
    }

    function trimMessageList(panel) {
        var list = panel.querySelector('[data-chat-messages]');
        if (!list) return;
        var items = list.querySelectorAll('.trip-chat-message');
        while (items.length > MESSAGE_LIMIT) {
            items[0].remove();
            items = list.querySelectorAll('.trip-chat-message');
        }
    }

    function clearImageDraft(panel) {
        pendingImages.delete(panel);
        var fileInput = panel.querySelector('[data-chat-image]');
        if (fileInput) {
            fileInput.value = '';
        }
        var preview = panel.querySelector('[data-chat-image-preview]');
        var previewImg = panel.querySelector('[data-chat-image-preview-img]');
        var prevUrl = previewUrls.get(panel);
        if (prevUrl) {
            URL.revokeObjectURL(prevUrl);
            previewUrls.delete(panel);
        }
        if (previewImg) {
            previewImg.removeAttribute('src');
        }
        if (preview) {
            preview.classList.add('d-none');
        }
    }

    function setImageDraft(panel, file) {
        clearImageDraft(panel);
        if (!file) return;
        pendingImages.set(panel, file);
        var url = URL.createObjectURL(file);
        previewUrls.set(panel, url);
        var preview = panel.querySelector('[data-chat-image-preview]');
        var previewImg = panel.querySelector('[data-chat-image-preview-img]');
        if (previewImg) {
            previewImg.src = url;
        }
        if (preview) {
            preview.classList.remove('d-none');
        }
    }

    function appendMessage(panel, message) {
        var list = panel.querySelector('[data-chat-messages]');
        if (!list || list.querySelector('[data-message-id="' + message.id + '"]')) return;

        var mine = message.role === panel.dataset.chatMode;
        var item = document.createElement('div');
        item.className = 'trip-chat-message ' + (mine ? 'is-mine' : 'is-theirs');
        item.dataset.messageId = String(message.id);

        if (message.image_url) {
            var wrap = document.createElement('a');
            wrap.className = 'trip-chat-message__image-wrap';
            wrap.href = message.image_url;
            wrap.target = '_blank';
            wrap.rel = 'noopener noreferrer';
            var img = document.createElement('img');
            img.className = 'trip-chat-message__image';
            img.src = message.image_url;
            img.alt = 'Ảnh đính kèm';
            img.loading = 'lazy';
            wrap.appendChild(img);
            item.appendChild(wrap);
        }

        var text = String(message.body || '').trim();
        if (text) {
            var body = document.createElement('div');
            body.className = 'trip-chat-message__body';
            body.textContent = text;
            item.appendChild(body);
            if (text.indexOf('📞') === 0) {
                item.classList.add('trip-chat-message--call');
            }
        }

        var time = document.createElement('small');
        time.className = 'trip-chat-message__time';
        time.textContent = message.created_at || '';
        item.appendChild(time);

        list.appendChild(item);
        trimMessageList(panel);
        panel.dataset.lastMessageId = String(Math.max(Number(panel.dataset.lastMessageId || 0), Number(message.id || 0)));

        var empty = panel.querySelector('[data-chat-empty]');
        if (empty) empty.classList.add('d-none');

        requestAnimationFrame(function () {
            list.scrollTop = list.scrollHeight;
        });
    }

    function syncComposerEnabled(panel, open) {
        var input = panel.querySelector('[data-chat-input]');
        var send = panel.querySelector('[data-chat-send]');
        var file = panel.querySelector('[data-chat-image]');
        if (input) input.disabled = !open;
        if (send) send.disabled = !open;
        if (file) file.disabled = !open;
        if (!open) {
            clearImageDraft(panel);
        }
    }

    function applyResponse(panel, data) {
        panel.dataset.chatOpen = data.open ? '1' : '0';
        var status = panel.querySelector('[data-chat-status]');
        if (status && data.status_message) status.textContent = data.status_message;

        (data.messages || []).forEach(function (message) {
            appendMessage(panel, message);
        });
        trimMessageList(panel);
        syncComposerEnabled(panel, !!data.open);
    }

    function loadMessages(panel, options) {
        options = options || {};
        var url = panel.dataset.chatListUrl;
        if (!url || panel.classList.contains('d-none')) {
            return Promise.resolve();
        }
        if (!panel.classList.contains('is-open') && !isEmbed(panel) && panel.closest('[hidden]')) {
            return Promise.resolve();
        }
        if (isEmbed(panel) && panel.closest('[hidden]') && !options.force) {
            var embedBody = panel.querySelector('[data-chat-body]');
            if (embedBody && embedBody.classList.contains('d-none')) {
                return Promise.resolve();
            }
        }

        if (options.reset) {
            clearMessageList(panel);
        }

        var params = requestParams(panel);
        var afterId = Number(panel.dataset.lastMessageId || 0);
        if (!options.reset && afterId > 0) {
            params.after_id = afterId;
        }
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
            if (data.unread && window.CustomerInbox && window.CustomerInbox.updateBadges) {
                window.CustomerInbox.updateBadges(data.unread);
            }
        }).catch(function (error) {
            setError(panel, error.message || 'Không tải được tin nhắn.');
        });
    }

    function syncToggleOpenState(toggle, opening) {
        if (!toggle) {
            return;
        }
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        var closedLabel = toggle.getAttribute('data-chat-label-closed') || 'Nhắn tin';
        var openLabel = toggle.getAttribute('data-chat-label-open') || 'Quay lại';
        var label = toggle.querySelector('[data-chat-toggle-label]');
        if (label) {
            label.textContent = opening ? openLabel : closedLabel;
        }
        toggle.setAttribute('aria-label', opening ? openLabel : closedLabel);
        toggle.title = opening ? openLabel : closedLabel;
    }

    function resolveDockOffset(panel) {
        var dock = document.querySelector('.customer-scroll-dock, .driver-app-dock');
        if (dock) {
            var h = Math.round(dock.getBoundingClientRect().height);
            if (h > 0) {
                return h + 'px';
            }
        }
        var root = getComputedStyle(document.documentElement);
        var fromCustomer = root.getPropertyValue('--customer-dock-height').trim();
        var fromDriver = root.getPropertyValue('--driver-dock-height').trim();
        if (panel && panel.dataset.chatMode === 'driver') {
            return fromDriver || fromCustomer || '3.85rem';
        }
        return fromCustomer || fromDriver || '3.85rem';
    }

    function applyDockOffset(panel) {
        if (!panel) return;
        panel.style.setProperty('--trip-chat-dock', resolveDockOffset(panel));
    }

    /** Thanh ngang popup chuyến (user / TX) — neo mép trên chat ngay bên dưới. */
    function resolveSheetHandleEl() {
        var guestSheet = document.getElementById('guest-trip-info-sheet');
        if (guestSheet && guestSheet.classList.contains('is-expanded')) {
            var guestHandle = guestSheet.querySelector('.guest-trip-info-sheet__handle');
            if (guestHandle && guestHandle.offsetParent !== null) {
                return guestHandle;
            }
        }

        var pickup = document.querySelector('[data-driver-pickup-sheet]');
        if (pickup && !pickup.hidden && pickup.offsetParent !== null) {
            var pickupHandle = pickup.querySelector('[data-driver-pickup-sheet-handle]')
                || pickup.querySelector('.driver-pickup-sheet__handle');
            if (pickupHandle) {
                return pickupHandle;
            }
        }

        var tripSheet = document.getElementById('driver-trip-sheet');
        if (tripSheet && !tripSheet.hidden && tripSheet.offsetParent !== null) {
            var tripHandle = tripSheet.querySelector('.driver-trip-sheet__handle');
            if (tripHandle) {
                return tripHandle;
            }
            return tripSheet;
        }

        var bottomPanel = document.getElementById('driver-bottom-panel');
        if (bottomPanel && bottomPanel.classList.contains('is-expanded')) {
            return bottomPanel;
        }

        return null;
    }

    function clearChatSheetAlign(panel) {
        if (!panel) return;
        panel.style.removeProperty('top');
        panel.style.removeProperty('height');
        panel.classList.remove('is-sheet-aligned');
    }

    /** Mép trên chat = ngay dưới thanh ngang (grip); đáy = dock. */
    function applyChatSheetAlign(panel) {
        if (!panel || !panel.classList.contains('is-open') || panel.classList.contains('is-expanded')) {
            return;
        }
        applyDockOffset(panel);
        var handle = resolveSheetHandleEl();
        if (!handle) {
            clearChatSheetAlign(panel);
            return;
        }
        var grip = handle.querySelector('.guest-trip-info-sheet__grip')
            || handle.querySelector('.driver-pickup-sheet__grip')
            || handle.querySelector('.driver-trip-sheet__handle');
        var top = grip
            ? Math.round(grip.getBoundingClientRect().bottom - 4)
            : Math.round(handle.getBoundingClientRect().top + 10);
        var dock = document.querySelector('.customer-scroll-dock, .driver-app-dock');
        var dockH = dock ? Math.round(dock.getBoundingClientRect().height) : 0;
        var maxBottom = window.innerHeight - dockH;
        if (top < 0 || top >= maxBottom - 120) {
            clearChatSheetAlign(panel);
            return;
        }
        panel.style.top = top + 'px';
        panel.style.height = 'auto';
        panel.style.bottom = (dockH > 0 ? dockH + 'px' : resolveDockOffset(panel));
        panel.classList.add('is-sheet-aligned');
    }

    var alignRaf = null;
    function scheduleChatSheetAlign(panel) {
        if (alignRaf) {
            window.cancelAnimationFrame(alignRaf);
        }
        alignRaf = window.requestAnimationFrame(function () {
            alignRaf = null;
            applyChatSheetAlign(panel || document.querySelector('.trip-chat-panel.is-open'));
        });
    }

    function setFullscreenOpen(panel, opening) {
        if (!panel || isEmbed(panel)) {
            return;
        }

        panel.classList.toggle('is-open', !!opening);
        document.documentElement.classList.toggle('trip-chat-open', !!opening);
        document.body.classList.toggle('trip-chat-open', !!opening);

        if (opening) {
            applyDockOffset(panel);
            if (!homeMarkers.has(panel) && panel.parentNode) {
                var marker = document.createComment('trip-chat-home');
                panel.parentNode.insertBefore(marker, panel);
                homeMarkers.set(panel, marker);
                document.body.appendChild(panel);
            }
            scheduleChatSheetAlign(panel);
            window.setTimeout(function () {
                scheduleChatSheetAlign(panel);
            }, 50);
            window.setTimeout(function () {
                scheduleChatSheetAlign(panel);
            }, 280);
            return;
        }

        setExpanded(panel, false);
        clearChatSheetAlign(panel);
        panel.style.removeProperty('--trip-chat-dock');
        clearImageDraft(panel);

        var home = homeMarkers.get(panel);
        if (home && home.parentNode) {
            home.parentNode.insertBefore(panel, home);
            home.parentNode.removeChild(home);
            homeMarkers.delete(panel);
        }
    }

    function setExpanded(panel, expanded) {
        if (!panel || isEmbed(panel)) {
            return;
        }
        applyDockOffset(panel);
        panel.classList.toggle('is-expanded', !!expanded);
        if (expanded) {
            clearChatSheetAlign(panel);
            panel.style.top = '0';
            panel.style.height = 'auto';
        } else if (panel.classList.contains('is-open')) {
            scheduleChatSheetAlign(panel);
        }
        var expandBtn = panel.querySelector('[data-chat-expand]');
        if (!expandBtn) {
            return;
        }
        expandBtn.setAttribute('aria-pressed', expanded ? 'true' : 'false');
        expandBtn.setAttribute(
            'aria-label',
            expanded ? 'Thu nhỏ nửa màn hình' : 'Phóng to toàn màn hình'
        );
        expandBtn.title = expanded ? 'Thu nhỏ nửa màn hình' : 'Phóng to toàn màn hình';
    }

    function setPanelOpen(panel, opening) {
        if (!panel) return;
        var body = panel.querySelector('[data-chat-body]');
        var toggle = panel.querySelector('[data-chat-toggle]');
        if (body) {
            body.classList.toggle('d-none', !opening);
        }
        syncToggleOpenState(toggle, opening);
        setFullscreenOpen(panel, opening);
        if (opening) {
            loadMessages(panel, { reset: true });
            window.setTimeout(function () {
                var input = panel.querySelector('[data-chat-input]');
                if (input && !input.disabled) {
                    input.focus();
                }
            }, 80);
        }
    }

    /** Đóng mọi sheet chat đang mở (không đụng inbox embed). */
    function closeAllOpen() {
        document.querySelectorAll('.trip-chat-panel.is-open').forEach(function (panel) {
            setPanelOpen(panel, false);
        });
    }

    function openDriverPanel(panel) {
        if (!panel) return;
        if (isEmbed(panel)) {
            var body = panel.querySelector('[data-chat-body]');
            if (body) body.classList.remove('d-none');
            var toggle = panel.querySelector('[data-chat-toggle]');
            syncToggleOpenState(toggle, true);
            loadMessages(panel, { reset: true, force: true });
            return;
        }
        setPanelOpen(panel, true);
    }

    function sendMessage(panel, bodyText, imageFile) {
        var url = panel.dataset.chatSendUrl;
        var params = requestParams(panel);
        var fd = new FormData();
        Object.keys(params).forEach(function (key) {
            fd.append(key, params[key]);
        });
        fd.append('body', bodyText || '');
        if (imageFile) {
            fd.append('image', imageFile);
        }

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: fd,
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
                var body = panel.querySelector('[data-chat-body]');
                if (body && !body.classList.contains('d-none')) {
                    loadMessages(panel);
                }
            });
        }, 4000);
    }

    document.addEventListener('change', function (event) {
        var fileInput = event.target.closest('[data-chat-image]');
        if (!fileInput) return;
        var panel = fileInput.closest('[data-trip-chat]');
        if (!panel) return;
        var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if (!file) {
            clearImageDraft(panel);
            return;
        }
        if (!String(file.type || '').startsWith('image/')) {
            setError(panel, 'Chỉ được đính kèm ảnh.');
            clearImageDraft(panel);
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            setError(panel, 'Ảnh tối đa 5MB.');
            clearImageDraft(panel);
            return;
        }
        setError(panel, '');
        setImageDraft(panel, file);
    });

    document.addEventListener('click', function (event) {
        var clearBtn = event.target.closest('[data-chat-image-clear]');
        if (clearBtn) {
            event.preventDefault();
            event.stopPropagation();
            var clearPanel = clearBtn.closest('[data-trip-chat]');
            if (clearPanel) clearImageDraft(clearPanel);
            return;
        }

        var expandBtn = event.target.closest('[data-chat-expand]');
        if (expandBtn) {
            event.preventDefault();
            event.stopPropagation();
            var expandPanel = expandBtn.closest('[data-trip-chat]');
            if (!expandPanel || !expandPanel.classList.contains('is-open')) {
                return;
            }
            setExpanded(expandPanel, !expandPanel.classList.contains('is-expanded'));
            return;
        }

        var toggle = event.target.closest('[data-chat-toggle]');
        if (!toggle) return;
        var panel = toggle.closest('[data-trip-chat]');
        var body = panel && panel.querySelector('[data-chat-body]');
        if (!panel || !body) return;

        var opening = body.classList.contains('d-none');
        setPanelOpen(panel, opening);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var openPanel = document.querySelector('.trip-chat-panel.is-open');
        if (!openPanel) return;
        if (openPanel.classList.contains('is-expanded')) {
            setExpanded(openPanel, false);
            return;
        }
        setPanelOpen(openPanel, false);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-chat-form]');
        if (!form) return;
        event.preventDefault();

        var panel = form.closest('[data-trip-chat]');
        var input = form.querySelector('[data-chat-input]');
        var value = input ? input.value.trim() : '';
        var imageFile = pendingImages.get(panel) || null;
        if (!panel || (!value && !imageFile)) return;

        var button = form.querySelector('[data-chat-send]');
        if (button) button.disabled = true;
        sendMessage(panel, value, imageFile).then(function () {
            if (input) input.value = '';
            clearImageDraft(panel);
        }).catch(function (error) {
            setError(panel, error.message || 'Không gửi được tin nhắn.');
        }).finally(function () {
            if (button && panel.dataset.chatOpen === '1') button.disabled = false;
            if (input) input.focus();
        });
    });

    window.TripChat = {
        openDriverPanel: openDriverPanel,
        setPanelOpen: setPanelOpen,
        setExpanded: setExpanded,
        closeAllOpen: closeAllOpen,
        alignToSheet: scheduleChatSheetAlign,
        injectMessage: function (bookingReference, message, mode) {
            if (!message) {
                return;
            }
            var chatMode = mode === 'customer' ? 'customer' : 'driver';
            var panel = null;
            if (bookingReference) {
                panel = document.querySelector(
                    '[data-trip-chat][data-chat-mode="' + chatMode + '"][data-booking-reference="'
                    + String(bookingReference).replace(/"/g, '')
                    + '"]'
                );
            }
            if (!panel) {
                panel = document.querySelector('[data-trip-chat][data-chat-mode="' + chatMode + '"].is-available');
            }
            if (!panel && chatMode === 'customer') {
                panel = document.querySelector('[data-trip-chat][data-chat-mode="customer"]');
            }
            if (panel) {
                appendMessage(panel, message);
            }
        },
        setCustomerBooking: function (booking) {
            var panel = document.querySelector('[data-trip-chat][data-chat-mode="customer"]');
            if (!panel) return;

            var open = !!(booking && booking.chat && booking.chat.open);
            panel.classList.toggle('d-none', !open);
            panel.classList.toggle('is-available', open);
            panel.dataset.bookingReference = booking && booking.booking_reference
                ? String(booking.booking_reference)
                : '';
            panel.dataset.contactPhone = booking && booking.contact_phone
                ? String(booking.contact_phone)
                : '';
            panel.dataset.chatOpen = open ? '1' : '0';

            if (!open) {
                setPanelOpen(panel, false);
            }
        },
    };

    window.addEventListener('resize', function () {
        scheduleChatSheetAlign();
    });
    window.addEventListener('orientationchange', function () {
        window.setTimeout(function () {
            scheduleChatSheetAlign();
        }, 120);
    });

    startPolling();
})();
