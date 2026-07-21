@php
    $pricingSettings = $pricingSettings ?? \App\Support\PricingConfig::forAdmin();
@endphp

<form method="POST" action="{{ route('admin.pricingSettings.update') }}" class="console-form">
    @csrf
    <input type="hidden" name="form_scope" value="qr">
    <h3 class="h6 fw-semibold mb-2">Hoa hồng giới thiệu</h3>
    <p class="text-muted small mb-3">% hoa hồng mặc định cho mã người giới thiệu mới.</p>
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
    </div>
    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu rule</button>
</form>
