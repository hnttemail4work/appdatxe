<nav class="customer-scroll-dock customer-scroll-dock--three-tabs" aria-label="Điều hướng nhanh">
    <a href="{{ route('home') }}"
       class="customer-scroll-dock-item {{ request()->routeIs('home') ? 'is-active' : '' }}"
       aria-label="Trang chủ"
       title="Trang chủ"
       @if(request()->routeIs('home')) aria-current="page" @endif>
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
        </span>
    </a>
    <a href="{{ route('booking.trips') }}"
       class="customer-scroll-dock-item {{ request()->routeIs('booking.trips') ? 'is-active' : '' }}"
       aria-label="Xem chuyến"
       title="Xem chuyến"
       @if(request()->routeIs('booking.trips')) aria-current="page" @endif>
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
        </span>
    </a>
    @auth
        @if(auth()->user()->role === 'customer')
        <a href="{{ route('customer.account') }}"
           class="customer-scroll-dock-item {{ request()->routeIs('customer.account') ? 'is-active' : '' }}"
           aria-label="Tài khoản"
           title="Tài khoản"
           @if(request()->routeIs('customer.account')) aria-current="page" @endif>
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </span>
        </a>
        @else
        <a href="{{ \App\Support\RoleDashboard::route(auth()->user()->role) }}"
           class="customer-scroll-dock-item"
           aria-label="Bảng điều khiển"
           title="Bảng điều khiển">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </span>
        </a>
        @endif
    @else
        <a href="{{ route('login') }}"
           class="customer-scroll-dock-item {{ request()->routeIs('login', 'customer.register', 'auth.biometric') ? 'is-active' : '' }}"
           aria-label="Đăng nhập"
           title="Đăng nhập">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </span>
        </a>
    @endauth
</nav>
