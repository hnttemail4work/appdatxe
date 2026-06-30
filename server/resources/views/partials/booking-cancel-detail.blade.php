{{-- Lý do / thời điểm hủy — dùng chung quản lý & tài xế --}}
@php
/** @var \App\Models\Booking $booking */
$isCancelled = in_array($booking->booking_status, ['cancelled', 'rejected'], true)
    || $booking->trip_status === 'cancelled';
@endphp

@if($isCancelled)
    <div class="booking-cancel-detail small text-muted mt-1">
        @if($label = $booking->cancelledByLabel())
            <span class="status-pill status-pill--danger me-1">{{ $label }}</span>
        @endif
        @if($booking->cancelled_at)
            <span>{{ $booking->cancelled_at->format('H:i, d/m/Y') }}</span>
        @endif
        @if($reason = $booking->cancellationReasonText())
            <div class="mt-1">Lý do: {{ $reason }}</div>
        @endif
    </div>
@endif
