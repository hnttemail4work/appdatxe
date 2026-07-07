@php
/** @var \App\Models\User|null $adminUser */
$adminUser = $adminUser ?? auth()->user();
@endphp

<div class="mb-4 pb-4">
    <h3 class="h6 fw-bold text-uppercase text-muted mb-2" style="letter-spacing:.04em">Đổi mật khẩu quản trị</h3>
    <p class="text-muted small mb-3">
        Tài khoản: <strong>{{ $adminUser?->phone ?: '—' }}</strong>
        @if($adminUser?->name)
            · {{ $adminUser->name }}
        @endif
    </p>

    <form method="POST" action="{{ route('admin.password.update') }}" class="console-form" style="max-width: 28rem;">
        @csrf
        @method('PATCH')

        <div class="mb-3">
            <label class="form-label" for="admin-current-password">Mật khẩu hiện tại</label>
            <input type="password"
                   name="current_password"
                   id="admin-current-password"
                   class="form-control @error('current_password') is-invalid @enderror"
                   required
                   autocomplete="current-password">
            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="admin-new-password">Mật khẩu mới</label>
            <input type="password"
                   name="password"
                   id="admin-new-password"
                   class="form-control @error('password') is-invalid @enderror"
                   required
                   minlength="6"
                   autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Tối thiểu 6 ký tự.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="admin-new-password-confirm">Nhập lại mật khẩu mới</label>
            <input type="password"
                   name="password_confirmation"
                   id="admin-new-password-confirm"
                   class="form-control @error('password_confirmation') is-invalid @enderror"
                   required
                   minlength="6"
                   autocomplete="new-password">
            @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary px-4 fw-semibold">Lưu mật khẩu mới</button>
    </form>
</div>
