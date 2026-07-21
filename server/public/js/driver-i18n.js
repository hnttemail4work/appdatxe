/**
 * Driver i18n — vi / en cho các nhãn UI tài xế (data-i18n).
 */
(function () {
    var dict = {
        vi: {
            settings_title: 'Cài đặt',
            settings_docs: 'Giấy tờ tài xế',
            settings_docs_hint: 'Cập nhật CCCD / bằng lái / ảnh xe. Thay đổi chỉ áp dụng sau khi quản trị duyệt.',
            settings_docs_pending: 'Đang chờ duyệt cập nhật giấy tờ.',
            settings_docs_cta: 'Cập nhật giấy tờ',
            settings_docs_title: 'Cập nhật giấy tờ',
            settings_docs_submit_hint: 'Gửi thông tin mới để quản trị duyệt. Hồ sơ hiện tại giữ nguyên cho đến khi được duyệt.',
            settings_language: 'Ngôn ngữ',
            settings_sound: 'Âm thanh',
            settings_sound_hint: '',
            settings_sound_enabled: 'Bật âm thanh',
            settings_save: 'Lưu cài đặt',
            preview: 'Nghe thử',
            sound_tone1: 'Chuông nhanh (mặc định)',
            sound_tone2: 'Ping nhẹ',
            sound_tone3: 'Nhịp đôi',
            sound_tone4: 'Còi ngắn',
            sound_tone5: 'Gợn sóng',
            account_title: 'Thông tin cá nhân',
            account_profile: 'Thông tin',
            account_profile_menu_hint: 'Xem họ tên, SĐT, mã tài xế, xe',
            account_name: 'Họ tên',
            account_phone: 'Số điện thoại',
            account_code: 'Mã tài xế',
            account_update: 'Hồ sơ tài xế',
            account_update_menu_hint: 'Thông tin, giấy tờ xe & CCCD',
            account_update_hint: 'Cập nhật biển số, loại xe, ngân hàng và ảnh giấy tờ. Thay đổi chỉ áp dụng sau khi quản trị duyệt.',
        },
        en: {
            settings_title: 'Settings',
            settings_docs: 'Driver documents',
            settings_docs_hint: 'Update ID / license / vehicle photos. Changes apply only after admin approval.',
            settings_docs_pending: 'Document update pending admin approval.',
            settings_docs_cta: 'Update documents',
            settings_docs_title: 'Update documents',
            settings_docs_submit_hint: 'Submit new info for admin review. Current profile stays until approved.',
            settings_language: 'Language',
            settings_sound: 'Sound',
            settings_sound_hint: '',
            settings_sound_enabled: 'Enable sound',
            settings_save: 'Save settings',
            preview: 'Preview',
            sound_tone1: 'Quick chime (default)',
            sound_tone2: 'Soft ping',
            sound_tone3: 'Double beat',
            sound_tone4: 'Short alert',
            sound_tone5: 'Wave pulse',
            account_title: 'Personal info',
            account_profile: 'Info',
            account_profile_menu_hint: 'Name, phone, driver code, vehicle',
            account_name: 'Full name',
            account_phone: 'Phone number',
            account_code: 'Driver code',
            account_update: 'Driver profile',
            account_update_menu_hint: 'Info, vehicle docs & ID',
            account_update_hint: 'Update plate, vehicle type, bank and document photos. Changes apply after admin approval.',
        },
    };

    var locale = (window.__driverAppSettings && window.__driverAppSettings.locale) || 'vi';

    function t(key) {
        var pack = dict[locale] || dict.vi;
        return pack[key] || dict.vi[key] || key;
    }

    function apply(root) {
        (root || document).querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            if (!key) {
                return;
            }
            el.textContent = t(key);
        });
        document.documentElement.lang = locale === 'en' ? 'en' : 'vi';
    }

    function setLocale(next) {
        locale = next === 'en' ? 'en' : 'vi';
        if (window.__driverAppSettings) {
            window.__driverAppSettings.locale = locale;
        }
        apply();
    }

    window.DriverI18n = {
        t: t,
        apply: apply,
        setLocale: setLocale,
        getLocale: function () {
            return locale;
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            apply();
        });
    } else {
        apply();
    }
})();
