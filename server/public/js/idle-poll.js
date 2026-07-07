/**
 * Poll định kỳ khi người dùng không tương tác (mặc định 5 giây).
 */
(function (global) {
    var DEFAULT_MS = 5000;

    function isBlocked() {
        if (document.hidden) {
            return true;
        }

        if (document.querySelector('.modal.show, .address-map-picker-modal.show')) {
            return true;
        }

        var active = document.activeElement;
        if (active && active.matches('input:not([readonly]):not([type="hidden"]), textarea, select')) {
            return true;
        }

        return false;
    }

    function create(options) {
        options = options || {};
        var intervalMs = options.intervalMs || DEFAULT_MS;
        var onPoll = typeof options.onPoll === 'function' ? options.onPoll : function () {};
        var paused = false;
        var timer = null;
        var lastActivity = Date.now();

        function touchActivity() {
            lastActivity = Date.now();
        }

        function tick() {
            if (paused || isBlocked()) {
                return;
            }
            if (Date.now() - lastActivity < intervalMs) {
                return;
            }
            onPoll();
        }

        function start() {
            if (timer) {
                return;
            }

            ['click', 'keydown', 'touchstart', 'input', 'change'].forEach(function (eventName) {
                document.addEventListener(eventName, touchActivity, true);
            });

            timer = global.setInterval(tick, intervalMs);
        }

        function stop() {
            if (!timer) {
                return;
            }
            global.clearInterval(timer);
            timer = null;
        }

        return {
            start: start,
            stop: stop,
            pause: function () { paused = true; },
            resume: function () { paused = false; touchActivity(); },
            touchActivity: touchActivity,
        };
    }

    global.IdlePoll = {
        create: create,
        DEFAULT_MS: DEFAULT_MS,
        isBlocked: isBlocked,
    };
})(window);
