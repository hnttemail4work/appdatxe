@extends('layouts.app')

@section('content')
@php
    $stepTitles = [
        1 => 'Đăng ký tài khoản',
        2 => 'CCCD',
        3 => 'Tạo PIN',
        4 => 'Nhập lại PIN',
    ];
@endphp
<div class="auth-screen" data-auth-screen data-customer-wizard-root
     data-home-url="{{ route('home') }}"
     data-check-phone-url="{{ route('login.checkPhone') }}"
     data-step-titles='@json($stepTitles)'>
    @include('partials.auth-screen-header', [
        'authTitle' => $stepTitles[1],
        'authBackUrl' => route('home'),
    ])

    <div class="auth-screen-body">
        <form method="POST" action="{{ route('customer.register') }}" id="customer-register-form"
              enctype="multipart/form-data" autocomplete="on" novalidate>
            @csrf
            <input type="hidden" name="password" data-register-password value="{{ old('password') }}">
            <input type="hidden" name="password_confirmation" data-register-password-confirm value="{{ old('password_confirmation') }}">

            <div id="customer-wizard" data-customer-wizard>
                <div class="auth-step-panel" data-wizard-step="1">
                    @include('partials.auth-field-row', [
                        'fieldLabel' => 'Số điện thoại',
                        'fieldName' => 'phone',
                        'fieldId' => 'customer-register-phone',
                        'fieldType' => 'tel',
                        'fieldValue' => old('phone', request('phone')),
                        'fieldAutocomplete' => 'off',
                        'fieldInputmode' => 'tel',
                        'nextType' => 'button',
                        'nextAttr' => 'data-wizard-next',
                    ])
                </div>

                <div class="auth-step-panel" data-wizard-step="2" hidden>
                    <div class="auth-field-label">Ảnh CCCD</div>
                    @include('partials.customer-docs-upload-register', ['idCardRequired' => true])
                    @include('partials.auth-terms-consent', ['checkboxId' => 'customerTermsCheck'])
                    <div class="auth-group-actions">
                        @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
                    </div>
                </div>

                <div class="auth-step-panel" data-wizard-step="3" hidden>
                    @include('partials.auth-pin-row', [
                        'pinName' => 'pin_draft',
                        'pinId' => 'customer-pin',
                        'pinLabel' => 'PIN',
                        'hideNext' => true,
                    ])
                </div>

                <div class="auth-step-panel" data-wizard-step="4" hidden>
                    @include('partials.auth-pin-row', [
                        'pinName' => 'pin_confirm_draft',
                        'pinId' => 'customer-pin-confirm',
                        'pinLabel' => 'Nhập lại PIN',
                        'hideNext' => true,
                    ])
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pin-input.js') }}?v={{ filemtime(public_path('js/pin-input.js')) }}"></script>
<script src="{{ asset('js/auth-field-validation.js') }}?v={{ filemtime(public_path('js/auth-field-validation.js')) }}"></script>
<script src="{{ asset('js/auth-phone-gate.js') }}?v={{ filemtime(public_path('js/auth-phone-gate.js')) }}"></script>
<script src="{{ asset('js/customer-register-wizard.js') }}?v={{ filemtime(public_path('js/customer-register-wizard.js')) }}"></script>
@endpush
