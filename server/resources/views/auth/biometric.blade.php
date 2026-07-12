@extends('layouts.app')

@section('content')
<div class="auth-page row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-lg p-4 auth-card border-0 customer-biometric-card"
             id="customer-biometric-app"
             data-has-credentials="{{ $hasCredentials ? '1' : '0' }}"
             data-register-options-url="{{ route('auth.webauthn.register.options') }}"
             data-register-verify-url="{{ route('auth.webauthn.register.verify') }}"
             data-login-options-url="{{ route('auth.webauthn.login.options') }}"
             data-login-verify-url="{{ route('auth.webauthn.login.verify') }}"
             data-skip-url="{{ route('auth.webauthn.skip') }}">
            <div class="customer-biometric-icon" aria-hidden="true">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                    <circle cx="12" cy="10" r="4"/>
                    <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
                    <path d="M16 3.5c1.2.8 2 2.2 2 3.8"/>
                    <path d="M8 3.5c-1.2.8-2 2.2-2 3.8"/>
                </svg>
            </div>
            <h2 class="mb-2 text-center">Xác thực sinh trắc học</h2>
            <p class="text-muted text-center mb-4">
                Xin chào <strong>{{ $user->name }}</strong>.
                @if($hasCredentials)
                    Quét khuôn mặt hoặc vân tay để hoàn tất đăng nhập.
                @else
                    Thiết lập Face ID / vân tay để bảo vệ tài khoản mỗi lần mở app.
                @endif
            </p>

            <div id="customer-biometric-alert" class="alert d-none mb-3" role="alert"></div>

            <button type="button" class="btn btn-outline-primary w-100 mb-2" id="customer-biometric-start">
                @if($hasCredentials)
                    Quét khuôn mặt / vân tay
                @else
                    Thiết lập sinh trắc học
                @endif
            </button>

            <button type="button" class="btn btn-link w-100 text-muted small d-none" id="customer-biometric-skip">
                Thiết bị không hỗ trợ — tiếp tục không dùng sinh trắc
            </button>

            <p class="text-muted small text-center mt-3 mb-0">
                Yêu cầu HTTPS và trình duyệt hỗ trợ WebAuthn (Safari/Chrome trên điện thoại).
            </p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/webauthn-client.js') }}?v={{ filemtime(public_path('js/webauthn-client.js')) }}"></script>
@endpush
