@extends('layouts.app')

@section('content')
@php
    $stepTitles = [
        1 => 'Đăng ký tài khoản',
        2 => 'CCCD',
        3 => 'Điều khoản',
        4 => 'Tạo PIN',
        5 => 'Nhập lại PIN',
    ];
@endphp
<div class="auth-screen" data-auth-screen data-customer-wizard-root
     data-home-url="{{ route('home') }}"
     data-step-titles='@json($stepTitles)'>
    @include('partials.auth-screen-header', [
        'authTitle' => $stepTitles[1],
        'authBackUrl' => route('home'),
    ])

    <div class="auth-screen-body">
        @if($errors->any())
            <div class="auth-field-error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

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
                        'fieldValue' => old('phone'),
                        'fieldPlaceholder' => '0901234567',
                        'fieldAutocomplete' => 'tel',
                        'fieldInputmode' => 'tel',
                        'nextType' => 'button',
                        'nextAttr' => 'data-wizard-next',
                    ])
                </div>

                <div class="auth-step-panel" data-wizard-step="2" hidden>
                    <div class="auth-field-label">Ảnh CCCD</div>
                    @include('partials.customer-docs-upload-register', ['idCardRequired' => true])
                    <div class="auth-group-actions">
                        @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
                    </div>
                </div>

                <div class="auth-step-panel" data-wizard-step="3" hidden>
                    <div class="auth-terms-row">
                        <input class="@error('terms') is-invalid @enderror" type="checkbox"
                               name="terms" value="1" id="customerTermsCheck" {{ old('terms') ? 'checked' : '' }} required>
                        <span>Đồng ý điều khoản {{ config('app.name') }}.</span>
                    </div>
                    <div class="invalid-feedback" data-client-feedback="terms">@error('terms'){{ $message }}@enderror</div>
                    <div class="auth-group-actions">
                        @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
                    </div>
                </div>

                <div class="auth-step-panel" data-wizard-step="4" hidden>
                    @include('partials.auth-pin-row', [
                        'pinName' => 'pin_draft',
                        'pinId' => 'customer-pin',
                        'pinLabel' => 'PIN',
                        'nextType' => 'button',
                        'nextAttr' => 'data-wizard-next',
                    ])
                </div>

                <div class="auth-step-panel" data-wizard-step="5" hidden>
                    @include('partials.auth-pin-row', [
                        'pinName' => 'pin_confirm_draft',
                        'pinId' => 'customer-pin-confirm',
                        'pinLabel' => 'Nhập lại PIN',
                        'nextType' => 'submit',
                        'nextAttr' => 'data-wizard-submit',
                        'nextAria' => 'Đăng ký',
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
<script src="{{ asset('js/customer-register-wizard.js') }}?v={{ filemtime(public_path('js/customer-register-wizard.js')) }}"></script>
@endpush
