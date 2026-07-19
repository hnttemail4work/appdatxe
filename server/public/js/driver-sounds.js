/**
 * Âm thanh tài xế — dùng AppSounds + cài đặt sound_enabled / sound_preset của tài xế.
 */
(function () {
    var settings = window.__driverAppSettings || {};
    var lastTripCount = null;

    function ensureAppSounds() {
        return window.AppSounds || null;
    }

    function syncSettings(next) {
        settings = Object.assign({}, settings, next || {});
        window.__driverAppSettings = settings;
        if (next && next.locale && window.DriverI18n) {
            window.DriverI18n.setLocale(next.locale);
        }
    }

    function play(presetKey) {
        var app = ensureAppSounds();
        if (!app) {
            return;
        }
        if (settings.sound_enabled === false) {
            return;
        }
        app.play(presetKey || settings.sound_preset || undefined);
    }

    function onTripCount(count) {
        var value = Number(count) || 0;
        if (lastTripCount === null) {
            lastTripCount = value;
            return;
        }
        if (value > lastTripCount) {
            play();
        }
        lastTripCount = value;
    }

    function onInboxUnread(unread) {
        var app = ensureAppSounds();
        if (!app) {
            return;
        }
        if (settings.sound_enabled === false) {
            return;
        }
        app.onInboxUnread(unread);
    }

    var form = document.getElementById('driver-settings-form');
    if (form) {
        form.addEventListener('submit', function () {
            var localeInput = form.querySelector('input[name="locale"]:checked');
            var presetInput = form.querySelector('input[name="sound_preset"]:checked');
            var resolve = (window.AppSounds && window.AppSounds.resolvePreset) || function (k) { return k || 'tone1'; };
            syncSettings({
                locale: localeInput ? localeInput.value : 'vi',
                sound_enabled: !!(form.querySelector('input[name="sound_enabled"]') || {}).checked,
                sound_preset: resolve(presetInput ? presetInput.value : 'tone1'),
            });
        });
    }

    var inboxOpen = document.getElementById('driver-inbox-open');
    if (inboxOpen) {
        inboxOpen.addEventListener('click', function () {
            if (document.querySelector('.driver-map-chrome__bell-dot')) {
                play();
            }
        });
    }

    window.DriverSounds = {
        play: play,
        playTrip: play,
        playAlert: play,
        onTripCount: onTripCount,
        onInboxUnread: onInboxUnread,
        syncSettings: syncSettings,
    };
})();
