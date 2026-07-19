/* global self, clients */

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

function applyAppBadge(count) {
    if (typeof count !== 'number' || ! isFinite(count)) {
        return Promise.resolve();
    }
    var n = Math.max(0, Math.floor(count));
    if (self.navigator && typeof self.navigator.setAppBadge === 'function') {
        if (n < 1 && typeof self.navigator.clearAppBadge === 'function') {
            return self.navigator.clearAppBadge().catch(function () {});
        }
        return self.navigator.setAppBadge(n).catch(function () {});
    }
    return Promise.resolve();
}

function isTripOfferEvent(payload, data) {
    var eventKey = data.event_key || payload.event_key || '';
    var clientEvent = data.client_event || payload.client_event || '';
    return clientEvent === 'driver_trip_request_created'
        || eventKey === 'driver.new_trip_request'
        || (payload.tag && String(payload.tag).indexOf('driver:trip:') === 0);
}

self.addEventListener('push', function (event) {
    var payload = { title: 'gozviet', body: '', url: '/', icon: '/favicon.svg' };

    if (event.data) {
        try {
            payload = Object.assign(payload, event.data.json());
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    var data = payload.data && typeof payload.data === 'object' ? payload.data : {};
    var targetUrl = payload.url || data.url || '/';
    var unreadTotal = payload.unread_total;
    if (typeof unreadTotal !== 'number' && typeof data.unread_total === 'number') {
        unreadTotal = data.unread_total;
    }

    var tripOffer = isTripOfferEvent(payload, data);

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            var anyVisible = false;
            clientList.forEach(function (client) {
                if (client && 'postMessage' in client) {
                    client.postMessage({
                        type: 'push-event',
                        payload: payload,
                    });
                }
                if (client && client.visibilityState === 'visible') {
                    anyVisible = true;
                }
            });

            // Cuốc mới: luôn hiện system notification để OS kêu khi tab nền/đóng.
            // Tab đang mở: vẫn hiện (requireInteraction) — Web Audio trong tab thường bị Chrome chặn khi nền.
            var options = {
                body: payload.body || '',
                icon: payload.icon || '/favicon.svg',
                badge: '/favicon.svg',
                tag: payload.tag || (tripOffer ? 'driver-trip-offer' : undefined),
                renotify: true,
                silent: false,
                data: Object.assign({}, data, {
                    url: targetUrl,
                    client_event: data.client_event || payload.client_event || null,
                    event_key: data.event_key || payload.event_key || null,
                }),
            };

            if (tripOffer) {
                options.requireInteraction = true;
                options.vibrate = [220, 100, 220, 100, 320];
                options.actions = [
                    { action: 'open', title: 'Xem cuốc' },
                ];
            } else if (anyVisible) {
                // Thông báo thường: nếu đang nhìn app thì chỉ postMessage, tránh spam.
                return applyAppBadge(unreadTotal);
            }

            return applyAppBadge(unreadTotal).then(function () {
                return self.registration.showNotification(payload.title || 'gozviet', options);
            });
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url && 'focus' in client) {
                    try {
                        var clientPath = new URL(client.url).pathname;
                        var targetPath = new URL(targetUrl, self.location.origin).pathname;
                        if (clientPath.indexOf('/driver') === 0 && targetPath.indexOf('/driver') === 0) {
                            return client.focus().then(function (focused) {
                                if (focused && focused.navigate) {
                                    return focused.navigate(targetUrl);
                                }
                                return focused;
                            });
                        }
                    } catch (e) {
                        /* ignore */
                    }
                }
                if (client.url && client.url.indexOf(targetUrl) !== -1 && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
            return undefined;
        })
    );
});

self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type === 'set-app-badge') {
        event.waitUntil(applyAppBadge(Number(data.count) || 0));
    }
    if (data.type === 'clear-app-badge') {
        event.waitUntil(applyAppBadge(0));
    }
});
