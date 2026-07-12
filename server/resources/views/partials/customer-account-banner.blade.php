@if(auth()->check() && auth()->user()->role === 'customer')
<section class="customer-account-banner mb-3" aria-label="Tài khoản">
    <a href="{{ route('customer.account') }}" class="customer-account-banner__link">
        <span class="customer-account-banner__avatar" aria-hidden="true">{{ auth()->user()->avatarInitial() }}</span>
        <span class="customer-account-banner__copy">
            <strong>{{ auth()->user()->name }}</strong>
            <span class="customer-account-banner__hint">Xem lịch sử chuyến &amp; đánh giá</span>
        </span>
        <span class="customer-account-banner__chev" aria-hidden="true">›</span>
    </a>
</section>
@elseif(! auth()->check())
<section class="customer-account-banner customer-account-banner--guest mb-3" aria-label="Đăng nhập">
    <div class="customer-account-banner__guest">
        <div>
            <strong>Tài khoản khách hàng</strong>
            <p class="mb-0 small text-muted">Đăng nhập để xem lịch sử chuyến và đánh giá tài xế.</p>
        </div>
        <div class="customer-account-banner__actions">
            <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary">Đăng nhập</a>
            <a href="{{ route('customer.register') }}" class="btn btn-sm btn-outline-secondary">Đăng ký</a>
        </div>
    </div>
</section>
@endif
