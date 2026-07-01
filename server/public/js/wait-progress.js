(function (global) {
    function parseWaitProgress(raw) {
        if (!raw) {
            return null;
        }
        if (typeof raw === 'object') {
            return raw;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function formatWaitRemaining(seconds) {
        if (seconds <= 0) {
            return 'Đang cập nhật…';
        }
        if (seconds < 60) {
            return 'Còn ' + seconds + ' giây';
        }
        var minutes = Math.ceil(seconds / 60);
        if (minutes < 60) {
            return 'Còn ~' + minutes + ' phút';
        }
        var hours = Math.floor(minutes / 60);
        var mins = minutes % 60;
        return 'Còn ~' + hours + ' giờ' + (mins > 0 ? ' ' + mins + ' phút' : '');
    }

    function formatElapsed(startedMs, kind) {
        var minutes = Math.max(1, Math.floor((Date.now() - startedMs) / 60000));
        if (kind === 'driver_search' || kind === 'driver_search_extended') {
            return 'Đã tìm ' + minutes + ' phút';
        }
        if (kind === 'complete_overdue') {
            return 'Quá hạn ' + minutes + ' phút';
        }
        if (kind === 'movement_confirm') {
            return 'Quá hạn ' + minutes + ' phút';
        }
        return 'Đã ' + minutes + ' phút';
    }

    function applyWaitProgressEl(block, wait) {
        if (!block || !wait) {
            return;
        }

        var labelEl = block.querySelector('[data-field="wait_label"]');
        var timeEl = block.querySelector('[data-field="wait_time"]');
        var hintEl = block.querySelector('[data-field="wait_hint"]');
        var barEl = block.querySelector('[data-field="wait_bar"]');

        block.classList.remove('is-indeterminate', 'is-review', 'is-overdue');
        if (wait.kind === 'review') {
            block.classList.add('is-review');
        }
        if (wait.kind === 'complete_overdue' || wait.kind === 'movement_confirm' && wait.indeterminate) {
            block.classList.add('is-overdue');
        }

        if (labelEl && wait.label) {
            labelEl.textContent = wait.label;
        }
        if (hintEl && wait.hint) {
            hintEl.textContent = wait.hint;
        }

        var startedMs = wait.started_at ? new Date(wait.started_at).getTime() : Date.now();
        var deadlineMs = wait.deadline_at ? new Date(wait.deadline_at).getTime() : null;
        var totalMs = Math.max(1000, (Number(wait.total_seconds) || 0) * 1000);

        if (wait.indeterminate) {
            block.classList.add('is-indeterminate');
            if (timeEl) {
                timeEl.textContent = formatElapsed(startedMs, wait.kind);
            }
            if (barEl) {
                barEl.style.width = '';
            }
            return;
        }

        block.classList.remove('is-indeterminate');
        var now = Date.now();
        var remainingSec = deadlineMs ? Math.max(0, Math.floor((deadlineMs - now) / 1000)) : 0;
        var elapsedMs = Math.max(0, now - startedMs);
        var pct = deadlineMs
            ? Math.min(100, Math.max(0, Math.round((elapsedMs / totalMs) * 100)))
            : 0;

        if (timeEl) {
            timeEl.textContent = deadlineMs ? formatWaitRemaining(remainingSec) : '';
        }
        if (barEl) {
            barEl.style.width = pct + '%';
        }
    }

    var tickTimer = null;
    var roots = new Set();

    function syncRoot(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('[data-wait-progress]').forEach(function (block) {
            var wait = parseWaitProgress(block.dataset.waitState);
            if (wait) {
                applyWaitProgressEl(block, wait);
            }
        });
    }

    function syncAll() {
        roots.forEach(syncRoot);
    }

    function startLoop() {
        if (tickTimer) {
            return;
        }
        tickTimer = global.setInterval(syncAll, 1000);
    }

    function observeRoot(root) {
        if (!root) {
            return;
        }
        roots.add(root);
        syncRoot(root);
        startLoop();
    }

    function buildBlockHtml(prefix, wait) {
        var hint = wait.hint
            ? '<p class="' + prefix + '-hint mb-0" data-field="wait_hint">' + wait.hint + '</p>'
            : '';
        return ''
            + '<div class="' + prefix + '-head">'
            + '<div class="' + prefix + '-copy">'
            + '<div class="' + prefix + '-label" data-field="wait_label">' + (wait.label || '') + '</div>'
            + '<div class="' + prefix + '-time" data-field="wait_time"></div>'
            + '</div>'
            + '</div>'
            + '<div class="' + prefix + '-bar" aria-hidden="true">'
            + '<div class="' + prefix + '-bar-fill" data-field="wait_bar"></div>'
            + '</div>'
            + hint;
    }

    function createBlock(wait, variant) {
        var prefix = variant === 'driver' ? 'driver-wait' : 'guest-trip-wait';
        var block = document.createElement('div');
        block.className = prefix + ' ' + prefix + '--' + (wait.kind || 'default');
        block.setAttribute('data-wait-progress', '');
        block.setAttribute('role', 'status');
        block.setAttribute('aria-live', 'polite');
        block.innerHTML = buildBlockHtml(prefix, wait);
        block.dataset.waitState = JSON.stringify(wait);
        applyWaitProgressEl(block, wait);
        observeRoot(document);
        return block;
    }

    function mount(block, wait) {
        if (!block) {
            return;
        }
        if (!wait) {
            block.classList.add('d-none');
            block.dataset.waitState = '';
            return;
        }
        block.classList.remove('d-none');
        block.dataset.waitState = JSON.stringify(wait);
        applyWaitProgressEl(block, wait);
        if (block.closest) {
            observeRoot(block.closest('[data-wait-progress-root]') || document);
        } else {
            observeRoot(document);
        }
    }

    global.WaitProgress = {
        parse: parseWaitProgress,
        apply: applyWaitProgressEl,
        mount: mount,
        create: createBlock,
        observeRoot: observeRoot,
        syncRoot: syncRoot,
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-wait-progress-root]').forEach(observeRoot);
    });
})(window);
