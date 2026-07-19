@extends('layouts.app')

@section('content')
<div class="auth-screen" data-auth-screen>
    @include('partials.auth-screen-header', [
        'authTitle' => 'Xác minh OTP',
        'authBackUrl' => route('home'),
    ])

    <div class="auth-screen-body">
        @error('code')
            <div class="auth-field-error" role="alert">{{ $message }}</div>
        @enderror

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

        <form method="POST" action="{{ route('auth.register.otp.resend') }}" class="auth-resend">
            @csrf
            <button type="submit">Gửi lại mã</button>
        </form>
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
