/**
 * Âm thanh thông báo dùng chung (Web Audio tone1–tone5).
 * - Mặc định nền tảng: window.__appSoundSettings { enabled, preset }
 * - Tài xế ghi đè qua DriverSounds / __driverAppSettings
 */
(function () {
    'use strict';

    var platform = window.__appSoundSettings || { enabled: true, preset: 'tone1' };
    var ctx = null;
    var lastInbox = null;

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

    function effectiveSettings() {
        var driver = window.__driverAppSettings;
        if (driver && typeof driver === 'object') {
            return {
                enabled: driver.sound_enabled !== false,
                preset: resolvePreset(driver.sound_preset || platform.preset || 'tone1'),
            };
        }
        var customer = window.__customerAppSettings;
        if (customer && typeof customer === 'object') {
            return {
                enabled: customer.sound_enabled !== false,
                preset: resolvePreset(customer.sound_preset || platform.preset || 'tone1'),
            };
        }
        return {
            enabled: platform.enabled !== false,
            preset: resolvePreset(platform.preset || 'tone1'),
        };
    }

    function syncCustomerSettings(next) {
        window.__customerAppSettings = Object.assign({}, window.__customerAppSettings || {}, next || {});
    }

    function play(presetKey, force) {
        var cfg = effectiveSettings();
        if (!force && !cfg.enabled) {
            return;
        }
        var key = resolvePreset(presetKey || cfg.preset);
        var notes = PRESETS[key] || PRESETS.tone1;
        notes.forEach(function (note) {
            setTimeout(function () {
                beep(note);
            }, note.delay || 0);
        });
    }

    function syncPlatform(next) {
        platform = Object.assign({}, platform, next || {});
        window.__appSoundSettings = platform;
    }

    /**
     * Phát âm khi notice hoặc info tăng (không tính chat).
     * @param {{notice?: number, info?: number}|null} unread
     */
    function onInboxUnread(unread) {
        if (!unread || typeof unread !== 'object') {
            return;
        }
        var notice = Number(unread.notice) || 0;
        var info = Number(unread.info) || 0;
        if (lastInbox === null) {
            lastInbox = { notice: notice, info: info };
            return;
        }
        if (notice > lastInbox.notice || info > lastInbox.info) {
            play();
        }
        lastInbox = { notice: notice, info: info };
    }

    function resetInboxBaseline(unread) {
        if (!unread || typeof unread !== 'object') {
            lastInbox = { notice: 0, info: 0 };
            return;
        }
        lastInbox = {
            notice: Number(unread.notice) || 0,
            info: Number(unread.info) || 0,
        };
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-sound-preview]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        play(btn.getAttribute('data-sound-preview'), true);
    });

    // Unlock AudioContext sau tương tác đầu (trình duyệt chặn autoplay).
    function unlock() {
        var ac = audioCtx();
        if (ac && ac.state === 'suspended') {
            ac.resume();
        }
        document.removeEventListener('pointerdown', unlock);
        document.removeEventListener('keydown', unlock);
    }
    document.addEventListener('pointerdown', unlock, { once: true });
    document.addEventListener('keydown', unlock, { once: true });

    window.AppSounds = {
        play: play,
        onInboxUnread: onInboxUnread,
        resetInboxBaseline: resetInboxBaseline,
        syncPlatform: syncPlatform,
        syncCustomerSettings: syncCustomerSettings,
        resolvePreset: resolvePreset,
        PRESETS: PRESETS,
    };
})();
