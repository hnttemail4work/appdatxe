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

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            clientList.forEach(function (client) {
                if (client && 'postMessage' in client) {
                    client.postMessage({
                        type: 'push-event',
                        payload: payload,
                    });
                }
            });

            return applyAppBadge(unreadTotal).then(function () {
                return self.registration.showNotification(payload.title || 'gozviet', {
                    body: payload.body || '',
                    icon: payload.icon || '/favicon.svg',
                    badge: '/favicon.svg',
                    tag: payload.tag || undefined,
                    renotify: true,
                    data: Object.assign({}, data, { url: targetUrl }),
                });
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
