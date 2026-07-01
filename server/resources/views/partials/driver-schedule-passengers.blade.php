@php
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $bookings */
$bookings = $bookings ?? $schedule->driverRelevantBookings();
$tripTotal = $tripTotal ?? (float) $bookings->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
$showTripTotal = $showTripTotal ?? true;
$phase = $phase ?? $schedule->driverWorkflowPhase();
@endphp

@if($bookings->isEmpty())
    <p class="text-muted small mb-0">Chưa có hành khách trên chuyến này.</p>
@else
    <div class="driver-passenger-list">
        @foreach($bookings as $booking)
        @php
            $mode = $booking->booking_mode ?? 'shared';
            $modeBadge = \App\Support\StatusBadge::bookingMode($mode);
        @endphp
        <div class="driver-passenger-item {{ ! $loop->last ? 'driver-passenger-item--split' : '' }}">
            <div class="driver-passenger-head">
                <strong>{{ $booking->passenger_name ?: 'Hành khách' }}</strong>
                <span class="status-pill status-pill--{{ $modeBadge }}">{{ $booking->bookingModeLabel() }}</span>
            </div>
            @if($booking->passengerProfileDetail())
                <div class="driver-info-line">{{ $booking->passengerProfileDetail() }}</div>
            @endif
            <div class="driver-info-line"><span class="driver-info-k">Đón</span> {{ $booking->pickupTimeLabel() ?? '—' }} · {{ $booking->driverPickupDetailLabel() }}</div>
            <div class="driver-info-line"><span class="driver-info-k">Trả</span> {{ $booking->driverDropoffDetailLabel() }}</div>
            @if(($booking->booking_mode ?? 'shared') === 'shared' && ($label = $booking->seatCountLabel()))
                <div class="driver-info-line">{{ $label }}</div>
            @endif
            @if($booking->notes)
                <div class="driver-info-line driver-info-line--note">{{ $booking->notes }}</div>
            @endif
            @if($showCancelDetail ?? false)
                @include('partials.booking-cancel-detail', ['booking' => $booking])
            @endif
        </div>
        @endforeach
    </div>
    @if($showTripTotal && $phase !== 'settled')
    <div class="driver-trip-total border-top pt-2 mt-2">
        Tổng chuyến: <strong>{{ number_format($tripTotal, 0, ',', '.') }} đ</strong>
    </div>
    @endif
@endif
