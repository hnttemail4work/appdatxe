<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
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
    @stack('styles')
</head>
<body class="app-shell @if(request()->routeIs('login', 'register')) app-shell--auth @endif @if(request()->routeIs('home', 'driver.dashboard', 'guest.orders')) app-shell--mobile-app @endif">
@php
    $isGuestBookingPage = request()->routeIs('home', 'guest.orders');
    $hidePublicNav = $isGuestBookingPage || request()->routeIs('admin.*');
    $minimalNav = auth()->check()
        && in_array(auth()->user()->role, ['operator', 'admin', 'driver'], true)
        && ! $isGuestBookingPage;
    $brandHref = route('home');
    if (auth()->check() && in_array(auth()->user()->role, ['operator', 'driver'], true)) {
        $brandHref = \App\Support\RoleDashboard::route(auth()->user()->role);
    }
@endphp
<nav class="navbar navbar-expand-lg app-navbar navbar-dark">
    <div class="container app-navbar-inner">
        <a class="navbar-brand app-brand-link" href="{{ $brandHref }}">
            <span class="app-brand-mark" aria-hidden="true">TL</span>
            <span class="app-brand-text">Limo</span>
        </a>
        @if($minimalNav)
        <div class="app-navbar-desktop d-none d-lg-flex ms-auto align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
            @if(auth()->user()->role === 'operator')
                <div class="navbar-console-user text-end">
                    <div class="navbar-console-role">Quản lý</div>
                    <div class="navbar-console-name">{{ auth()->user()->name }}</div>
                </div>
                @if(request()->routeIs('operator.tripOffers.*'))
                    <a href="{{ route('operator.dashboard') }}" class="btn btn-sm btn-outline-primary">Quản lý</a>
                @else
                    <a href="{{ route('operator.tripOffers.create') }}" class="btn btn-sm btn-outline-primary">Tạo chuyến</a>
                @endif
            @elseif(auth()->user()->role === 'admin')
                <div class="navbar-console-user text-end">
                    <div class="navbar-console-role">Quản trị</div>
                    <div class="navbar-console-name">{{ auth()->user()->name }}</div>
                </div>
            @else
                @include('partials.driver-emergency-call')
            @endif
            @include('partials.logout-button')
        </div>
        <div class="app-navbar-mobile d-flex d-lg-none ms-auto align-items-center gap-2">
            @if(auth()->user()->role === 'operator')
                @if(request()->routeIs('operator.tripOffers.*'))
                    <a href="{{ route('operator.dashboard') }}" class="btn btn-sm btn-outline-primary">Quản lý</a>
                @else
                    <a href="{{ route('operator.tripOffers.create') }}" class="btn btn-sm btn-outline-primary">Tạo chuyến</a>
                @endif
            @elseif(auth()->user()->role === 'driver')
                @include('partials.driver-emergency-call')
            @endif
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
                    @if(request()->routeIs('register'))
                    <li class="nav-item ms-1">
                        <a class="btn btn-sm btn-primary" href="{{ route('login') }}">Đăng nhập</a>
                    </li>
                    @elseif(! request()->routeIs('login'))
                    <li class="nav-item ms-1">
                        <a class="btn btn-sm btn-primary" href="{{ route('register') }}">Đăng ký tài xế</a>
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
        @endphp
        @if($showMobileDrawer)
            <button type="button"
                    class="app-nav-drawer-trigger d-lg-none ms-auto"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#appNavDrawer"
                    aria-controls="appNavDrawer"
                    aria-label="Mở menu">
                <span class="app-nav-drawer-bars" aria-hidden="true"><i></i><i></i><i></i></span>
            </button>
        @endif
    </div>
</nav>
@if($showMobileDrawer ?? false)
    @include('partials.app-nav-drawer')
@endif

<main class="app-main">
<div class="container py-4">
    @include('partials.alerts')
    @yield('content')
</div>
</main>

<footer class="app-footer bg-dark text-secondary border-top">
    <div class="container">
        <div class="row g-2 app-footer-grid">
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-1">{{ config('app.name') }}</h6>
                <p class="small mb-0 app-footer-text">Nền tảng đặt vé xe khách liên tỉnh cao cấp.</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-1">Liên hệ</h6>
                <p class="small mb-0 app-footer-text">Tổng đài: {{ config('app.contact_phone') }}</p>
                <p class="small mb-0 app-footer-text">Email: {{ config('app.contact_email') }}</p>
            </div>
        </div>
        <hr class="border-secondary app-footer-divider">
        <p class="small text-center mb-0 app-footer-copy">© Bản quyền thuộc về {{ config('app.name') }}.</p>
    </div>
</footer>

@stack('modals')

@include('partials.app-dialog')
@include('partials.cancellation-reason-modal')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

    fetch('/csrf-token', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { syncCsrfToken(data && data.token); })
        .catch(function () {});

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
</body>
</html>
