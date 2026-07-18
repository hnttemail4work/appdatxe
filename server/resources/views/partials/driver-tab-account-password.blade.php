@php
    $mustChangePassword = (bool) ($mustChangePassword ?? false);
@endphp
<section class="driver-account-panel" aria-label="Đổi PIN">
    <h2 class="driver-panel-title mb-3" data-i18n="account_password">Đổi PIN</h2>

    @if($mustChangePassword)
        <div class="driver-notice driver-notice-warning mb-3" role="alert">
            <strong data-i18n="account_password_required_title">Đổi PIN</strong>
            <p class="mb-0 small" data-i18n="account_password_required_hint">Bạn đang dùng PIN tạm. Vui lòng đặt PIN 6 số mới để bảo mật tài khoản.</p>
        </div>
    @endif

    <div class="driver-account-card driver-settings-card">
        <p class="driver-account-hint mb-3" data-i18n="account_password_hint">PIN gồm đúng 6 chữ số. Không chia sẻ cho người khác.</p>

        <form method="POST" action="{{ route('driver.password.update') }}" class="driver-account-form">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label class="form-label" for="driver-current-password" data-i18n="account_password_current">PIN hiện tại</label>
                <input type="password"
                       name="current_password"
                       id="driver-current-password"
                       class="form-control @error('current_password') is-invalid @enderror"
                       required
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       maxlength="6"
                       autocomplete="current-password">
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="driver-new-password" data-i18n="account_password_new">PIN mới</label>
                <input type="password"
                       name="password"
                       id="driver-new-password"
                       class="form-control @error('password') is-invalid @enderror"
                       required
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       maxlength="6"
                       autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="driver-new-password-confirm" data-i18n="account_password_confirm">Nhập lại PIN mới</label>
                <input type="password"
                       name="password_confirmation"
                       id="driver-new-password-confirm"
                       class="form-control @error('password_confirmation') is-invalid @enderror"
                       required
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       maxlength="6"
                       autocomplete="new-password">
                @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-warning fw-semibold w-100" data-i18n="account_password_save">Lưu PIN mới</button>
        </form>

        <p class="small text-muted mt-3 mb-0">
            Quên PIN?
            <a href="{{ route('password.reset.request') }}">Yêu cầu đặt lại qua admin</a>
        </p>
    </div>
</section>
