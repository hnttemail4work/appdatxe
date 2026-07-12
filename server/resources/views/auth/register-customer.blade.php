@extends('layouts.app')

@section('content')
<div class="auth-page row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-lg p-4 auth-card border-0">
            <h2 class="mb-1">Đăng ký tài khoản</h2>
            <p class="text-muted mb-4">Đã có tài khoản?
                <a href="{{ route('login') }}">Đăng nhập</a>
            </p>

            @if($errors->any())
                <div class="alert alert-danger py-2 small mb-3" role="alert">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('customer.register') }}" autocomplete="on">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                        class="form-control @error('phone') is-invalid @enderror"
                        required autofocus autocomplete="tel" inputmode="tel"
                        placeholder="0901234567">
                </div>
                <div class="mb-3">
                    <label class="form-label">Gmail</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        autocomplete="email" placeholder="email@gmail.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        required autocomplete="new-password" minlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nhập lại mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation"
                        class="form-control @error('password_confirmation') is-invalid @enderror"
                        required autocomplete="new-password" minlength="8">
                </div>
                <button class="btn btn-outline-primary w-100">Tạo tài khoản</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush
