<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f4ff; color: #212529; }
        .navbar-brand { font-weight: 800; letter-spacing: -.5px; font-size: 1.3rem; }
        .card { border-radius: 1rem; border: none; }
        .form-control, .form-select {
            background-color: #fff;
            color: #212529;
            border: 1px solid #d0d7e6;
        }
        .form-control:focus, .form-select:focus {
            background-color: #fff;
            color: #212529;
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.18);
        }
        .form-control::placeholder { color: #8898aa; }
        .card-title-bar { border-left: 4px solid #0d6efd; padding-left: .75rem; }
        .nav-link.active-page {
            color: #0d6efd !important;
            font-weight: 600;
            border-bottom: 2px solid #0d6efd;
        }
        .navbar { min-height: 58px; }
.navbar-console-user {
    line-height: 1.2;
}
.navbar-console-role {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
}
.navbar-console-name {
    font-size: .8125rem;
    font-weight: 600;
    color: #0f172a;
}
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
        .booking-flash .app-flash-close { background: rgba(6, 95, 70, .12); }
        .booking-flash .app-flash-close:hover { background: rgba(6, 95, 70, .2); }
        .booking-flash-error .app-flash-close { background: rgba(185, 28, 28, .1); }
        .booking-flash-error .app-flash-close:hover { background: rgba(185, 28, 28, .18); }
    </style>
    <link rel="stylesheet" href="{{ asset('css/screen-tabs.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app-dialog.css') }}">
    @stack('styles')
    @if(auth()->check() && auth()->user()->role === 'operator')
    <link rel="stylesheet" href="{{ asset('css/operator-notifications.css') }}">
    @endif
</head>
<body class="app-shell">
@php
    $isGuestBookingPage = request()->routeIs('booking.*');
    $hidePublicNav = $isGuestBookingPage || request()->routeIs('admin.*');
    $minimalNav = auth()->check()
        && in_array(auth()->user()->role, ['operator', 'admin', 'driver'], true)
        && ! $isGuestBookingPage;
    $bookingShareQrNav = $isGuestBookingPage
        && auth()->check()
        && in_array(auth()->user()->role, ['operator', 'admin', 'driver'], true);
    $navDriverProfile = ($minimalNav && auth()->check() && auth()->user()->role === 'driver')
        ? \App\Models\DriverProfile::query()->where('user_id', auth()->id())->first()
        : null;
    $brandHref = url('/');
    if (auth()->check()) {
        $brandHref = match (auth()->user()->role) {
            'operator' => route('operator.dashboard'),
            'admin'    => route('admin.dashboard'),
            'driver'   => route('driver.dashboard'),
            default    => url('/'),
        };
    } elseif ($hidePublicNav) {
        $brandHref = route('booking.index');
    }
@endphp
<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand text-primary" href="{{ $brandHref }}">{{ config('app.name') }}</a>
        @if($minimalNav)
        <div class="ms-auto d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
            @if(auth()->user()->role === 'operator')
                <div class="navbar-console-user text-end">
                    <div class="navbar-console-role">Quản lý</div>
                    <div class="navbar-console-name">{{ auth()->user()->name }}</div>
                </div>
                @include('partials.share-booking-qr-button', [
                    'shareUrl' => \App\Support\BookingShareUrl::guest(),
                    'shareLabel' => 'QR đặt vé chung',
                    'modalId' => 'shareQrModal-operator-guest',
                    'iconOnly' => true,
                ])
                @include('partials.operator-notifications-bell')
            @elseif(auth()->user()->role === 'admin')
                <div class="navbar-console-user text-end">
                    <div class="navbar-console-role">Quản trị</div>
                    <div class="navbar-console-name">{{ auth()->user()->name }}</div>
                </div>
            @else
                @include('partials.share-booking-qr-button', [
                    'shareUrl' => \App\Support\BookingShareUrl::guest(),
                    'shareLabel' => 'QR đặt vé',
                    'modalId' => 'shareQrModal-driver-guest',
                    'iconOnly' => true,
                ])
                @include('partials.driver-emergency-call')
            @endif
            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">Đăng xuất</button>
            </form>
        </div>
        @elseif($bookingShareQrNav)
        <div class="ms-auto d-flex align-items-center">
            @include('partials.share-booking-qr-button', [
                'shareUrl' => \App\Support\BookingShareUrl::guest(),
                'shareLabel' => 'QR đặt vé chung',
                'modalId' => 'shareQrModal-booking-guest',
                'iconOnly' => true,
            ])
        </div>
        @elseif(! $hidePublicNav)
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Mở menu điều hướng">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-1">
                @auth
                    <li class="nav-item">
                        <span class="nav-link text-muted small pe-0">{{ auth()->user()->name }}</span>
                    </li>
                    <li class="nav-item ms-1">
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">Đăng xuất</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('booking.*') ? 'active-page' : '' }}"
                           href="{{ route('booking.index') }}">Đặt vé</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item ms-1">
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('register') }}">Đăng ký tài xế</a>
                    </li>
                @endauth
            </ul>
        </div>
        @endif
    </div>
</nav>

<main class="app-main">
<div class="container py-4">
    @include('partials.alerts')
    @yield('content')
</div>
</main>

<footer class="app-footer bg-dark text-secondary border-top">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-2">{{ config('app.name') }}</h6>
                <p class="small mb-0">Nền tảng đặt vé xe khách liên tỉnh cao cấp.</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-2">Liên hệ</h6>
                <p class="small mb-1">Tổng đài: {{ config('app.contact_phone') }}</p>
                <p class="small mb-0">Thư điện tử: {{ config('app.contact_email') }}</p>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <p class="small text-center mb-0">© {{ date('Y') }} {{ config('app.name') }}. Bảo lưu mọi quyền.</p>
    </div>
</footer>

@stack('modals')
@if($navDriverProfile ?? null)
    @include('partials.share-booking-qr-modal', [
        'shareUrl' => \App\Support\BookingShareUrl::guest(),
        'shareLabel' => 'QR đặt vé',
        'modalId' => 'shareQrModal-driver-guest',
    ])
@endif
@if($bookingShareQrNav ?? false)
    @include('partials.share-booking-qr-modal', [
        'shareUrl' => \App\Support\BookingShareUrl::guest(),
        'shareLabel' => 'QR đặt vé chung',
        'modalId' => 'shareQrModal-booking-guest',
    ])
@endif

@include('partials.app-dialog')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app-dialog.js') }}"></script>
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

    var logoutForm = document.getElementById('logout-form');
    if (logoutForm) {
        logoutForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch('/csrf-token', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    syncCsrfToken(data.token);
                    logoutForm.submit();
                })
                .catch(function () { logoutForm.submit(); });
        });
    }

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
@if(($bookingShareQrNav ?? false) || (auth()->check() && auth()->user()->role === 'driver'))
<script src="{{ asset('js/share-booking-qr.js') }}"></script>
@endif
@stack('scripts')
</body>
</html>
