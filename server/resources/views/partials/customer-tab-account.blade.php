@php
    $pendingChange = $pendingChange ?? null;
@endphp
<section class="customer-account-panel is-active" aria-label="Tài khoản">
    <nav class="customer-account-menu" aria-label="Mục tài khoản">
        <a href="{{ route('customer.account', ['tab' => 'profile']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Hồ sơ</strong>
                <span class="customer-account-menu__hint">Xem số điện thoại và CCCD</span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <a href="{{ route('customer.account', ['tab' => 'info']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Cập nhật thông tin</strong>
                <span class="customer-account-menu__hint">Họ tên, ngày sinh, giới tính</span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <a href="{{ route('customer.account', ['tab' => 'update']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Cập nhật CCCD</strong>
                <span class="customer-account-menu__hint">
                    Số CCCD và ảnh giấy tờ
                    @if($pendingChange)
                        · Đang chờ duyệt
                    @endif
                </span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <a href="{{ route('customer.account', ['tab' => 'password']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Đổi PIN</strong>
                <span class="customer-account-menu__hint">Bảo mật tài khoản đăng nhập</span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <a href="{{ route('customer.account', ['tab' => 'wallet']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Ví</strong>
                <span class="customer-account-menu__hint">Số dư và nạp tiền</span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>

        <a href="{{ route('customer.account', ['tab' => 'trips']) }}" class="customer-account-menu__item">
            <span class="customer-account-menu__copy">
                <strong>Lịch sử chuyến</strong>
                <span class="customer-account-menu__hint">Các chuyến đã hoàn thành</span>
            </span>
            <span class="customer-account-menu__chevron" aria-hidden="true">›</span>
        </a>
    </nav>

    <div class="customer-account-card mt-3">
        @include('partials.logout-button', ['class' => 'btn btn-outline-danger w-100 btn-logout'])
    </div>
</section>
