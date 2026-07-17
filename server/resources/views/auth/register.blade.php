@extends('layouts.app')

@section('content')
<div class="customer-page register-shell">
    <div class="card border-0 overflow-hidden register-card">
        <div class="register-header">
            <h2 class="register-title">Đăng ký tài xế</h2>
            <p class="register-subtitle">Hoàn tất hồ sơ để bắt đầu nhận chuyến</p>
        </div>
        <div class="card-body register-body">
            @if($errors->has('phone'))
                <div class="alert alert-danger register-alert py-2 px-3 mb-3" role="alert">
                    {{ $errors->first('phone') }}
                    <a href="{{ route('login') }}" class="register-alert-link">Đăng nhập</a>
                </div>
            @elseif($errors->any())
                <div class="alert alert-danger register-alert py-2 px-3 mb-3" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif
            <form method="POST" action="/register"
                  id="driver-register-form" enctype="multipart/form-data" novalidate autocomplete="off">
                @csrf
                <input type="hidden" name="register_mode" value="driver">
                @include('auth.partials.register-driver')
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/driver-register-wizard.js') }}"></script>
@endpush
