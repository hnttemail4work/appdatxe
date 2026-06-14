@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm p-4">
            <h2 class="mb-1">Đăng nhập</h2>
            <p class="text-muted mb-4">Chưa có tài khoản?
                <a href="{{ route('register', ['mode' => 'customer']) }}">Đăng ký khách hàng</a> ·
                <a href="{{ route('register', ['mode' => 'driver']) }}">Đăng ký tài xế</a>
            </p>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="email@example.com" required autofocus>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="••••••••" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label text-muted" for="remember">Ghi nhớ đăng nhập</label>
                    </div>
                </div>
                <button class="btn btn-primary w-100">Đăng nhập</button>
            </form>

            <hr class="my-4">
            <p class="text-muted small text-center mb-0">
                Tài khoản demo:<br>
                Admin: <code>admin@appdatxe.test</code> · Quản lý: <code>vantam.quanly@gmail.com</code><br>
                Khách hàng: <code>customer@appdatxe.test</code> · Tài xế: <code>driver@appdatxe.test</code><br>
                Mật khẩu: <code>password</code>
            </p>
        </div>
    </div>
</div>
@endsection
