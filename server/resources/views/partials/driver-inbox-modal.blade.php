<div class="driver-inbox-backdrop" id="driver-inbox-backdrop" hidden></div>
<div class="driver-inbox-modal" id="driver-inbox-modal" hidden role="dialog" aria-modal="true" aria-labelledby="driver-inbox-title">
    <div class="driver-inbox-modal__head">
        <h2 class="driver-inbox-modal__title" id="driver-inbox-title">Thông báo</h2>
        <button type="button" class="driver-inbox-modal__close" id="driver-inbox-close" aria-label="Đóng">×</button>
    </div>
    <div class="driver-inbox-modal__body">
        @if($profile->isMissedTripLocked())
            <article class="driver-inbox-item driver-inbox-item--danger">
                <strong>Tài khoản tạm khóa</strong>
                <p class="mb-0">Không nhận chuyến được. Liên hệ quản lý để mở khóa.</p>
            </article>
        @endif

        @if($walletBlockReason ?? null)
            <article class="driver-inbox-item driver-inbox-item--warning">
                <strong>Ví tài xế</strong>
                <p class="mb-2">{{ $walletBlockReason }}</p>
                @if($walletNotice ?? null)
                    <button type="button" class="btn btn-sm btn-outline-warning" data-driver-tab="wallet" data-driver-inbox-close>
                        {{ $walletNotice['cta_label'] ?? 'Nạp ví ngay' }}
                    </button>
                @endif
            </article>
        @endif

        @unless($profile->isMissedTripLocked() || ($walletBlockReason ?? null))
            <div class="driver-inbox-empty">
                <p class="mb-0">Chưa có thông báo mới.</p>
            </div>
        @endunless
    </div>
</div>
