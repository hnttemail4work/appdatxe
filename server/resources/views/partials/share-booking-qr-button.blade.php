@php
    $shareUrl = $shareUrl ?? '';
    $shareLabel = $shareLabel ?? 'QR đặt vé';
    $modalId = $modalId ?? ('shareQrModal-' . md5($shareUrl . $shareLabel));
    $iconOnly = $iconOnly ?? false;
@endphp

<button type="button"
        class="btn btn-outline-primary btn-sm share-booking-btn {{ $iconOnly ? 'share-booking-btn-icon' : '' }}"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}"
        aria-controls="{{ $modalId }}"
        aria-label="{{ $shareLabel }}"
        title="{{ $shareLabel }}">
    @if($iconOnly)
        @include('partials.share-booking-qr-icon')
    @else
        📤 {{ $shareLabel }}
    @endif
</button>
