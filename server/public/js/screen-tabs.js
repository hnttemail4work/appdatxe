(function () {
    function activateFromQuery() {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab');
        if (!tab) return;

        document.querySelectorAll('.screen-tabs-wrap[data-tab-prefix]').forEach(function (wrap) {
            var prefix = wrap.getAttribute('data-tab-prefix');
            var btn = document.getElementById(prefix + '-tab-' + tab);
            if (btn && window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(btn).show();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activateFromQuery);
    } else {
        activateFromQuery();
    }
})();
