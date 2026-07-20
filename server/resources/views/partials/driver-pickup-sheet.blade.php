@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$primaryBooking = $bookings->first();
$peekAddress = $primaryBooking?->driverPickupDetailLabel() ?: $schedule->routeDepartureLabel();
@endphp

{{-- Sheet đón khách — mặc định thu nhỏ (peek) để ưu tiên map + banner chỉ đường; vuốt lên xem đầy đủ. --}}
<article class="driver-pickup-sheet is-peek" id="driver-pickup-sheet-{{ $schedule->id }}"
         data-schedule-id="{{ $schedule->id }}" data-driver-pickup-sheet>
    <button type="button" class="driver-pickup-sheet__handle" data-driver-pickup-sheet-handle
            aria-expanded="false" aria-label="Kéo lên để xem đầy đủ thông tin khách">
        <span class="driver-pickup-sheet__grip" aria-hidden="true"></span>
        <span class="driver-pickup-sheet__peek-row">
            <span class="driver-pickup-sheet__peek-text">
                <strong class="driver-pickup-sheet__peek-name">{{ $primaryBooking?->passenger_name ?: 'Hành khách' }}</strong>
                <span class="driver-pickup-sheet__peek-addr">{{ $peekAddress }}</span>
            </span>
            @if($bookings->count() > 1)
                <span class="driver-pickup-sheet__peek-count">+{{ $bookings->count() - 1 }} khách</span>
            @endif
        </span>
    </button>

    <div class="driver-pickup-sheet__body" id="driver-pickup-sheet-body-{{ $schedule->id }}">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $bookings,
            'showTripTotal' => true,
            'omitPickupDupes' => true,
        ])

        @include('partials.driver-trip-quick-actions', [
            'booking' => $primaryBooking,
            'mapNav' => null,
            'inAppNavOnly' => true,
        ])

        @if($schedule->driverCanCancelTrip())
            <form method="POST" action="{{ route('driver.schedules.cancel', $schedule) }}"
                  class="driver-pickup-sheet__cancel cancel-reason-form"
                  data-audience="driver"
                  data-reason-title="Lý do hủy chuyến"
                  data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100">Hủy chuyến</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('driver.schedules.advance', $schedule) }}" class="driver-pickup-sheet__cta">
        @csrf
        <button type="submit" class="btn btn-primary">Đã đến điểm đón</button>
    </form>
</article>
