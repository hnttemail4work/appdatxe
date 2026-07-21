@php
    /** @var \App\Models\ReferralCode|null $commissionReferral */
    $commissionReferral = $commissionReferral ?? null;
    $commissionPercent = $commissionReferral ? $commissionReferral->commissionPercent() : 0;
    $isDriverCustomerQr = $commissionReferral && (float) $commissionPercent <= 0;
    $commissionLabel = rtrim(rtrim(number_format($commissionPercent, 1, ',', ''), '0'), ',') ?: '0';
    $commissionUrl = $commissionReferral?->landingUrl();
    $commissionQrUrl = $commissionUrl
        ? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=' . rawurlencode($commissionUrl)
        : null;
@endphp
<section class="driver-invite-panel" aria-label="Mã QR">
    <h2 class="driver-panel-title mb-3">Mã QR</h2>

    @if(! ($commissionReferral && $commissionQrUrl))
        <p class="driver-account-hint mb-0">Chưa có mã QR. Liên hệ quản trị viên để được cấp.</p>
    @else
        <div class="driver-invite-qr-grid">
            <div class="driver-invite-qr-card">
                <h3 class="driver-invite-qr-card__title">
                    {{ $isDriverCustomerQr ? 'QR khách của tôi' : 'QR hoa hồng' }}
                </h3>
                <p class="driver-account-hint mb-2">
                    Mã <strong>{{ $commissionReferral->code }}</strong>
                    @if($isDriverCustomerQr)
                        — khách quét và hoàn thành chuyến sẽ vào danh sách Khách của tôi; đặt lại sẽ ưu tiên bạn nhận trước.
                    @else
                        — hoa hồng <strong>{{ $commissionLabel }}%</strong> khi khách đặt qua mã này.
                    @endif
                </p>
                <div class="driver-invite-qr-wrap mb-0">
                    <div class="driver-invite-qr"
                         data-invite-url="{{ $commissionUrl }}"
                         aria-label="Mã QR {{ $commissionReferral->code }}">
                        <img src="{{ $commissionQrUrl }}"
                             width="180"
                             height="180"
                             alt="Mã QR {{ $commissionReferral->code }}"
                             decoding="async">
                    </div>
                    @if($isDriverCustomerQr)
                        <p class="driver-invite-qr-badge mb-0">Khách của tôi</p>
                    @else
                        <p class="driver-invite-qr-badge driver-invite-qr-badge--commission mb-0">HH {{ $commissionLabel }}%</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</section>
