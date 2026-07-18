@php
    $authTitle = $authTitle ?? 'Đăng nhập';
    $authBackUrl = $authBackUrl ?? route('home');
@endphp
<header class="auth-screen-header">
    <a href="{{ $authBackUrl }}"
       class="auth-back"
       data-auth-back
       aria-label="Quay lại">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 18l-6-6 6-6"/>
        </svg>
    </a>
    <h1 class="auth-screen-title" data-auth-title>{{ $authTitle }}</h1>
</header>
