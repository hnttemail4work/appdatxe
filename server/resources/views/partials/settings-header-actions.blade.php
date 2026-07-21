{{-- Icon Ghim + Thông báo trên header Cài đặt (TX / KH) --}}
@php
    $installId = $installId ?? 'settings-pwa-install';
@endphp
<div class="settings-header-actions" data-settings-header-actions {{ !empty($hidden) ? 'hidden' : '' }}>
    <button type="button"
            class="settings-header-action"
            id="{{ $installId }}"
            data-pwa-install-trigger
            aria-label="Ghim"
            title="Ghim">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 17v5"/>
            <path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6a3 3 0 1 0-6 0Z"/>
        </svg>
        <span class="visually-hidden" data-pwa-install-label>Ghim</span>
    </button>
    <button type="button"
            class="settings-header-action"
            data-pwa-enable-push
            data-pwa-push-label-on="Tắt thông báo"
            data-pwa-push-label-off="Bật thông báo"
            aria-label="Bật thông báo"
            title="Bật thông báo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
        </svg>
        <span class="visually-hidden" data-pwa-push-label>Bật</span>
    </button>
</div>
