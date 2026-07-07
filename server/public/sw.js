/* global self, clients */

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var payload = { title: 'gozviet', body: '', url: '/', icon: '/favicon.svg' };

    if (event.data) {
        try {
            payload = Object.assign(payload, event.data.json());
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    // TODO (Fix Stuck Offer UI): Đẩy payload vào tab đang mở để app tài xế tự thu hồi card hết hạn ngay.
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

            return self.registration.showNotification(payload.title || 'gozviet', {
                body: payload.body || '',
                icon: payload.icon || '/favicon.svg',
                badge: '/favicon.svg',
                tag: payload.tag || undefined,
                data: { url: payload.url || '/' },
            });
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/';

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
