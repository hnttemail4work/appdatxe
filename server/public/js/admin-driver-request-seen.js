/**
 * Highlight hàng tài xế có yêu cầu đổi hồ sơ cho đến khi admin bấm Xem.
 * Đánh dấu đã xem theo id yêu cầu (localStorage).
 */
(function () {
    var STORAGE_KEY = 'admin.driverChangeSeen.v1';

    function readSeen() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            var parsed = raw ? JSON.parse(raw) : {};
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function writeSeen(map) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
        } catch (e) { /* ignore quota */ }
    }

    function markSeen(changeId) {
        if (!changeId) {
            return;
        }
        var map = readSeen();
        map[String(changeId)] = 1;
        writeSeen(map);
    }

    function isSeen(changeId) {
        if (!changeId) {
            return false;
        }
        return !!readSeen()[String(changeId)];
    }

    function applyListHighlight() {
        document.querySelectorAll('tr[data-pending-change-id]').forEach(function (row) {
            var id = row.getAttribute('data-pending-change-id');
            if (!id) {
                return;
            }
            if (isSeen(id)) {
                row.classList.remove('driver-row-request-unread');
                row.classList.add('driver-row-request-seen');
            } else {
                row.classList.add('driver-row-request-unread');
                row.classList.remove('driver-row-request-seen');
            }
        });
    }

    function markFromPage() {
        var root = document.querySelector('[data-pending-change-id][data-mark-change-seen]');
        if (!root) {
            return;
        }
        markSeen(root.getAttribute('data-pending-change-id'));
    }

    markFromPage();
    applyListHighlight();
})();
