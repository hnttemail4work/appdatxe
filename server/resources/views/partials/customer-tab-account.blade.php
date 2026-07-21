@php
    $pendingChange = $pendingChange ?? null;
    $user = $user ?? auth()->user();
    $profile = $profile ?? [];
    $wallet = $wallet ?? null;
    $balance = (int) ($wallet?->balance ?? 0);
    $walletBalanceLabel = number_format($balance, 0, ',', '.');

    $displayName = trim((string) ($profile['name'] ?? ($user?->preferredDisplayName() ?? '')));
    $phone = (string) ($profile['phone'] ?? ($user?->phone ?? ''));
    if ($displayName === '' || $displayName === $phone || preg_match('/^[\d\s.+()-]+$/', $displayName)) {
        $displayName = $phone !== '' ? $phone : 'Khách hàng';
    }
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
@endphp
<section class="customer-account-panel is-active" aria-label="Tài khoản">
    <div class="customer-account-identity mb-3">
        <div class="customer-account-identity__avatar" aria-hidden="true">
            <span>{{ $initial }}</span>
        </div>
        <div class="customer-account-identity__meta">
            <strong class="customer-account-identity__name">{{ $displayName }}</strong>
            <span class="customer-account-identity__phone">{{ $phone !== '' ? $phone : '—' }}</span>
        </div>
        <a href="{{ route('customer.account', ['tab' => 'wallet']) }}"
           class="customer-account-identity__wallet"
           aria-label="Mở ví, số dư {{ $walletBalanceLabel }} đ">
            <span class="customer-account-identity__wallet-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/>
                    <path d="M16 7V5a2 2 0 0 0-2-2H6"/>
                    <circle cx="17" cy="13" r="1.25" fill="currentColor" stroke="none"/>
                </svg>
            </span>
            <span class="customer-account-identity__wallet-balance">{{ $walletBalanceLabel }} <small>đ</small></span>
        </a>
    </div>

    <nav class="customer-account-menu" aria-label="Mục tài khoản">
        <a href="{{ route('customer.account', ['tab' => 'profile']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Hồ sơ khách</strong>
                <span class="customer-account-menu__hint">
                    Thông tin & giấy tờ
                    @if($pendingChange)
                        · Đang chờ duyệt
                    @endif
                </span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <a href="{{ route('customer.account', ['tab' => 'trips']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Lịch sử chuyến</strong>
                <span class="customer-account-menu__hint">Các chuyến đã hoàn thành</span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <a href="{{ route('customer.account', ['tab' => 'settings']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Cài đặt</strong>
                <span class="customer-account-menu__hint">Âm thanh, ngôn ngữ</span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>
    </nav>

    <div class="customer-account-card mt-3">
        @include('partials.logout-button', ['class' => 'btn btn-outline-danger w-100 btn-logout'])
    </div>
</section>
