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
                    <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                        class="form-control @error('name') is-invalid @enderror"
                        required autofocus autocomplete="name"
                        placeholder="Nguyễn Văn A">
                </div>
                <div class="mb-3">
                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                        class="form-control @error('phone') is-invalid @enderror"
                        required autocomplete="tel" inputmode="tel"
                        placeholder="0901234567">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Tuổi <span class="text-danger">*</span></label>
                        <input type="number" name="age" value="{{ old('age') }}"
                            class="form-control @error('age') is-invalid @enderror"
                            required min="1" max="120" inputmode="numeric"
                            placeholder="25">
                    </div>
                    <div class="col-6">
                        <label class="form-label d-block">Giới tính <span class="text-danger">*</span></label>
                        @php $defaultGender = old('gender', 'male'); @endphp
                        <div class="booking-chip-group booking-chip-group--inline mt-1">
                            <label class="form-check booking-chip">
                                <input type="radio" name="gender" value="male" class="form-check-input"
                                    @checked($defaultGender === 'male') required> Nam
                            </label>
                            <label class="form-check booking-chip">
                                <input type="radio" name="gender" value="female" class="form-check-input"
                                    @checked($defaultGender === 'female')> Nữ
                            </label>
                        </div>
                    </div>
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
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush
