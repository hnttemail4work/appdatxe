<section class="customer-account-panel is-active" aria-label="Đổi PIN">
    <div class="customer-account-subhead mb-3">
        <a href="{{ route('customer.account', ['tab' => 'account']) }}" class="customer-account-back" aria-label="Quay lại">←</a>
        <h2 class="customer-account-panel__title mb-0">Đổi PIN</h2>
    </div>

    <div class="customer-account-card">
        <p class="small text-muted mb-3">Đăng nhập bằng số điện thoại và PIN 6 số.</p>
        <a href="{{ route('password.reset.request') }}" class="btn btn-primary w-100">Đặt lại PIN</a>
    </div>
</section>
