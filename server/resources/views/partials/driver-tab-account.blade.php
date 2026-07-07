@php
    $mustChangePassword = (bool) ($mustChangePassword ?? false);
@endphp

<section class="driver-account-panel" aria-label="Tài khoản">
    @if($mustChangePassword)
        <div class="driver-notice driver-notice-warning mb-3" role="alert">
            <strong>Đổi mật khẩu</strong>
            <p class="mb-0 small">Bạn đang dùng mật khẩu mặc định. Vui lòng đặt mật khẩu mới để bảo mật tài khoản.</p>
        </div>
    @endif

    <div class="driver-account-card">
        <h2 class="driver-panel-title mb-1">Đổi mật khẩu</h2>
        <p class="driver-account-hint mb-3">Mật khẩu tối thiểu 6 ký tự. Dùng mật khẩu riêng, không chia sẻ cho người khác.</p>

        <form method="POST" action="{{ route('driver.password.update') }}" class="driver-account-form">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label class="form-label" for="driver-current-password">Mật khẩu hiện tại</label>
                <input type="password"
                       name="current_password"
                       id="driver-current-password"
                       class="form-control @error('current_password') is-invalid @enderror"
                       required
                       autocomplete="current-password">
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="driver-new-password">Mật khẩu mới</label>
                <input type="password"
                       name="password"
                       id="driver-new-password"
                       class="form-control @error('password') is-invalid @enderror"
                       required
                       minlength="6"
                       autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="driver-new-password-confirm">Nhập lại mật khẩu mới</label>
                <input type="password"
                       name="password_confirmation"
                       id="driver-new-password-confirm"
                       class="form-control @error('password_confirmation') is-invalid @enderror"
                       required
                       minlength="6"
                       autocomplete="new-password">
                @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-warning fw-semibold">Lưu mật khẩu mới</button>
        </form>
    </div>
</section>
