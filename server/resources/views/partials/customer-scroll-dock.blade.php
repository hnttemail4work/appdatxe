<nav class="customer-scroll-dock customer-scroll-dock--two-tabs" aria-label="Điều hướng nhanh">
    <a href="{{ route('home') }}"
       class="customer-scroll-dock-item {{ request()->routeIs('home') ? 'is-active' : '' }}"
       aria-label="Trang chủ"
       @if(request()->routeIs('home')) aria-current="page" @endif>
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
        </span>
        <span class="customer-scroll-dock-label">Trang chủ</span>
    </a>
    <a href="{{ route('booking.trips') }}"
       class="customer-scroll-dock-item {{ request()->routeIs('booking.trips') ? 'is-active' : '' }}"
       aria-label="Xem chuyến"
       @if(request()->routeIs('booking.trips')) aria-current="page" @endif>
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
        </span>
        <span class="customer-scroll-dock-label">Xem chuyến</span>
    </a>
</nav>
