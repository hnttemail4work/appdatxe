@php
    $profile = $booking->schedule?->designatedDriverProfile();
    $driverUser = $profile?->user;
@endphp

@if($booking->isTripOverdueStuck())
    @if($driverUser)
        <button type="button"
                class="btn btn-sm btn-outline-warning operator-contact-btn"
                data-contact-role="Tài xế"
                data-contact-name="{{ $driverUser->name }}"
                data-contact-phone="{{ $driverUser->phone }}">
            Liên hệ
        </button>
        <div class="cell-muted small mt-1">Gọi nhắc tài xế hoàn thành chuyến</div>
    @else
        <span class="text-muted small">Chưa có tài xế</span>
    @endif
@elseif($booking->isInOperatorPendingQueue() && ! $booking->hasDriverAccepted())
    @include('partials.operator-booking-assign', [
        'booking' => $booking,
        'drivers' => $drivers,
        'bookingList' => 'pending',
    ])
@else
    <span class="text-muted">—</span>
@endif
