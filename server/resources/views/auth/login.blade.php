@extends('layouts.app')

@section('content')
<div class="auth-screen" data-auth-screen data-login-pin
     data-check-phone-url="{{ route('login.checkPhone') }}">
    @include('partials.auth-screen-header', [
        'authTitle' => ($errors->has('login') || old('password')) ? 'Nhập PIN' : 'Đăng nhập',
        'authBackUrl' => route('home'),
    ])

    <div class="auth-screen-body">
        <form method="POST" action="/login" autocomplete="on" id="login-pin-form" data-pin-autosubmit="1">
            @csrf

            <div class="auth-step-panel" data-login-step="phone" @if($errors->has('login') || old('password')) hidden @endif>
                @include('partials.auth-field-row', [
                    'fieldLabel' => 'Số điện thoại',
                    'fieldName' => 'phone',
                    'fieldId' => 'login-phone',
                    'fieldType' => 'tel',
                    'fieldValue' => old('phone'),
                    'fieldAutocomplete' => 'off',
                    'fieldInputmode' => 'tel',
                    'nextType' => 'button',
                    'nextAttr' => 'data-login-continue',
                    'footerHtml' => '<a href="'.e(route('password.reset.request')).'">Quên mật khẩu?</a>',
                ])
            </div>

            <div class="auth-step-panel" data-login-step="pin" @if(! $errors->has('login') && ! old('password')) hidden @endif>
                @include('partials.auth-pin-row', [
                    'pinName' => 'password',
                    'pinId' => 'login-pin',
                    'pinLabel' => 'PIN',
                    'pinErrorBag' => 'login',
                    'hideNext' => true,
                    'pinAutoSubmit' => true,
                ])
            </div>
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
<script src="{{ asset('js/login-pin.js') }}?v={{ filemtime(public_path('js/login-pin.js')) }}"></script>
@endpush
