@extends('layouts.app')

@section('content')
@php
    $mode = $mode ?? old('register_mode', 'customer');
    if (! in_array($mode, ['customer', 'driver'], true)) {
        $mode = 'customer';
    }
@endphp
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="mb-1">Đăng ký tài khoản</h2>
                    <p class="text-muted mb-0">Chọn loại tài khoản và điền đầy đủ thông tin.</p>
                </div>
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Đã có tài khoản? Đăng nhập</a>
            </div>

            <ul class="nav nav-pills nav-fill mb-4">
                <li class="nav-item">
                    <a class="nav-link {{ $mode === 'customer' ? 'active' : '' }}"
                       href="{{ route('register', ['mode' => 'customer']) }}">Khách hàng</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $mode === 'driver' ? 'active' : '' }}"
                       href="{{ route('register', ['mode' => 'driver']) }}">Tài xế</a>
                </li>
            </ul>

            <form method="POST" action="{{ route('register') }}">
                @csrf
                <input type="hidden" name="register_mode" value="{{ $mode }}">

                @if($mode === 'driver')
                    @include('auth.partials.register-driver')
                @else
                    @include('auth.partials.register-customer')
                @endif

                <div class="form-check mt-3">
                    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox"
                           name="terms" value="1" id="termsCheck" {{ old('terms') ? 'checked' : '' }} required>
                    <label class="form-check-label small" for="termsCheck">
                        Tôi đồng ý với điều khoản sử dụng và chính sách bảo mật của {{ config('app.name') }}.
                    </label>
                    @error('terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <button class="btn btn-primary w-100 mt-3">
                    {{ $mode === 'driver' ? 'Gửi hồ sơ đăng ký tài xế' : 'Tạo tài khoản khách hàng' }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
