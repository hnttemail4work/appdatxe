@php $reset = session('driver_password_reset'); @endphp
@if($reset)
<div class="alert alert-warning driver-password-reset-alert mb-3" role="alert">
    <div class="fw-semibold mb-2">Đã đặt lại mật khẩu cho {{ $reset['driver_name'] ?? 'tài xế' }}</div>
    <div class="driver-password-reset-codes">
        <span class="driver-password-reset-label">Mật khẩu mới</span>
        <code class="driver-password-reset-value">{{ $reset['password'] }}</code>
        <span class="driver-password-reset-badge">Mã 8 ký tự</span>
    </div>
</div>
@endif
