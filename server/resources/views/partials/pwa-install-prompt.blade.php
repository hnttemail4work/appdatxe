<div id="pwa-install-banner" class="pwa-install-banner" aria-live="polite">
    <div class="pwa-install-banner__inner">
        <div class="pwa-install-banner__icon" aria-hidden="true">
            <img src="{{ asset('favicon.svg') }}" alt="">
        </div>
        <div class="pwa-install-banner__text">
            <p class="pwa-install-banner__title" data-pwa-install-title>{{ $pwaInstallTitle ?? 'Thêm vào màn hình chính' }}</p>
            <p class="pwa-install-banner__hint">{{ $pwaInstallHint ?? 'Mở nhanh như ứng dụng và nhận thông báo chuyến.' }}</p>
        </div>
        <div class="pwa-install-banner__actions">
            <button type="button" class="btn btn-sm btn-outline-light" data-pwa-dismiss>Để sau</button>
            <button type="button" class="btn btn-sm btn-warning fw-semibold" data-pwa-install>Cài đặt</button>
            <button type="button" class="btn btn-sm btn-outline-warning d-none" data-pwa-enable-push>Bật TB</button>
        </div>
    </div>
</div>

<div id="pwa-ios-hint" class="pwa-ios-hint">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <p><strong>iPhone/iPad:</strong> nhấn <strong>Chia sẻ</strong> → <strong>Thêm vào Màn hình chính</strong> để ghim {{ $pwaAudienceLabel ?? 'ứng dụng' }}.</p>
        <button type="button" class="btn btn-sm btn-outline-light flex-shrink-0" data-pwa-ios-dismiss>Đóng</button>
    </div>
</div>
