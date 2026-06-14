<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand text-primary" href="{{ url('/') }}">{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-1">
                @auth
                    @php $role = auth()->user()->role; @endphp

                    @if($role === 'customer')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.dashboard') ? 'active-page' : '' }}"
                               href="{{ route('customer.dashboard') }}">Đặt vé</a>
                        </li>
                    @endif

                    @if($role === 'driver')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('driver.dashboard') ? 'active-page' : '' }}"
                               href="{{ route('driver.dashboard') }}">Lịch chạy</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('driver.profile*') ? 'active-page' : '' }}"
                               href="{{ route('driver.profile') }}">Hồ sơ tài xế</a>
                        </li>
                    @endif

                    @if($role === 'operator')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('operator.dashboard') ? 'active-page' : '' }}"
                               href="{{ route('operator.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('operator.drivers*') ? 'active-page' : '' }}"
                               href="{{ route('operator.drivers') }}">Danh sách tài xế</a>
                        </li>
                    @endif

                    @if($role === 'admin')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('operator.drivers*') ? 'active-page' : '' }}"
                               href="{{ route('operator.drivers') }}">Danh sách tài xế</a>
                        </li>
                    @endif

                    @if($role === 'admin')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active-page' : '' }}"
                               href="{{ route('admin.dashboard') }}">Quản trị</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.bookings') ? 'active-page' : '' }}"
                               href="{{ route('admin.bookings') }}">Booking</a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <span class="nav-link text-muted small pe-0">
                            {{ auth()->user()->name }}
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;vertical-align:middle;">
                                {{ match(auth()->user()->role) {
                                    'admin'    => 'Quản trị',
                                    'operator' => 'Quản lý',
                                    'driver'   => 'Tài xế',
                                    default    => 'Khách hàng',
                                } }}
                            </span>
                        </span>
                    </li>
                    <li class="nav-item ms-1">
                        <form action="{{ route('logout') }}" method="POST" class="d-inline" id="logout-form">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">Đăng xuất</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item ms-1">
                        <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Đăng ký</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">
    @include('partials.alerts')
    @yield('content')
</div>

<footer class="bg-dark text-secondary mt-5 py-4 border-top">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-2">{{ config('app.name') }}</h6>
                <p class="small mb-0">Nền tảng đặt vé xe khách liên tỉnh cao cấp.</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-white fw-bold mb-2">Liên hệ</h6>
                <p class="small mb-1">Hotline: {{ config('app.contact_phone') }}</p>
                <p class="small mb-0">Email: {{ config('app.contact_email') }}</p>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <p class="small text-center mb-0">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
})();
</script>
@stack('scripts')
</body>
</html>
