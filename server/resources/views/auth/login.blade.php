@extends('layouts.app')

@section('content')
@php
    $forDriver = (bool) ($forDriver ?? false);
    $loginAction = $loginAction ?? route('login');
    $phoneValue = old('phone', $phone ?? request('phone'));
@endphp
<div class="auth-screen" data-auth-screen data-login-pin
     data-check-phone-url="{{ route('login.checkPhone') }}"
     data-for-driver="{{ $forDriver ? '1' : '0' }}"
     data-auto-check-phone="{{ filled($phoneValue) && ! $errors->has('login') && ! old('password') ? '1' : '0' }}">
    @include('partials.auth-screen-header', [
        'authTitle' => ($errors->has('login') || old('password')) ? 'Nhập PIN' : ($forDriver ? 'Đăng nhập tài xế' : 'Đăng nhập'),
        'authBackUrl' => $forDriver ? route('driver.login') : route('home'),
    ])

    <div class="auth-screen-body">
        <form method="POST" action="{{ $loginAction }}" autocomplete="on" id="login-pin-form" data-pin-autosubmit="1">
            @csrf
            @if($forDriver)
                <input type="hidden" name="for_driver" value="1">
            @endif

            <div class="auth-step-panel" data-login-step="phone" @if($errors->has('login') || old('password')) hidden @endif>
                @include('partials.auth-field-row', [
                    'fieldLabel' => 'Số điện thoại',
                    'fieldName' => 'phone',
                    'fieldId' => 'login-phone',
                    'fieldType' => 'tel',
                    'fieldValue' => $phoneValue,
                    'fieldAutocomplete' => 'off',
                    'fieldInputmode' => 'tel',
                    'nextType' => 'button',
                    'nextAttr' => 'data-login-continue',
                    'footerHtml' => '<a href="'.e(route('password.reset.request', $forDriver ? ['for_driver' => 1] : [])).'">Quên mật khẩu?</a>',
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
