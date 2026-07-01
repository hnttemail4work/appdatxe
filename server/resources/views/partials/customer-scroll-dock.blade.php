@php
    /** @var string $customerDockMode home|orders */
    $customerDockMode = $customerDockMode ?? 'home';
    $guestShowTrackTab = $guestShowTrackTab ?? true;
    $guestOrdersUrl = route('guest.orders');
    $homeUrl = route('home');
@endphp
<nav class="customer-scroll-dock customer-scroll-dock--three-tabs" aria-label="Điều hướng nhanh">
    @if($customerDockMode === 'orders')
        <a href="{{ $homeUrl }}" class="customer-scroll-dock-item" aria-label="Tìm chuyến">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Tìm</span>
        </a>
        <a href="{{ $homeUrl }}#booking-results-main" class="customer-scroll-dock-item customer-scroll-dock-item--primary" aria-label="Danh sách chuyến có thể đặt">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Chuyến</span>
        </a>
        <span class="customer-scroll-dock-item customer-scroll-dock-item--track is-active" aria-current="page" aria-label="Đơn đặt của bạn">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4h12a2 2 0 0 1 2 2v1H4V6a2 2 0 0 1 2-2z"/><path d="M4 9h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9z"/><path d="M8 13h8"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Đơn đặt</span>
            @if(($guestActiveOrdersCount ?? 0) > 0)
                <span class="customer-scroll-dock-badge is-hot">{{ $guestActiveOrdersCount }}</span>
            @endif
        </span>
    @else
        <button type="button" class="customer-scroll-dock-item is-active" data-scroll-target="booking-search-block" aria-label="Tìm chuyến">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Tìm</span>
        </button>
        <button type="button" class="customer-scroll-dock-item customer-scroll-dock-item--primary" data-scroll-target="booking-results-main" aria-label="Danh sách chuyến có thể đặt">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Chuyến</span>
        </button>
        <a href="{{ $guestOrdersUrl }}"
           class="customer-scroll-dock-item customer-scroll-dock-item--track"
           aria-label="Đơn đặt của bạn">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4h12a2 2 0 0 1 2 2v1H4V6a2 2 0 0 1 2-2z"/><path d="M4 9h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9z"/><path d="M8 13h8"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Đơn đặt</span>
            <span class="customer-scroll-dock-badge d-none" data-scroll-dock-badge="track"></span>
        </a>
    @endif
</nav>
