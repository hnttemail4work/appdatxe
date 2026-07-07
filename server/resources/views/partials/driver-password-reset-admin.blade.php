@if(($canReset ?? false) && isset($driver) && $driver->user)
<div class="driver-password-reset-actions border rounded p-3 bg-light-subtle">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="fw-semibold">Quên mật khẩu</div>
        <form method="POST"
              action="{{ route('admin.drivers.resetPassword', $driver) }}"
              data-confirm="Đặt lại mật khẩu cho tài xế này? Mật khẩu cũ sẽ không dùng được nữa."
              data-confirm-title="Đặt lại mật khẩu"
              data-confirm-variant="warning"
              data-confirm-ok="Đặt lại">
            @csrf
            <button type="submit" class="btn btn-outline-warning btn-sm fw-semibold">Đặt lại mật khẩu</button>
        </form>
    </div>
</div>
@endif
