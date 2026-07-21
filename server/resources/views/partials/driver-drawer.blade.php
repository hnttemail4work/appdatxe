@php
    $driverInitial = mb_strtoupper(mb_substr($user->preferredDisplayName(), 0, 1));
    $driverPhotoUrl = $profile->photoUrl('photo_portrait');
    $walletBalanceLabel = $driverWallet
        ? number_format($driverWallet->balance, 0, ',', '.') . ' đ'
        : '—';
    $driverLikeCount = $profile->likeCount();
    $statusKey = $heroStatus['key'] ?? 'offline';
    $statusLabel = $heroStatus['label'] ?? 'Tạm nghỉ';
@endphp
<div class="driver-drawer-backdrop" id="driver-drawer-backdrop" hidden></div>
<aside class="driver-drawer" id="driver-drawer" hidden aria-hidden="true" aria-label="Menu tài xế">
    <div class="driver-drawer__glow" aria-hidden="true"></div>

    {{-- Giữ id trạng thái ẩn cho JS availability --}}
    <div class="d-none" aria-hidden="true">
        <div id="driver-hero-status-pill" class="driver-drawer__status driver-drawer__status--{{ $statusKey }}">
            <span id="driver-hero-status-label">{{ $statusLabel }}</span>
        </div>
    </div>

    <div class="driver-drawer__head">
        <div class="driver-drawer__identity">
            <div class="driver-avatar driver-drawer__avatar">
                @if($driverPhotoUrl)
                    <img src="{{ $driverPhotoUrl }}" alt="" class="driver-avatar-img" loading="lazy" decoding="async">
                @else
                    <span class="driver-avatar-fallback" aria-hidden="true">{{ $driverInitial }}</span>
                @endif
            </div>
            <div class="driver-drawer__meta">
                <strong class="driver-drawer__name">{{ $user->preferredDisplayName() }}</strong>
                <div class="driver-drawer__chips">
                    <span class="driver-drawer__likes" aria-label="{{ $driverLikeCount }} lượt thích">
                        <svg class="driver-drawer__likes-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/>
                            <path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                        </svg>
                        <span class="driver-drawer__likes-value">{{ number_format($driverLikeCount) }}</span>
                    </span>
                </div>
            </div>
        </div>
        <button type="button" class="driver-drawer__close" id="driver-drawer-close" aria-label="Đóng menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                <path d="M18 6 6 18M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <button type="button" class="driver-drawer__wallet" data-driver-tab="wallet" data-driver-drawer-close>
        <span class="driver-drawer__wallet-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="6" width="20" height="14" rx="2.5"/>
                <path d="M2 10h20"/>
                <circle cx="16.5" cy="15" r="1.4"/>
            </svg>
        </span>
        <span class="driver-drawer__wallet-text">
            <span class="driver-drawer__wallet-label">Ví tài xế</span>
            <strong class="driver-drawer__wallet-value">{{ $walletBalanceLabel }}</strong>
        </span>
        <span class="driver-drawer__chevron" aria-hidden="true">›</span>
    </button>

    <nav class="driver-drawer__nav" aria-label="Chức năng">
        <p class="driver-drawer__nav-label">Chức năng</p>

        <button type="button" class="driver-drawer__link" data-driver-tab="invite" data-driver-drawer-close>
            <span class="driver-drawer__link-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7"/><path d="M12 3v12"/><path d="m8 7 4-4 4 4"/></svg>
            </span>
            <span class="driver-drawer__link-text">Mời bạn bè</span>
            <span class="driver-drawer__chevron" aria-hidden="true">›</span>
        </button>

        <button type="button" class="driver-drawer__link" data-driver-tab="customers" data-driver-drawer-close>
            <span class="driver-drawer__link-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3.5"/><path d="M22 21v-2a3.5 3.5 0 0 0-2.5-3.35"/><path d="M16.5 3.6a3.5 3.5 0 0 1 0 6.8"/></svg>
            </span>
            <span class="driver-drawer__link-text">Khách của tôi</span>
            <span class="driver-drawer__chevron" aria-hidden="true">›</span>
        </button>

        <button type="button" class="driver-drawer__link" data-driver-tab="settings" data-driver-drawer-close>
            <span class="driver-drawer__link-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2.5v2M12 19.5v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2.5 12h2M19.5 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </span>
            <span class="driver-drawer__link-text" data-i18n="settings_title">Cài đặt</span>
            <span class="driver-drawer__chevron" aria-hidden="true">›</span>
        </button>
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="driver-drawer__logout">
        @csrf
        <button type="submit" class="driver-drawer__logout-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <path d="M16 17l5-5-5-5"/>
                <path d="M21 12H9"/>
            </svg>
            Đăng xuất
        </button>
    </form>
</aside>
