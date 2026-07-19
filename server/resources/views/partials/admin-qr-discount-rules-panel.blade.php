@php
    $pricingSettings = $pricingSettings ?? \App\Support\PricingConfig::forAdmin();
@endphp

<form method="POST" action="{{ route('admin.pricingSettings.update') }}" class="console-form">
    @csrf
    <input type="hidden" name="form_scope" value="qr">
    <h3 class="h6 fw-semibold mb-2">Rule giảm giá QR</h3>
    <p class="text-muted small mb-3">% mặc định cho mã giới thiệu / QR mời tài xế. Không đổi cách áp dụng khi đặt xe.</p>
    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <label class="form-label">Hoa hồng GT mặc định (%)</label>
            <div class="input-group">
                <input type="number" name="referral_commission_first" class="form-control @error('referral_commission_first') is-invalid @enderror"
                       min="0" max="100" step="0.1"
                       value="{{ old('referral_commission_first', $pricingSettings['referral_commission_first']) }}" required>
                <span class="input-group-text">%</span>
            </div>
            @error('referral_commission_first')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="form-label">Giảm giá QR mã vé (%)</label>
            <div class="input-group">
                <input type="number" name="booking_qr_discount" class="form-control @error('booking_qr_discount') is-invalid @enderror"
                       min="0" max="100" step="0.1"
                       value="{{ old('booking_qr_discount', $pricingSettings['booking_qr_discount']) }}" required>
                <span class="input-group-text">%</span>
            </div>
            @error('booking_qr_discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="form-label">Giảm giá QR mời TX (%)</label>
            <div class="input-group">
                <input type="number" name="driver_invite_qr_discount" class="form-control @error('driver_invite_qr_discount') is-invalid @enderror"
                       min="0" max="100" step="0.1"
                       value="{{ old('driver_invite_qr_discount', $pricingSettings['driver_invite_qr_discount']) }}" required>
                <span class="input-group-text">%</span>
            </div>
            @error('driver_invite_qr_discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="sync_driver_invite_discount" value="1" id="sync-driver-invite-qr">
                <label class="form-check-label" for="sync-driver-invite-qr">Đồng bộ % QR TX hiện có</label>
            </div>
        </div>
    </div>
    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu rule</button>
</form>
