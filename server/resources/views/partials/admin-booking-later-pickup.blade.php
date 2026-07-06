@php
/** @var \App\Models\Booking $booking */
@endphp
@if($booking->showsLaterPickupReminder())
    <form method="POST"
          action="{{ route('admin.bookings.laterPickup', $booking) }}"
          class="mt-2"
          data-confirm="Tạo chuyến đón khách về cho {{ $booking->passenger_name }}?"
          data-confirm-title="Đón khách"
          data-confirm-ok="Đón khách">
        @csrf
        <button type="submit" class="btn btn-sm btn-warning fw-semibold">Đón khách</button>
    </form>
@elseif($booking->isLaterDeparturePlan() && $booking->later_pickup_dispatched_at)
    <div class="cell-muted small mt-1">Đã tạo chuyến về</div>
@endif
