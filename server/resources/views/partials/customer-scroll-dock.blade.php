@php
    $isCustomer = auth()->check() && auth()->user()->role === 'customer';
    $inboxUnreadTotal = 0;
    if ($isCustomer) {
        $inboxUnreadTotal = app(\App\Services\CustomerInboxService::class)->unreadCount((int) auth()->id());
    }
    $dockTabs = $isCustomer ? 4 : 3;
@endphp
<nav class="customer-scroll-dock"
     style="--customer-dock-tabs: {{ $dockTabs }};"
     aria-label="Menu khách"
     @if($isCustomer) data-inbox-unread="{{ (int) $inboxUnreadTotal }}" @endif>
    <a href="{{ route('home') }}"
       class="customer-scroll-dock-item {{ request()->routeIs('home') ? 'is-active' : '' }}"
       aria-label="Trang chủ"
       title="Trang chủ"
       @if(request()->routeIs('home')) aria-current="page" @endif>
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
        </span>
        <span class="customer-scroll-dock-label">Trang chủ</span>
    </a>

    <a href="{{ route('booking.trips') }}"
       class="customer-scroll-dock-item {{ request()->routeIs('booking.trips') ? 'is-active' : '' }}"
       aria-label="Chuyến"
       title="Chuyến"
       @if(request()->routeIs('booking.trips')) aria-current="page" @endif>
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
        </span>
        <span class="customer-scroll-dock-label">Chuyến</span>
    </a>

    @if($isCustomer)
        <a href="{{ route('customer.account', ['tab' => 'inbox']) }}"
           class="customer-scroll-dock-item {{ request()->routeIs('customer.account') && request('tab') === 'inbox' ? 'is-active' : '' }}"
           aria-label="Hộp thư"
           title="Hộp thư"
           data-inbox-unread="{{ (int) $inboxUnreadTotal }}">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Hộp thư</span>
            @if($inboxUnreadTotal > 0)
                <span class="customer-scroll-dock-badge">{{ $inboxUnreadTotal > 99 ? '99+' : $inboxUnreadTotal }}</span>
            @endif
        </a>
    @endif

    @auth
        @if(auth()->user()->role === 'customer')
        <a href="{{ route('customer.account', ['tab' => 'account']) }}"
           class="customer-scroll-dock-item {{ request()->routeIs('customer.account') && request('tab', 'account') !== 'inbox' ? 'is-active' : '' }}"
           aria-label="Tài khoản"
           title="Tài khoản"
           @if(request()->routeIs('customer.account') && request('tab', 'account') !== 'inbox') aria-current="page" @endif>
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Tài khoản</span>
        </a>
        @else
        <a href="{{ \App\Support\RoleDashboard::route(auth()->user()->role) }}"
           class="customer-scroll-dock-item"
           aria-label="Bảng điều khiển"
           title="Bảng điều khiển">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Menu</span>
        </a>
        @endif
    @else
        <a href="{{ route('login') }}"
           class="customer-scroll-dock-item {{ request()->routeIs('login', 'customer.register') ? 'is-active' : '' }}"
           aria-label="Đăng nhập"
           title="Đăng nhập">
            <span class="customer-scroll-dock-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/></svg>
            </span>
            <span class="customer-scroll-dock-label">Đăng nhập</span>
        </a>
    @endauth
</nav>
