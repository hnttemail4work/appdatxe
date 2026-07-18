@extends('layouts.app')

@section('content')
@php
    $fromDriver = (bool) ($fromDriver ?? false);
    $stepTitles = [
        1 => 'Giấy tờ',
        2 => 'Tài khoản',
        3 => 'Xe',
        4 => 'Ngân hàng',
        5 => 'Điều khoản',
        6 => 'Tạo PIN',
        7 => 'Nhập lại PIN',
    ];
@endphp
<div class="auth-screen" data-auth-screen data-driver-wizard-root
     data-home-url="{{ route('home') }}"
     data-step-titles='@json($stepTitles)'>
    @include('partials.auth-screen-header', [
        'authTitle' => $stepTitles[1],
        'authBackUrl' => route('home'),
    ])

    <div class="auth-screen-body">
        @if($errors->any())
            <div class="auth-field-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('register') }}"
              id="driver-register-form" enctype="multipart/form-data" novalidate autocomplete="off">
            @csrf
            <input type="hidden" name="register_mode" value="driver">
            @if($fromDriver)
                <input type="hidden" name="from" value="driver">
            @endif
            @include('auth.partials.register-driver')
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pin-input.js') }}?v={{ filemtime(public_path('js/pin-input.js')) }}"></script>
<script src="{{ asset('js/auth-field-validation.js') }}?v={{ filemtime(public_path('js/auth-field-validation.js')) }}"></script>
<script src="{{ asset('js/driver-register-wizard.js') }}?v={{ filemtime(public_path('js/driver-register-wizard.js')) }}"></script>
@endpush
