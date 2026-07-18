@php
    /** @var \App\Models\DriverProfile $driver */
    /** @var \App\Models\ReferralCode|null $inviteReferral */
    $inviteReferral = $inviteReferral ?? $driver->referralCode;
    /** @var \App\Models\ReferralCode|null $commissionReferral */
    $commissionReferral = $commissionReferral ?? $driver->assignedCommissionCode;
    $inviteFrom = $inviteFrom ?? request('from');

    $discountValue = $inviteReferral
        ? $inviteReferral->customerDiscountPercent()
        : \App\Support\PlatformFees::driverInviteQrDiscountPercent();
    $discountLabel = \App\Models\ReferralCode::formatPercentLabel((float) $discountValue);
    $inviteUrl = $inviteReferral?->landingUrl();
    $qrImageUrl = $inviteUrl
        ? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=' . rawurlencode($inviteUrl)
        : null;

    $commissionUrl = $commissionReferral?->landingUrl();
    $commissionQrUrl = $commissionUrl
        ? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=' . rawurlencode($commissionUrl)
        : null;
@endphp

<section class="driver-qr-admin" aria-label="QR tài xế">
    <div class="driver-qr-admin__grid">
        <article class="driver-qr-card">
            <header class="driver-qr-card__head">
                <h3 class="driver-qr-card__title mb-0">QR giảm giá</h3>
                @if($inviteReferral)
                    <span class="status-pill status-pill--{{ $inviteReferral->statusColor() }}">{{ $inviteReferral->statusLabel() }}</span>
                @endif
            </header>

            @if($inviteReferral && $qrImageUrl)
                <div class="driver-qr-card__body">
                    <div class="driver-invite-admin__qr">
                        <img src="{{ $qrImageUrl }}" width="148" height="148"
                             alt="QR {{ $inviteReferral->code }}" decoding="async">
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.drivers.invite.update', $driver) }}" class="driver-qr-card__form">
                    @csrf
                    @method('PATCH')
                    @if($inviteFrom === 'referrals')
                        <input type="hidden" name="from" value="referrals">
                    @endif
                    <label class="form-label" for="driver-invite-discount">% giảm giá</label>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <div class="input-group" style="max-width: 9rem;">
                            <input type="number"
                                   name="customer_discount_percent"
                                   id="driver-invite-discount"
                                   class="form-control @error('customer_discount_percent') is-invalid @enderror"
                                   min="0" max="100" step="0.1"
                                   value="{{ old('customer_discount_percent', number_format($discountValue, 1, '.', '')) }}"
                                   required>
                            <span class="input-group-text">%</span>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">Lưu</button>
                        @if($inviteReferral->isSuspended())
                            <button type="submit"
                                    form="driver-invite-restore-form"
                                    class="btn btn-outline-primary btn-sm">Dùng</button>
                        @else
                            <button type="submit"
                                    form="driver-invite-suspend-form"
                                    class="btn btn-outline-danger btn-sm"
                                    data-confirm="Tạm ngưng QR giảm {{ $discountLabel }}? QR sẽ ẩn khỏi Mời bạn bè."
                                    data-confirm-title="Tạm ngưng QR"
                                    data-confirm-variant="danger"
                                    data-confirm-ok="Ngưng">Ngưng</button>
                        @endif
                    </div>
                    @error('customer_discount_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
                @if($inviteReferral->isSuspended())
                    <form method="POST" action="{{ route('admin.drivers.invite.restore', $driver) }}" id="driver-invite-restore-form" class="d-none">
                        @csrf
                        @if($inviteFrom === 'referrals')
                            <input type="hidden" name="from" value="referrals">
                        @endif
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.drivers.invite.suspend', $driver) }}" id="driver-invite-suspend-form" class="d-none">
                        @csrf
                        @if($inviteFrom === 'referrals')
                            <input type="hidden" name="from" value="referrals">
                        @endif
                    </form>
                @endif
            @else
                <form method="POST" action="{{ route('admin.drivers.invite.store', $driver) }}" class="driver-qr-card__form">
                    @csrf
                    @if($inviteFrom === 'referrals')
                        <input type="hidden" name="from" value="referrals">
                    @endif
                    <label class="form-label" for="driver-invite-discount">% giảm giá</label>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <div class="input-group" style="max-width: 9rem;">
                            <input type="number"
                                   name="customer_discount_percent"
                                   id="driver-invite-discount"
                                   class="form-control @error('customer_discount_percent') is-invalid @enderror"
                                   min="0" max="100" step="0.1"
                                   value="{{ old('customer_discount_percent', number_format($discountValue, 1, '.', '')) }}"
                                   required>
                            <span class="input-group-text">%</span>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">Tạo QR</button>
                    </div>
                    @error('customer_discount_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            @endif
        </article>

        <article class="driver-qr-card {{ $commissionReferral ? '' : 'driver-qr-card--muted' }}">
            <header class="driver-qr-card__head">
                <h3 class="driver-qr-card__title mb-0">QR hoa hồng</h3>
                @if($commissionReferral)
                    <span class="status-pill status-pill--{{ $commissionReferral->statusColor() }}">{{ $commissionReferral->statusLabel() }}</span>
                @endif
            </header>

            @if($commissionReferral && $commissionQrUrl)
                <div class="driver-qr-card__body">
                    <div class="driver-invite-admin__qr">
                        <img src="{{ $commissionQrUrl }}" width="148" height="148"
                             alt="QR {{ $commissionReferral->code }}" decoding="async">
                    </div>
                </div>
            @endif
        </article>
    </div>
</section>
