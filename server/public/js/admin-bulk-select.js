/**
 * Checkbox bulk select — tái dùng cho booking / khách / tài xế.
 * data-bulk-form, data-bulk-btn, data-bulk-select-all, data-bulk-item
 */
(function () {
    function bindBulk(root) {
        var formId = root.getAttribute('data-bulk-form');
        var btnId = root.getAttribute('data-bulk-btn');
        var form = formId ? document.getElementById(formId) : null;
        var btn = btnId ? document.getElementById(btnId) : null;
        if (!form || !btn) {
            return;
        }

        var selectAll = root.querySelector('[data-bulk-select-all]');
        var itemSelector = root.getAttribute('data-bulk-item') || '[data-bulk-item]';

        function boxes() {
            return Array.prototype.slice.call(root.querySelectorAll(itemSelector));
        }

        function refresh() {
            var all = boxes();
            var checked = all.filter(function (el) {
                return el.checked;
            });
            btn.disabled = checked.length < 1;
            if (selectAll) {
                selectAll.checked = all.length > 0 && checked.length === all.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            }
        }

        boxes().forEach(function (el) {
            el.addEventListener('change', refresh);
        });
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                boxes().forEach(function (el) {
                    el.checked = selectAll.checked;
                });
                refresh();
            });
        }
        refresh();
    }

    function init() {
        document.querySelectorAll('[data-bulk-root]').forEach(bindBulk);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('admin-bulk-select:refresh', init);
})();
