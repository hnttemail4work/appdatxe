@php
    use App\Support\AppBrandingSettings;
@endphp
<div id="pwa-install-banner" class="pwa-install-banner" aria-live="polite">
    <div class="pwa-install-banner__collapse">
    <div class="pwa-install-banner__inner">
        <div class="pwa-install-banner__icon" aria-hidden="true">
            <img src="{{ AppBrandingSettings::appIconAssetUrl() }}" alt="">
        </div>
        <div class="pwa-install-banner__text">
            <p class="pwa-install-banner__title" data-pwa-install-title>{{ $pwaInstallTitle ?? 'Thêm vào màn hình chính' }}</p>
            <p class="pwa-install-banner__hint">{{ $pwaInstallHint ?? 'Mở nhanh như ứng dụng và nhận thông báo chuyến.' }}</p>
        </div>
        <div class="pwa-install-banner__actions">
            <button type="button" class="btn btn-sm btn-outline-light" data-pwa-dismiss>Để sau</button>
            <button type="button" class="btn btn-sm btn-warning fw-semibold" data-pwa-install>Cài đặt</button>
            <button type="button" class="btn btn-sm btn-outline-warning" data-pwa-enable-push>Bật TB</button>
        </div>
    </div>
    </div>
</div>

<div id="pwa-ios-hint" class="pwa-ios-hint">
    <div class="pwa-ios-hint__collapse">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
            <p class="mb-2"><strong>iPhone/iPad — ghim {{ $pwaAudienceLabel ?? 'ứng dụng' }}:</strong></p>
            <ol class="pwa-ios-hint__steps mb-2">
                <li>Mở trang bằng <strong>Safari</strong> (không Chrome, Zalo, Facebook).</li>
                <li>Nhấn <strong>Chia sẻ</strong> (mũi tên lên, ở thanh dưới Safari).</li>
                <li>Cuộn xuống chọn <strong>Thêm vào Màn hình chính</strong> (không phải Thêm dấu trang).</li>
                <li>Nhấn <strong>Thêm</strong> góc phải trên.</li>
            </ol>
            <p class="pwa-ios-hint__note mb-0" data-pwa-ios-warning hidden></p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-light flex-shrink-0" data-pwa-ios-dismiss>Đóng</button>
    </div>
    </div>
</div>
