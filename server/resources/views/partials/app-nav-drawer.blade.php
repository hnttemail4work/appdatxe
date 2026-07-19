@php
    $drawerId = 'appNavDrawer';
@endphp
<div class="offcanvas offcanvas-end app-nav-drawer" tabindex="-1" id="{{ $drawerId }}" aria-label="Menu điều hướng">
    <div class="offcanvas-body">
        <button type="button" class="btn-close btn-close-white app-nav-drawer-close" data-bs-dismiss="offcanvas" aria-label="Đóng menu"></button>
        @if($minimalNav ?? false)
            @if(auth()->user()->role === 'admin')
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
            @auth
                @if(auth()->user()->role === 'customer')
                    <a href="{{ route('customer.account') }}" class="app-nav-drawer-link {{ request()->routeIs('customer.account') ? 'is-active' : '' }}">Tài khoản</a>
                    <div class="app-nav-drawer-footer mt-4">
                        @include('partials.logout-button', ['class' => 'app-nav-drawer-link app-nav-drawer-link--logout w-100 text-start'])
                    </div>
                @else
                    <div class="app-nav-drawer-footer mt-4">
                        @include('partials.logout-button', ['class' => 'app-nav-drawer-link app-nav-drawer-link--logout w-100 text-start'])
                    </div>
                @endif
            @else
                @if(! request()->routeIs('login'))
                    <a href="{{ route('login') }}" class="app-nav-drawer-link {{ request()->routeIs('login') ? 'is-active' : '' }}">Đăng nhập</a>
                @endif
                @if(! request()->routeIs('customer.register'))
                    <a href="{{ route('customer.register') }}" class="app-nav-drawer-link {{ request()->routeIs('customer.register') ? 'is-active' : '' }}">Đăng ký khách</a>
                @endif
                @if(! request()->routeIs('driver.register', 'register'))
                    <a href="{{ route('driver.register') }}" class="app-nav-drawer-link {{ request()->routeIs('driver.register', 'register') ? 'is-active' : '' }}">Đăng ký tài xế</a>
                @endif
                @if(! request()->routeIs('driver.login', 'login'))
                    <a href="{{ route('driver.login') }}" class="app-nav-drawer-link {{ request()->routeIs('driver.login') ? 'is-active' : '' }}">Đăng nhập tài xế</a>
                @endif
                <a href="{{ route('about') }}" class="app-nav-drawer-link {{ request()->routeIs('about') ? 'is-active' : '' }}">Giới thiệu</a>
            @endauth
        @endif
    </div>
</div>
