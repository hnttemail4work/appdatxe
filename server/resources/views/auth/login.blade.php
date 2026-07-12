@extends('layouts.app')

@section('content')
<div class="auth-page row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-lg p-4 auth-card border-0">
            <h2 class="mb-1">Đăng nhập</h2>
            <p class="text-muted mb-4">
                Khách hàng chưa có tài khoản?
                <a href="{{ route('customer.register') }}">Đăng ký</a>
                · Tài xế?
                <a href="{{ route('register') }}">Đăng ký tài xế</a>
            </p>

            @error('login')
                <div class="alert alert-danger py-2 small mb-3" role="alert">{{ $message }}</div>
            @enderror

            <form method="POST" action="/login" autocomplete="on">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                        class="form-control @error('phone') is-invalid @enderror"
                        required autofocus autocomplete="username" inputmode="tel">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        required autocomplete="current-password">
                </div>
                <button class="btn btn-outline-primary w-100">Tiếp tục</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush
