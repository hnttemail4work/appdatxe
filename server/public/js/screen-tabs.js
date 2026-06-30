(function () {
    function storageKey(prefix) {
        return 'screen-tab-' + prefix;
    }

    function cookieName(prefix) {
        return prefix + '_tab';
    }

    function setCookie(prefix, key) {
        document.cookie = cookieName(prefix) + '=' + encodeURIComponent(key)
            + ';path=/;max-age=' + (86400 * 30) + ';SameSite=Lax';
    }

    function readCookie(prefix) {
        var name = cookieName(prefix) + '=';
        var parts = document.cookie.split(';');
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (part.indexOf(name) === 0) {
                return decodeURIComponent(part.substring(name.length));
            }
        }

        return '';
    }

    function syncUrlTab(tab) {
        var url = new URL(window.location.href);
        if (tab) {
            url.searchParams.set('tab', tab);
        } else {
            url.searchParams.delete('tab');
        }
        history.replaceState(null, '', url);
    }

    function activateTab(prefix, tab) {
        if (!tab) {
            return;
        }

        var btn = document.getElementById(prefix + '-tab-' + tab);
        if (btn && window.bootstrap && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(btn).show();
        }
    }

    function initTabs() {
        var params = new URLSearchParams(window.location.search);
        var tabFromUrl = params.get('tab');

        document.querySelectorAll('.screen-tabs-wrap[data-tab-prefix]').forEach(function (wrap) {
            var prefix = wrap.getAttribute('data-tab-prefix');
            if (!prefix) {
                return;
            }

            wrap.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
                btn.addEventListener('shown.bs.tab', function () {
                    var id = btn.id || '';
                    var key = id.replace(prefix + '-tab-', '');
                    if (!key || key === id) {
                        return;
                    }
                    sessionStorage.setItem(storageKey(prefix), key);
                    setCookie(prefix, key);
                    syncUrlTab(key);
                });
            });

            var tab = tabFromUrl || sessionStorage.getItem(storageKey(prefix)) || readCookie(prefix);
            if (tab && tab !== tabFromUrl) {
                syncUrlTab(tab);
            }
            activateTab(prefix, tab);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        initTabs();
    }
})();
