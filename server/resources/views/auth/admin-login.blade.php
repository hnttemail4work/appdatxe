@extends('layouts.app')

@section('content')
<div class="auth-screen" data-auth-screen>
    @include('partials.auth-screen-header', [
        'authTitle' => 'Đăng nhập quản trị',
        'authBackUrl' => route('home'),
    ])

    <div class="auth-screen-body">
        <form method="POST" action="{{ route('admin.login') }}" autocomplete="on">
            @csrf

            <div class="auth-field-block">
                <label class="auth-field-label" for="admin-login">Tài khoản</label>
                <div class="auth-field-row">
                    <input
                        type="text"
                        name="login"
                        id="admin-login"
                        value="{{ old('login') }}"
                        class="auth-field-input @error('login') is-invalid @enderror"
                        required
                        autocomplete="username"
                        autofocus
                        placeholder="Tài khoản quản trị"
                    >
                </div>
                <div class="auth-field-error" hidden></div>
            </div>

            <div class="auth-field-block">
                <label class="auth-field-label" for="admin-password">Mật khẩu</label>
                <div class="auth-field-row">
                    <input
                        type="password"
                        name="password"
                        id="admin-password"
                        class="auth-field-input @error('password') is-invalid @enderror"
                        required
                        autocomplete="current-password"
                        placeholder="Mật khẩu"
                    >
                    <button type="submit" class="auth-next-btn" aria-label="Đăng nhập">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </button>
                </div>
                <div class="auth-field-error" hidden></div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush
