@php
    $profile = $booking->schedule?->designatedDriverProfile();
    $driverUser = $profile?->user;
@endphp

@if($booking->isTripOverdueStuck())
    <div class="d-flex flex-column gap-2">
        @if($driverUser)
            <button type="button"
                    class="btn btn-sm btn-outline-warning operator-contact-btn"
                    data-contact-role="Tài xế"
                    data-contact-name="{{ $driverUser->name }}"
                    data-contact-phone="{{ $driverUser->phone }}">
                Liên hệ
            </button>
            <div class="cell-muted small">Gọi nhắc tài xế hoàn thành chuyến</div>
        @else
            <span class="text-muted small">Chưa có tài xế</span>
        @endif
        @include('partials.operator-booking-dismiss-form', ['booking' => $booking])
    </div>
@elseif($booking->isInOperatorPendingQueue() && ! $booking->hasDriverAccepted())
    <div class="d-flex flex-column gap-2">
        @include('partials.operator-booking-assign', [
            'booking' => $booking,
            'drivers' => $drivers,
            'bookingList' => 'pending',
        ])
        @if($booking->isOperatorDismissible())
            @include('partials.operator-booking-dismiss-form', ['booking' => $booking])
        @endif
    </div>
@else
    <span class="text-muted">—</span>
@endif
