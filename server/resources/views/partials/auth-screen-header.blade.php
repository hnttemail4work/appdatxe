@php
    $authTitle = $authTitle ?? 'Đăng nhập';
    $authBackUrl = $authBackUrl ?? route('home');
@endphp
<header class="auth-screen-header">
    <a href="{{ $authBackUrl }}"
       class="auth-back"
       data-auth-back
       aria-label="Quay lại">
        @include('partials.app-back-icon')
    </a>
    <h1 class="auth-screen-title" data-auth-title>{{ $authTitle }}</h1>
</header>
