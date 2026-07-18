@php
    $mustChangePassword = (bool) ($mustChangePassword ?? false);
    $pendingDocs = $pendingChangeRequest ?? null;
@endphp
<section class="driver-account-panel" aria-label="Thông tin cá nhân">
    <h2 class="driver-panel-title mb-3" data-i18n="account_title">Thông tin cá nhân</h2>

    @if($mustChangePassword)
        <div class="driver-notice driver-notice-warning mb-3" role="alert">
            <strong data-i18n="account_password_required_title">Đổi mật khẩu</strong>
            <p class="mb-0 small" data-i18n="account_password_required_hint">Bạn đang dùng mật khẩu mặc định. Vui lòng đặt mật khẩu mới để bảo mật tài khoản.</p>
        </div>
    @endif

    <nav class="driver-account-menu" aria-label="Mục thông tin cá nhân">
        <button type="button" class="driver-account-menu__item" data-driver-tab="account-profile">
            <span class="driver-account-menu__copy">
                <strong data-i18n="account_profile">Hồ sơ</strong>
                <span class="driver-account-menu__hint" data-i18n="account_profile_menu_hint">Xem họ tên, SĐT, mã tài xế, xe</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>

        <button type="button" class="driver-account-menu__item" data-driver-tab="account-update">
            <span class="driver-account-menu__copy">
                <strong data-i18n="account_update">Cập nhật thông tin</strong>
                <span class="driver-account-menu__hint">
                    <span data-i18n="account_update_menu_hint">Biển số, loại xe, ngân hàng, ảnh giấy tờ</span>
                    @if($pendingDocs)
                        · Đang chờ duyệt
                    @endif
                </span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>

        <button type="button" class="driver-account-menu__item {{ $mustChangePassword ? 'is-alert' : '' }}" data-driver-tab="account-password">
            <span class="driver-account-menu__copy">
                <strong data-i18n="account_password">Đổi mật khẩu</strong>
                <span class="driver-account-menu__hint" data-i18n="account_password_menu_hint">Bảo mật tài khoản đăng nhập</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>
    </nav>
</section>
