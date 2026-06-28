@php
    $shareUrl = $shareUrl ?? '';
    $shareLabel = $shareLabel ?? 'Chia sẻ đặt vé';
    $modalId = $modalId ?? ('shareQrModal-' . md5($shareUrl . $shareLabel));
@endphp

<div class="modal fade share-qr-modal" id="{{ $modalId }}" tabindex="-1"
     aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" id="{{ $modalId }}-label">{{ $shareLabel }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center pt-2">
                <p class="text-muted small mb-3">Quét mã QR để mở trang đặt vé</p>
                <div class="d-flex justify-content-center mb-3">
                    <div class="share-qr-canvas p-2 bg-white border rounded"
                         data-share-qr data-url="{{ $shareUrl }}"></div>
                </div>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control share-url-input font-monospace"
                           value="{{ $shareUrl }}" readonly
                           onclick="this.select()" aria-label="Link đặt vé">
                    <button type="button" class="btn btn-outline-secondary share-copy-btn">Copy</button>
                </div>
            </div>
        </div>
    </div>
</div>
