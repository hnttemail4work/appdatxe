@php
    $drawerId = 'appNavDrawer';
@endphp
<div class="offcanvas offcanvas-end app-nav-drawer" tabindex="-1" id="{{ $drawerId }}" aria-label="Menu điều hướng">
    <div class="offcanvas-body">
        <button type="button" class="btn-close btn-close-white app-nav-drawer-close" data-bs-dismiss="offcanvas" aria-label="Đóng menu"></button>
        @if($minimalNav ?? false)
            @if(auth()->user()->role === 'operator')
                <div class="app-nav-drawer-user mb-3">
                    <div class="app-nav-drawer-user-role">Quản lý</div>
                    <div class="app-nav-drawer-user-name">{{ auth()->user()->name }}</div>
                </div>
                @if(request()->routeIs('operator.tripOffers.*'))
                    <a href="{{ route('operator.dashboard') }}" class="app-nav-drawer-link">← Trang quản lý</a>
                @else
                    <a href="{{ route('operator.tripOffers.create') }}" class="app-nav-drawer-link">Tạo chuyến</a>
                @endif
            @elseif(auth()->user()->role === 'admin')
                <div class="app-nav-drawer-user mb-3">
                    <div class="app-nav-drawer-user-role">Quản trị</div>
                    <div class="app-nav-drawer-user-name">{{ auth()->user()->name }}</div>
                </div>
            @else
                <div class="app-nav-drawer-user mb-3">
                    <div class="app-nav-drawer-user-name">{{ auth()->user()->name }}</div>
                </div>
                @include('partials.driver-emergency-call')
            @endif
            <div class="app-nav-drawer-footer mt-4">
                @include('partials.logout-button', ['class' => 'app-nav-drawer-link app-nav-drawer-link--logout w-100 text-start'])
            </div>
        @else
            <a href="{{ route('home') }}" class="app-nav-drawer-link {{ request()->routeIs('home') ? 'is-active' : '' }}">Đặt vé</a>
            <a href="{{ route('login') }}" class="app-nav-drawer-link {{ request()->routeIs('login') ? 'is-active' : '' }}">Đăng nhập</a>
        @endif
    </div>
</div>
