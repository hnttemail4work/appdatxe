@php
    $schedule = $booking->schedule;
    $isActiveTrip = ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        && $booking->trip_status !== 'completed';
    $canModify = $isActiveTrip && $booking->adminCanModifyDriverOrCancel();
    $canCancelAfterTimeout = $canModify;
    $waitMinutes = $booking->adminWaitingMinutesRemaining();
@endphp

<div class="admin-booking-actions">
    @if($waitMinutes)
        <div class="admin-booking-wait-hint mb-2">
            Chờ tài xế · còn ~{{ $waitMinutes }} phút
        </div>
    @elseif($booking->adminStillSearchingReplacementDriver())
        <div class="admin-booking-wait-hint mb-2">
            Tài xế đã hủy cuốc — hệ thống đang tìm tài xế khác
        </div>
    @elseif($booking->adminReleasedAfterDriverEngagement())
        <div class="admin-booking-wait-hint admin-booking-wait-hint--expired mb-2">
            Tài xế đã hủy cuốc — có thể hủy chuyến
        </div>
    @elseif($canCancelAfterTimeout)
        <div class="admin-booking-wait-hint admin-booking-wait-hint--expired mb-2">
            @if($booking->operator_help_reason === 'driver_movement_timeout')
                TX chưa xác nhận đi đón — có thể hủy chuyến
            @elseif($booking->operator_help_reason === 'driver_search_timeout')
                Chưa có tài xế nhận — có thể hủy chuyến
            @elseif($booking->operator_help_reason === 'driver_cancelled_trip')
                Tài xế đã hủy cuốc — có thể hủy chuyến
            @elseif($booking->needs_operator_help_at)
                Cần xử lý — có thể hủy chuyến
            @else
                Đã tới khung giờ đón − 1 tiếng — có thể hủy chuyến
            @endif
        </div>
    @endif

    @if($isActiveTrip && $booking->passengerPickedUp())
        <div class="cell-muted small mb-0">
            Đã đón khách — không thể đổi TX
        </div>
    @endif

    @if($canCancelAfterTimeout)
        <form method="POST"
              action="{{ route('admin.bookings.cancel', $booking) }}"
              class="admin-booking-action-form"
              data-confirm="Hủy chuyến này? Khách và tài xế sẽ không còn thấy trên app."
              data-confirm-title="Hủy chuyến"
              data-confirm-variant="danger"
              data-confirm-ok="Hủy chuyến">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                Hủy chuyến
            </button>
        </form>
    @endif
</div>
