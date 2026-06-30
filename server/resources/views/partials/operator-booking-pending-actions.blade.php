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
        <form method="POST"
              action="{{ route('operator.bookings.dismissStuck', $booking) }}"
              data-confirm="Ẩn chuyến treo này khỏi danh sách? Hệ thống sẽ tự xóa sau {{ \App\Services\OperatorBookingDismissService::RETENTION_DAYS }} ngày."
              data-confirm-title="Ẩn chuyến treo"
              data-confirm-variant="danger"
              data-confirm-ok="Ẩn">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-secondary">Ẩn</button>
        </form>
    </div>
@elseif($booking->isInOperatorPendingQueue() && ! $booking->hasDriverAccepted())
    @include('partials.operator-booking-assign', [
        'booking' => $booking,
        'drivers' => $drivers,
        'bookingList' => 'pending',
    ])
@else
    <span class="text-muted">—</span>
@endif
