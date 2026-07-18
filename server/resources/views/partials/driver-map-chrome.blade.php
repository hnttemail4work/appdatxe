@php
    $dayEarnings = number_format($revenueStats['day'] ?? 0, 0, ',', '.') . ' đ';
    $heroStatus = $heroStatus ?? ['key' => 'offline', 'label' => ''];
@endphp
<header class="driver-map-chrome" aria-label="Thanh điều khiển">
    <button type="button" class="driver-map-chrome__btn" id="driver-drawer-open" aria-label="Mở menu" aria-controls="driver-drawer" aria-expanded="false">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
    </button>

    <button type="button"
            class="driver-map-chrome__earnings"
            id="driver-earnings-chip"
            data-driver-tab="earnings"
            aria-label="Thu nhập hôm nay, mở thu nhập">
        <span class="driver-map-chrome__earnings-label">Hôm nay</span>
        <strong class="driver-map-chrome__earnings-value">{{ $dayEarnings }}</strong>
    </button>

    @php $bellUnread = (int) (($inboxUnread['total'] ?? 0)); @endphp
    <button type="button"
            class="driver-map-chrome__btn driver-map-chrome__bell"
            id="driver-inbox-open"
            data-driver-tab="inbox"
            aria-label="Hộp thư{{ $bellUnread > 0 ? ', ' . $bellUnread . ' chưa đọc' : '' }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        @if($bellUnread > 0 || $profile->isMissedTripLocked() || ($walletBlockReason ?? null))
            <span class="driver-map-chrome__bell-badge" data-inbox-bell-badge @if($bellUnread < 1) hidden @endif>{{ $bellUnread > 99 ? '99+' : $bellUnread }}</span>
            @if($bellUnread < 1)
                <span class="driver-map-chrome__bell-dot" aria-hidden="true"></span>
            @endif
        @endif
    </button>
</header>
