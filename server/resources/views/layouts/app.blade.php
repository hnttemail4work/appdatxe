<!DOCTYPE html>
@php
    use App\Support\AppBrandingSettings;
    use App\Support\PushAudience;

    $appBrandName = AppBrandingSettings::appName();
    $appBrandTitle = AppBrandingSettings::brandTitle();
    $appBrandTagline = AppBrandingSettings::brandTagline();
    $appIconUrl = AppBrandingSettings::appIconAssetUrl();
    $appIconMime = AppBrandingSettings::appIconMimeType();
    $pwaEnabled = PushAudience::enabledFor(auth()->user());
    $pwaAudience = $pwaEnabled ? PushAudience::resolve(auth()->user()) : null;
    $pwaAudienceLabel = $pwaAudience ? PushAudience::shortLabel($pwaAudience) : '';
    $pwaInstallTitle = match ($pwaAudience) {
        PushAudience::DRIVER => 'Ghim app Tài xế',
        default => 'Ghim app Đặt xe',
    };
    $pwaInstallHint = match ($pwaAudience) {
        PushAudience::DRIVER => 'Mở nhanh bảng chuyến và nhận cuốc mới.',
        default => 'Mở nhanh trang đặt xe và nhận thông báo chuyến.',
    };
    $isGuestBookingPage = request()->routeIs('home', 'booking.trips');
    $hidePublicNav = $isGuestBookingPage || request()->routeIs('about') || request()->routeIs('admin.*');
    $minimalNav = auth()->check()
        && in_array(auth()->user()->role, ['admin', 'driver'], true)
        && ! $isGuestBookingPage;
    $brandHref = route('home');
    if (auth()->check() && auth()->user()->role === 'driver') {
        $brandHref = \App\Support\RoleDashboard::route('driver');
    }
@endphp
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(request()->routeIs('home', 'about', 'booking.trips', 'driver.dashboard', 'register', 'login'))
    <script src="{{ asset('js/mobile-app-chrome.js') }}?v={{ filemtime(public_path('js/mobile-app-chrome.js')) }}"></script>
    @endif
    <title>{{ $appBrandName }}</title>
    @if($pwaEnabled)
    <meta name="theme-color" content="#0f1419">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $pwaAudienceLabel }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    @endif
    <link rel="icon" type="{{ $appIconMime }}" href="{{ $appIconUrl }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ AppBrandingSettings::appleTouchIconAssetUrl() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-theme.css') }}?v={{ filemtime(public_path('css/app-theme.css')) }}">
    <style>
        .card { border-radius: 1rem; border: none; }
        .card-title-bar { border-left: 4px solid #d4af37; padding-left: .75rem; }
.share-booking-btn-icon {
    width: 2.25rem;
    height: 2.25rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
        .app-flash { position: relative; padding-right: 2.75rem; transition: opacity .25s ease, transform .25s ease; }
        .app-flash.is-hiding { opacity: 0; transform: translateY(-6px); pointer-events: none; }
        .app-flash-close {
            position: absolute;
            top: .65rem;
            right: .65rem;
            width: 1.75rem;
            height: 1.75rem;
            border: 0;
            border-radius: 50%;
            background: rgba(15, 23, 42, .08);
            color: inherit;
            font-size: 1.15rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .app-flash-close:hover { background: rgba(15, 23, 42, .14); }
        .booking-flash .app-flash-close { background: rgba(212, 175, 55, .15); }
        .booking-flash .app-flash-close:hover { background: rgba(212, 175, 55, .28); }
        .booking-flash-error .app-flash-close { background: rgba(248, 113, 113, .15); }
        .booking-flash-error .app-flash-close:hover { background: rgba(248, 113, 113, .28); }
    </style>
    <link rel="stylesheet" href="{{ asset('css/app-status.css') }}?v={{ filemtime(public_path('css/app-status.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/screen-tabs.css') }}?v={{ filemtime(public_path('css/screen-tabs.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}?v={{ filemtime(public_path('css/app-layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/app-dialog.css') }}?v={{ filemtime(public_path('css/app-dialog.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/app-flash.css') }}?v={{ filemtime(public_path('css/app-flash.css')) }}">
    @if($pwaEnabled)
    <link rel="stylesheet" href="{{ asset('css/pwa-install.css') }}?v={{ filemtime(public_path('css/pwa-install.css')) }}">
    @endif
    @if($pwaEnabled)
    <script>
    window.__pwaDeferredInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        window.__pwaDeferredInstallPrompt = e;
        window.dispatchEvent(new Event('pwa-install-available'));
    });
    </script>
    @endif
    @stack('styles')
</head>
<body class="app-shell @if(request()->routeIs('login', 'register')) app-shell--auth @endif @if(request()->routeIs('home', 'about', 'booking.trips', 'driver.dashboard', 'register', 'login')) app-shell--mobile-app @endif">
<nav class="navbar navbar-expand-lg app-navbar navbar-dark">
    <div class="container app-navbar-inner">
        <a class="navbar-brand app-brand-link app-brand-link--stacked" href="{{ $brandHref }}" aria-label="{{ $appBrandName }}">
            <span class="app-brand-stack">
                <span class="app-brand-title">{{ $appBrandTitle }}</span>
                @if(! request()->routeIs('home'))
                <span class="app-brand-tagline">{{ $appBrandTagline }}</span>
                @endif
            </span>
        </a>
        @if($minimalNav)
        <div class="app-navbar-desktop d-none d-lg-flex ms-auto align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
            @if(auth()->user()->role === 'admin')
                <div class="navbar-console-user text-end">
                    <div class="navbar-console-role">Quản trị</div>
                    <div class="navbar-console-name">{{ auth()->user()->name }}</div>
                </div>
            @endif
            @include('partials.logout-button')
        </div>
        <div class="app-navbar-mobile d-flex d-lg-none ms-auto align-items-center gap-2">
            @include('partials.logout-button')
        </div>
        @elseif(! $hidePublicNav)
        <div class="app-navbar-desktop d-none d-lg-flex ms-auto align-items-center gap-1">
            <ul class="navbar-nav flex-row align-items-center gap-1">
                @auth
                    <li class="nav-item">
                        <span class="nav-link text-muted small pe-0">{{ auth()->user()->name }}</span>
                    </li>
                    <li class="nav-item ms-1">
                        @include('partials.logout-button')
                    </li>
                @else
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-primary {{ request()->routeIs('home') ? 'active' : '' }}"
                           href="{{ route('home') }}">Đặt vé</a>
                    </li>
                    @if(! request()->routeIs('login'))
                    <li class="nav-item ms-1">
                        <a class="btn btn-sm btn-outline-primary {{ request()->routeIs('login') ? 'active' : '' }}"
                           href="{{ route('login') }}">Đăng nhập</a>
                    </li>
                    @endif
                    @if(! request()->routeIs('register'))
                    <li class="nav-item ms-1">
                        <a class="btn btn-sm btn-outline-primary {{ request()->routeIs('register') ? 'active' : '' }}"
                           href="{{ route('register') }}">Đăng ký tài xế</a>
                    </li>
                    @endif
                @endauth
            </ul>
        </div>
        @auth
        <div class="app-navbar-mobile d-flex d-lg-none ms-auto align-items-center gap-2">
            @include('partials.logout-button')
        </div>
        @endauth
        @endif
        @php
            $showMobileDrawer = ! auth()->check();
            $showNavDrawerOnDesktop = $showMobileDrawer && $hidePublicNav;
        @endphp
        @if($showMobileDrawer)
            <button type="button"
                    class="app-nav-drawer-trigger ms-auto @unless($showNavDrawerOnDesktop) d-lg-none @endunless"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#appNavDrawer"
                    aria-controls="appNavDrawer"
                    aria-label="Mở menu">
                <span class="app-nav-drawer-bars" aria-hidden="true"><i></i><i></i><i></i></span>
            </button>
        @endif
    </div>
</nav>
@if($pwaEnabled)
@include('partials.pwa-install-prompt')
@endif
@if($showMobileDrawer ?? false)
    @include('partials.app-nav-drawer')
@endif

<main class="app-main">
<div class="container py-4">
    @include('partials.alerts')
    <div id="app-flash-stack" class="app-flash-stack" aria-live="polite" aria-atomic="true"></div>
    @yield('content')
</div>
</main>

@if(request()->routeIs('home', 'booking.trips', 'about'))
    @include('partials.customer-scroll-dock')
@endif

<footer class="app-footer bg-dark text-secondary border-top">
    <div class="container">
        <div class="row g-2 app-footer-grid">
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-1">{{ $appBrandName }}</h6>
                <p class="small mb-0 app-footer-text">Nền tảng đặt vé xe khách liên tỉnh cao cấp.</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-1">Liên hệ</h6>
                <p class="small mb-0 app-footer-text">Tổng đài: {{ config('app.contact_phone') }}</p>
                <p class="small mb-0 app-footer-text">Email: {{ config('app.contact_email') }}</p>
            </div>
        </div>
        <hr class="border-secondary app-footer-divider">
        <p class="small text-center mb-0 app-footer-copy">© Bản quyền thuộc về {{ $appBrandName }}.</p>
    </div>
</footer>

@stack('modals')

@include('partials.app-dialog')
@include('partials.cancellation-reason-modal')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app-flash.js') }}?v={{ filemtime(public_path('js/app-flash.js')) }}"></script>
<script src="{{ asset('js/app-dialog.js') }}"></script>
<script>window.__cancellationReasonsUrl = @json(route('cancellationReasons.index'));</script>
<script src="{{ asset('js/cancellation-reason-modal.js') }}"></script>
<script>
(function () {
    function syncCsrfToken(token) {
        if (!token) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });
    }

    document.querySelectorAll('.logout-form').forEach(function (logoutForm) {
        logoutForm.addEventListener('submit', function (e) {
            if (logoutForm.dataset.logoutSubmitting === '1') {
                logoutForm.dataset.logoutSubmitting = '';
                return;
            }

            e.preventDefault();

            function submitLogout() {
                fetch('/csrf-token', { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        syncCsrfToken(data.token);
                        logoutForm.dataset.logoutSubmitting = '1';
                        if (typeof logoutForm.requestSubmit === 'function') {
                            logoutForm.requestSubmit();
                        } else {
                            logoutForm.submit();
                        }
                    })
                    .catch(function () {
                        logoutForm.dataset.logoutSubmitting = '1';
                        logoutForm.submit();
                    });
            }

            if (window.AppDialog) {
                window.AppDialog.confirm({
                    title: 'Đăng xuất',
                    message: 'Bạn có chắc muốn đăng xuất?',
                    confirmText: 'Đăng xuất',
                    cancelText: 'Huỷ',
                    variant: 'danger',
                }).then(function (ok) {
                    if (ok) submitLogout();
                });
            } else if (window.confirm('Bạn có chắc muốn đăng xuất?')) {
                submitLogout();
            }
        });
    });

    document.querySelectorAll('.app-flash').forEach(function (flash) {
        var hideTimer = null;

        function dismissFlash() {
            if (flash.classList.contains('is-hiding')) return;
            flash.classList.add('is-hiding');
            window.setTimeout(function () {
                if (flash.parentNode) flash.parentNode.removeChild(flash);
            }, 260);
        }

        var closeBtn = flash.querySelector('[data-flash-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (hideTimer) window.clearTimeout(hideTimer);
                dismissFlash();
            });
        }

        var autoMs = parseInt(flash.getAttribute('data-auto-dismiss') || '0', 10);
        if (autoMs > 0) {
            hideTimer = window.setTimeout(dismissFlash, autoMs);
        }
    });
})();
</script>
<script src="{{ asset('js/form-field-validation.js') }}"></script>
<script src="{{ asset('js/screen-tabs.js') }}"></script>
<script>
(function () {
    var drawer = document.getElementById('appNavDrawer');
    if (drawer) {
        drawer.querySelectorAll('a.app-nav-drawer-link[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                var instance = bootstrap.Offcanvas.getInstance(drawer);
                if (instance) instance.hide();
            });
        });
    }
})();
</script>
@stack('scripts')
@if($pwaEnabled)
<script>
window.__pwaConfig = {
    audience: @json($pwaAudience),
    audienceLabel: @json($pwaAudienceLabel),
    startUrl: @json(PushAudience::startUrl($pwaAudience)),
};
</script>
<script src="{{ asset('js/pwa-client.js') }}?v={{ filemtime(public_path('js/pwa-client.js')) }}"></script>
@endif
</body>
</html>
