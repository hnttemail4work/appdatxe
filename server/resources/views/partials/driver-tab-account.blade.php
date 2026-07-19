@php
    $mustChangePassword = (bool) ($mustChangePassword ?? false);
    $pendingDocs = $pendingChangeRequest ?? null;
    $user = $user ?? auth()->user();
    $profile = $profile ?? null;
    $driverWallet = $driverWallet ?? null;
    $heroStatus = $heroStatus ?? ['key' => 'offline', 'label' => 'Tạm nghỉ'];

    $driverInitial = $user ? mb_strtoupper(mb_substr($user->preferredDisplayName(), 0, 1)) : '?';
    $driverPhotoUrl = $profile?->photoUrl('photo_portrait');
    $walletBalanceLabel = $driverWallet
        ? number_format($driverWallet->balance, 0, ',', '.') . ' đ'
        : '—';
    $driverRatingLabel = $profile?->starRatingLabel() ?? '—';
    $registerUrl = route('register', ['from' => 'driver']);
    $statusKey = $heroStatus['key'] ?? 'offline';
    $statusLabel = $heroStatus['label'] ?? 'Tạm nghỉ';
@endphp
<section class="driver-account-panel" aria-label="Thông tin cá nhân">
    {{-- Giữ id trạng thái ẩn cho JS availability --}}
    <div class="d-none" aria-hidden="true">
        <div id="driver-hero-status-pill" class="driver-drawer__status driver-drawer__status--{{ $statusKey }}">
            <span id="driver-hero-status-label">{{ $statusLabel }}</span>
        </div>
    </div>

    <h2 class="driver-panel-title mb-3" data-i18n="account_title">Thông tin cá nhân</h2>

    <div class="driver-account-identity mb-3">
        <div class="driver-avatar driver-account-identity__avatar">
            @if($driverPhotoUrl)
                <img src="{{ $driverPhotoUrl }}" alt="" class="driver-avatar-img" loading="lazy" decoding="async">
            @else
                <span class="driver-avatar-fallback" aria-hidden="true">{{ $driverInitial }}</span>
            @endif
        </div>
        <div class="driver-account-identity__meta">
            <strong class="driver-account-identity__name">{{ $user?->preferredDisplayName() ?? '—' }}</strong>
            <span class="driver-account-identity__rating" aria-label="Đánh giá {{ $driverRatingLabel }} sao">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M12 2.5l2.9 5.88 6.49.94-4.7 4.58 1.11 6.47L12 17.77l-5.8 3.05 1.11-6.47-4.7-4.58 6.49-.94L12 2.5z"/>
                </svg>
                {{ $driverRatingLabel }}
            </span>
        </div>
    </div>

    @if($mustChangePassword)
        <div class="driver-notice driver-notice-warning mb-3" role="alert">
            <strong data-i18n="account_password_required_title">Đổi mật khẩu</strong>
            <p class="mb-0 small" data-i18n="account_password_required_hint">Bạn đang dùng mật khẩu mặc định. Vui lòng đặt mật khẩu mới để bảo mật tài khoản.</p>
        </div>
    @endif

    <nav class="driver-account-menu" aria-label="Mục tài khoản">
        <button type="button" class="driver-account-menu__item" data-driver-tab="wallet">
            <span class="driver-account-menu__copy">
                <strong>Ví tài xế</strong>
                <span class="driver-account-menu__hint">Số dư {{ $walletBalanceLabel }}</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>

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

        <button type="button"
                class="driver-account-menu__item"
                id="driver-account-pwa-install"
                data-pwa-install-trigger>
            <span class="driver-account-menu__copy">
                <strong data-pwa-install-label>Ghim vào màn hình chính</strong>
                <span class="driver-account-menu__hint" data-pwa-install-meta>Lối tắt mở màn sẵn sàng nhận cuốc</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>

        <button type="button" class="driver-account-menu__item" data-driver-tab="invite">
            <span class="driver-account-menu__copy">
                <strong>Mời bạn bè</strong>
                <span class="driver-account-menu__hint">QR giới thiệu / hoa hồng</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>

        <button type="button" class="driver-account-menu__item" data-driver-tab="customers">
            <span class="driver-account-menu__copy">
                <strong>Khách của tôi</strong>
                <span class="driver-account-menu__hint">Danh sách khách đã đi</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>

        <a class="driver-account-menu__item" href="{{ $registerUrl }}">
            <span class="driver-account-menu__copy">
                <strong>Đăng ký tài xế</strong>
                <span class="driver-account-menu__hint">Tạo hồ sơ tài xế mới</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <button type="button" class="driver-account-menu__item" data-driver-tab="settings">
            <span class="driver-account-menu__copy">
                <strong data-i18n="settings_title">Cài đặt</strong>
                <span class="driver-account-menu__hint">Ngôn ngữ, âm thanh thông báo</span>
            </span>
            <span class="driver-account-menu__chevron" aria-hidden="true">›</span>
        </button>
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="driver-account-logout mt-3">
        @csrf
        <button type="submit" class="driver-account-logout__btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <path d="M16 17l5-5-5-5"/>
                <path d="M21 12H9"/>
            </svg>
            Đăng xuất
        </button>
    </form>
</section>
