@php
    $shareUrl = $shareUrl ?? '';
    $shareLabel = $shareLabel ?? 'Chia sẻ đặt vé';
    $modalId = 'shareQrModal-' . md5($shareUrl . $shareLabel);
@endphp

@include('partials.share-booking-qr-button', compact('shareUrl', 'shareLabel', 'modalId'))

@push('modals')
    @include('partials.share-booking-qr-modal', compact('shareUrl', 'shareLabel', 'modalId'))
@endpush
