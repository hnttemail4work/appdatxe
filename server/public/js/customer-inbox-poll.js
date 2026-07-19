/**
 * Poll hộp thư khách — cập nhật badge + phát âm khi notice/info tăng.
 */
(function () {
    var url = window.__customerInboxPollUrl;
    if (!url) {
        return;
    }

    var timer = null;

    function pollInbox() {
        if (document.hidden) {
            return;
        }
        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.unread) {
                    return;
                }
                if (window.AppSounds && window.AppSounds.onInboxUnread) {
                    window.AppSounds.onInboxUnread(data.unread);
                }
                if (window.AppInboxBadge && window.AppInboxBadge.apply) {
                    window.AppInboxBadge.apply(data.unread);
                }
                if (window.CustomerInbox && window.CustomerInbox.updateBadges) {
                    window.CustomerInbox.updateBadges(data.unread);
                }
            })
            .catch(function () { /* ignore */ });
    }

    // Baseline ngay khi vào trang (không kêu), rồi poll định kỳ.
    pollInbox();
    timer = window.setInterval(pollInbox, 12000);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            pollInbox();
        }
    });
})();
