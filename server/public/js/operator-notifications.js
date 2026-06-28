(function () {
    var btn = document.getElementById('operator-notify-btn');
    var panel = document.getElementById('operator-notify-panel');
    var closeBtn = document.getElementById('operator-notify-close');

    if (!btn || !panel) {
        return;
    }

    function openPanel() {
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
    }

    function closePanel() {
        panel.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closePanel();
        });
    }

    document.addEventListener('click', function (e) {
        if (panel.hidden) {
            return;
        }
        if (!panel.contains(e.target) && !btn.contains(e.target)) {
            closePanel();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) {
            closePanel();
        }
    });
})();
