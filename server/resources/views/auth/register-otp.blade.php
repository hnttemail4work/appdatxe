@extends('layouts.app')

@section('content')
@php
    use App\Support\AuthOtp;

    $phone = $phone ?? '';
    $forDriver = (bool) ($forDriver ?? false);
    $loginUrl = $loginUrl ?? ($forDriver ? route('driver.login') : route('login'));
    $awaitingApproval = (bool) ($awaitingApproval ?? false);
    $isCustomer = ($role ?? 'customer') === 'customer';
@endphp
<div class="auth-screen" data-auth-screen>
    @include('partials.auth-screen-header', [
        'authTitle' => $awaitingApproval ? 'Đang chờ admin duyệt' : 'Xác minh OTP',
        'authBackUrl' => $loginUrl,
    ])

    <div class="auth-screen-body">
        <div class="auth-otp-notice" role="status">
            <strong class="auth-otp-notice__title">
                {{ $awaitingApproval ? 'Đang chờ admin duyệt' : 'Nhập mã OTP' }}
            </strong>
            <p class="auth-otp-notice__text mb-0">
                @if($awaitingApproval)
                    {{ AuthOtp::awaitingApprovalOtpNotice($isCustomer) }}
                @else
                    Admin đã duyệt hồ sơ — nhập mã 6 số (hiệu lực {{ AuthOtp::ttlLabel() }}).
                @endif
            </p>
            @if($phone !== '')
                <p class="auth-otp-notice__meta mb-0">
                    SĐT: <span class="auth-otp-notice__phone">{{ $phone }}</span>
                </p>
            @endif
        </div>

        <div class="auth-otp-pin-stack">
            <form method="POST" action="{{ route('auth.register.otp.verify') }}" id="register-otp-form" data-pin-autosubmit="1">
                @csrf
                @include('partials.auth-pin-row', [
                    'pinName' => 'code',
                    'pinId' => 'register-otp',
                    'pinLabel' => 'Mã OTP',
                    'pinErrorBag' => 'code',
                    'hideNext' => true,
                    'pinAutoSubmit' => true,
                ])
            </form>

            <div class="auth-otp-refresh-wrap">
                <a href="{{ route('auth.register.otp') }}"
                   class="auth-otp-refresh"
                   title="Làm mới trang"
                   aria-label="Làm mới trang">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36"/>
                        <polyline points="21 3 21 9 15 9"/>
                    </svg>
                </a>
            </div>
        </div>

        @unless($awaitingApproval)
            <form method="POST" action="{{ route('auth.register.otp.resend') }}" class="auth-resend">
                @csrf
                <button type="submit">Gửi lại mã</button>
            </form>
        @endunless
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pin-input.js') }}?v={{ filemtime(public_path('js/pin-input.js')) }}"></script>
<script src="{{ asset('js/auth-field-validation.js') }}?v={{ filemtime(public_path('js/auth-field-validation.js')) }}"></script>
<script>
(function () {
  var form = document.getElementById('register-otp-form');
  if (form && window.AuthFieldValidation) AuthFieldValidation.bindCodeSubmit(form);
})();
</script>
@endpush
