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
            settings_sound: 'Âm thanh thông báo',
            settings_sound_hint: 'Âm thanh khi có cuốc mới hoặc tin hộp thư (Thông báo / Thông tin). Đồng bộ mặc định admin; có thể đổi tone riêng.',
            settings_sound_enabled: 'Bật âm thanh thông báo',
            settings_save: 'Lưu cài đặt',
            preview: 'Nghe thử',
            sound_tone1: 'Chuông nhanh (mặc định)',
            sound_tone2: 'Ping nhẹ',
            sound_tone3: 'Nhịp đôi',
            sound_tone4: 'Còi ngắn',
            sound_tone5: 'Gợn sóng',
            account_title: 'Thông tin cá nhân',
            account_profile: 'Hồ sơ',
            account_profile_menu_hint: 'Xem họ tên, SĐT, mã tài xế, xe',
            account_name: 'Họ tên',
            account_phone: 'Số điện thoại',
            account_code: 'Mã tài xế',
            account_update: 'Cập nhật thông tin',
            account_update_menu_hint: 'Biển số, loại xe, ngân hàng, ảnh giấy tờ',
            account_update_hint: 'Cập nhật biển số, loại xe, ngân hàng và ảnh giấy tờ. Thay đổi chỉ áp dụng sau khi quản trị duyệt.',
            account_password: 'Đổi mật khẩu',
            account_password_menu_hint: 'Bảo mật tài khoản đăng nhập',
            account_password_hint: 'Mật khẩu tối thiểu 6 ký tự. Dùng mật khẩu riêng, không chia sẻ cho người khác.',
            account_password_current: 'Mật khẩu hiện tại',
            account_password_new: 'Mật khẩu mới',
            account_password_confirm: 'Nhập lại mật khẩu mới',
            account_password_save: 'Lưu mật khẩu mới',
            account_password_required_title: 'Đổi mật khẩu',
            account_password_required_hint: 'Bạn đang dùng mật khẩu mặc định. Vui lòng đặt mật khẩu mới để bảo mật tài khoản.',
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
            settings_sound: 'Notification sound',
            settings_sound_hint: 'Sound for new trips or inbox messages (Notice / Info). Follows admin default; you can pick your own tone.',
            settings_sound_enabled: 'Enable notification sound',
            settings_save: 'Save settings',
            preview: 'Preview',
            sound_tone1: 'Quick chime (default)',
            sound_tone2: 'Soft ping',
            sound_tone3: 'Double beat',
            sound_tone4: 'Short alert',
            sound_tone5: 'Wave pulse',
            account_title: 'Personal info',
            account_profile: 'Profile',
            account_profile_menu_hint: 'Name, phone, driver code, vehicle',
            account_name: 'Full name',
            account_phone: 'Phone number',
            account_code: 'Driver code',
            account_update: 'Update profile',
            account_update_menu_hint: 'Plate, vehicle type, bank, documents',
            account_update_hint: 'Update plate, vehicle type, bank and document photos. Changes apply after admin approval.',
            account_password: 'Change password',
            account_password_menu_hint: 'Secure your login account',
            account_password_hint: 'Password must be at least 6 characters. Use a private password.',
            account_password_current: 'Current password',
            account_password_new: 'New password',
            account_password_confirm: 'Confirm new password',
            account_password_save: 'Save new password',
            account_password_required_title: 'Change password',
            account_password_required_hint: 'You are using the default password. Please set a new one to secure your account.',
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
