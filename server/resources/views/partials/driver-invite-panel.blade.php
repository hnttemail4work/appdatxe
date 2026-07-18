@php
    $inviteUrl = $inviteUrl ?? null;
    $hasInviteQr = is_string($inviteUrl) && $inviteUrl !== '';
    $discountPercent = $hasInviteQr
        ? (float) ($inviteDiscountPercent ?? \App\Support\PlatformFees::driverInviteQrDiscountPercent())
        : null;
    $discountLabel = $discountPercent !== null
        ? (rtrim(rtrim(number_format($discountPercent, 1, ',', ''), '0'), ',') ?: '0')
        : null;
    $appName = \App\Support\AppBrandingSettings::appName();
    $qrImageUrl = $hasInviteQr
        ? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=' . rawurlencode($inviteUrl)
        : null;

    /** @var \App\Models\ReferralCode|null $commissionReferral */
    $commissionReferral = $commissionReferral ?? null;
    $commissionPercent = $commissionReferral ? $commissionReferral->commissionPercent() : 0;
    $commissionLabel = rtrim(rtrim(number_format($commissionPercent, 1, ',', ''), '0'), ',') ?: '0';
    $commissionUrl = $commissionReferral?->landingUrl();
    $commissionQrUrl = $commissionUrl
        ? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=' . rawurlencode($commissionUrl)
        : null;
@endphp
<section class="driver-invite-panel" aria-label="Mời bạn bè">
    <h2 class="driver-panel-title mb-3">Mời bạn bè</h2>

    @if(! $hasInviteQr && ! ($commissionReferral && $commissionQrUrl))
        <p class="driver-account-hint mb-0">Chưa có mã QR giới thiệu. Liên hệ tổng đài nếu cần cấp QR.</p>
    @else
        <div class="driver-invite-qr-grid">
            @if($hasInviteQr && $qrImageUrl && $discountLabel !== null)
                <div class="driver-invite-qr-card">
                    <h3 class="driver-invite-qr-card__title">QR giảm giá khách</h3>
                    <p class="driver-account-hint mb-2">
                        Khách quét được giảm <strong>{{ $discountLabel }}%</strong> khi đặt xe lần đầu qua link của bạn trên {{ $appName }}.
                    </p>
                    <div class="driver-invite-qr-wrap mb-0">
                        <div id="driver-invite-qr"
                             class="driver-invite-qr"
                             data-invite-url="{{ $inviteUrl }}"
                             aria-label="Mã QR giảm {{ $discountLabel }}%">
                            <img src="{{ $qrImageUrl }}"
                                 width="180"
                                 height="180"
                                 alt="Mã QR giảm {{ $discountLabel }}%"
                                 decoding="async">
                        </div>
                        <p class="driver-invite-qr-badge mb-0">Giảm {{ $discountLabel }}%</p>
                    </div>
                </div>
            @endif

            @if($commissionReferral && $commissionQrUrl)
                <div class="driver-invite-qr-card">
                    <h3 class="driver-invite-qr-card__title">QR hoa hồng</h3>
                    <p class="driver-account-hint mb-2">
                        Mã <strong>{{ $commissionReferral->code }}</strong> — hoa hồng <strong>{{ $commissionLabel }}%</strong> khi khách đặt qua mã này.
                    </p>
                    <div class="driver-invite-qr-wrap mb-0">
                        <div class="driver-invite-qr"
                             data-invite-url="{{ $commissionUrl }}"
                             aria-label="Mã QR hoa hồng {{ $commissionLabel }}%">
                            <img src="{{ $commissionQrUrl }}"
                                 width="180"
                                 height="180"
                                 alt="Mã QR hoa hồng {{ $commissionLabel }}%"
                                 decoding="async">
                        </div>
                        <p class="driver-invite-qr-badge driver-invite-qr-badge--commission mb-0">HH {{ $commissionLabel }}%</p>
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
