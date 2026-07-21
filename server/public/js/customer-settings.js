/**
 * Đồng bộ cài đặt âm thanh khách trên form Cài đặt.
 */
(function () {
    var form = document.getElementById('customer-settings-form');
    if (!form || !window.AppSounds) {
        return;
    }

    form.addEventListener('submit', function () {
        var localeInput = form.querySelector('input[name="locale"]:checked');
        var presetInput = form.querySelector('input[name="sound_preset"]:checked');
        var resolve = window.AppSounds.resolvePreset || function (k) { return k || 'tone1'; };
        var next = {
            locale: localeInput ? localeInput.value : 'vi',
            sound_enabled: !!(form.querySelector('input[name="sound_enabled"]') || {}).checked,
            sound_preset: resolve(presetInput ? presetInput.value : 'tone1'),
        };
        window.__customerAppSettings = Object.assign({}, window.__customerAppSettings || {}, next);
        if (window.AppSounds.syncCustomerSettings) {
            window.AppSounds.syncCustomerSettings(next);
        }
    });
})();
