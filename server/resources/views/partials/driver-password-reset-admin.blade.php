@if(($canReset ?? false) && isset($driver) && $driver->user)
<div class="driver-password-reset-actions border rounded p-3 bg-light-subtle">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="fw-semibold">Đặt lại PIN</div>
        <form method="POST"
              action="{{ route('admin.drivers.resetPassword', $driver) }}"
              data-confirm="Đặt lại PIN 6 số cho tài xế này? PIN cũ sẽ không dùng được nữa."
              data-confirm-title="Đặt lại PIN"
              data-confirm-variant="warning"
              data-confirm-ok="Đặt lại">
            @csrf
            <button type="submit" class="btn btn-outline-warning btn-sm fw-semibold">Đặt lại PIN</button>
        </form>
    </div>
</div>
@endif
