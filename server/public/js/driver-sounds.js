/**
 * Driver notification sound — one shared preset (tone1–tone5), Web Audio.
 */
(function () {
    var settings = window.__driverAppSettings || {};
    var ctx = null;
    var lastTripCount = null;

    var PRESETS = {
        tone1: [
            { freq: 660, duration: 0.14, type: 'triangle', volume: 0.2, delay: 0 },
            { freq: 880, duration: 0.16, type: 'triangle', volume: 0.22, delay: 130 },
            { freq: 1175, duration: 0.18, type: 'triangle', volume: 0.2, delay: 260 },
        ],
        tone2: [
            { freq: 880, duration: 0.12, type: 'sine', volume: 0.16, delay: 0 },
            { freq: 1320, duration: 0.2, type: 'sine', volume: 0.14, delay: 110 },
        ],
        tone3: [
            { freq: 523, duration: 0.14, type: 'square', volume: 0.1, delay: 0 },
            { freq: 659, duration: 0.14, type: 'square', volume: 0.1, delay: 160 },
            { freq: 523, duration: 0.14, type: 'square', volume: 0.1, delay: 320 },
            { freq: 659, duration: 0.18, type: 'square', volume: 0.1, delay: 480 },
        ],
        tone4: [
            { freq: 740, duration: 0.1, type: 'sawtooth', volume: 0.12, delay: 0 },
            { freq: 980, duration: 0.22, type: 'sawtooth', volume: 0.14, delay: 90 },
        ],
        tone5: [
            { freq: 440, duration: 0.22, type: 'sine', volume: 0.14, delay: 0 },
            { freq: 554, duration: 0.22, type: 'sine', volume: 0.13, delay: 180 },
            { freq: 659, duration: 0.28, type: 'sine', volume: 0.12, delay: 360 },
        ],
    };

    function audioCtx() {
        if (ctx) {
            return ctx;
        }
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) {
            return null;
        }
        ctx = new AC();
        return ctx;
    }

    function beep(opts) {
        var ac = audioCtx();
        if (!ac) {
            return;
        }
        if (ac.state === 'suspended') {
            ac.resume();
        }

        var now = ac.currentTime;
        var osc = ac.createOscillator();
        var gain = ac.createGain();
        osc.type = opts.type || 'sine';
        osc.frequency.setValueAtTime(opts.freq || 880, now);
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(opts.volume || 0.18, now + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + (opts.duration || 0.28));
        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(now);
        osc.stop(now + (opts.duration || 0.28) + 0.02);
    }

    function resolvePreset(key) {
        return PRESETS[key] ? key : 'tone1';
    }

    function play(presetKey) {
        if (settings.sound_enabled === false) {
            return;
        }
        var key = resolvePreset(presetKey || settings.sound_preset || 'tone1');
        var notes = PRESETS[key] || PRESETS.tone1;
        notes.forEach(function (note) {
            setTimeout(function () {
                beep(note);
            }, note.delay || 0);
        });
    }

    function syncSettings(next) {
        settings = Object.assign({}, settings, next || {});
        window.__driverAppSettings = settings;
        if (next && next.locale && window.DriverI18n) {
            window.DriverI18n.setLocale(next.locale);
        }
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

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-sound-preview]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var wasEnabled = settings.sound_enabled;
        settings.sound_enabled = true;
        play(btn.getAttribute('data-sound-preview'));
        settings.sound_enabled = wasEnabled;
    });

    var form = document.getElementById('driver-settings-form');
    if (form) {
        form.addEventListener('submit', function () {
            var localeInput = form.querySelector('input[name="locale"]:checked');
            var presetInput = form.querySelector('input[name="sound_preset"]:checked');
            syncSettings({
                locale: localeInput ? localeInput.value : 'vi',
                sound_enabled: !!(form.querySelector('input[name="sound_enabled"]') || {}).checked,
                sound_preset: resolvePreset(presetInput ? presetInput.value : 'tone1'),
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
        syncSettings: syncSettings,
    };
})();
