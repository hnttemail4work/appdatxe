@extends('layouts.app')

@section('content')
<div class="auth-screen" data-auth-screen>
    @include('partials.auth-screen-header', [
        'authTitle' => 'Sinh trắc học',
        'authBackUrl' => route('login'),
    ])

    <div class="auth-screen-body">
        <div id="customer-biometric-app"
             data-has-credentials="{{ $hasCredentials ? '1' : '0' }}"
             data-register-options-url="{{ route('auth.webauthn.register.options') }}"
             data-register-verify-url="{{ route('auth.webauthn.register.verify') }}"
             data-login-options-url="{{ route('auth.webauthn.login.options') }}"
             data-login-verify-url="{{ route('auth.webauthn.login.verify') }}"
             data-skip-url="{{ route('auth.webauthn.skip') }}">
            <p class="auth-biometric-hint mb-0">
                @if($hasCredentials)
                    Quét khuôn mặt hoặc vân tay để hoàn tất đăng nhập.
                @else
                    Thiết lập Face ID / vân tay để bảo vệ tài khoản.
                @endif
            </p>

            <div id="customer-biometric-alert" class="auth-field-error d-none" role="alert"></div>

            <div class="auth-biometric-actions">
                <button type="button" class="btn btn-warning fw-semibold" id="customer-biometric-start">
                    @if($hasCredentials)
                        Quét sinh trắc
                    @else
                        Thiết lập sinh trắc
                    @endif
                </button>

                <button type="button" class="auth-text-link d-none" id="customer-biometric-skip">
                    Tiếp tục không dùng sinh trắc
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/webauthn-client.js') }}?v={{ filemtime(public_path('js/webauthn-client.js')) }}"></script>
@endpush
