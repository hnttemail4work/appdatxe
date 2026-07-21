@php
    $pendingDocs = $pendingChangeRequest ?? null;
    $user = $user ?? auth()->user();
    $profile = $profile ?? null;
    $driverWallet = $driverWallet ?? null;
    $heroStatus = $heroStatus ?? ['key' => 'offline', 'label' => 'Tạm nghỉ'];

    $driverInitial = $user ? mb_strtoupper(mb_substr($user->preferredDisplayName(), 0, 1)) : '?';
    $driverPhotoUrl = $profile?->photoUrl('photo_portrait');
    $walletBalanceLabel = $driverWallet
        ? number_format($driverWallet->balance, 0, ',', '.')
        : '—';
    $driverLikeCount = $profile?->likeCount() ?? 0;
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
            <span class="driver-account-identity__likes" aria-label="{{ $driverLikeCount }} lượt thích">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/>
                    <path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                </svg>
                {{ number_format($driverLikeCount) }}
            </span>
        </div>
        <button type="button"
                class="driver-account-identity__wallet"
                data-driver-tab="wallet"
                aria-label="Mở ví tài xế, số dư {{ $walletBalanceLabel }} đ">
            <span class="driver-account-identity__wallet-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/>
                    <path d="M16 7V5a2 2 0 0 0-2-2H6"/>
                    <circle cx="17" cy="13" r="1.25" fill="currentColor" stroke="none"/>
                </svg>
            </span>
            <span class="driver-account-identity__wallet-balance">{{ $walletBalanceLabel }} <small>đ</small></span>
        </button>
    </div>

    <nav class="driver-account-menu" aria-label="Mục tài khoản">
        <button type="button" class="driver-account-menu__item" data-driver-tab="account-update">
            <span class="driver-account-menu__copy">
                <strong data-i18n="account_update">Hồ sơ tài xế</strong>
                <span class="driver-account-menu__hint">
                    <span data-i18n="account_update_menu_hint">Thông tin, giấy tờ xe & CCCD</span>
                    @if($pendingDocs)
                        · Đang chờ duyệt
                    @endif
                </span>
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

        <button type="button" class="driver-account-menu__item" data-driver-tab="settings">
            <span class="driver-account-menu__copy">
                <strong data-i18n="settings_title">Cài đặt</strong>
                <span class="driver-account-menu__hint">Âm thanh, ngôn ngữ</span>
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
