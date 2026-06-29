@php
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $bookings */
$bookings = $bookings ?? $schedule->driverRelevantBookings();
$tripTotal = $tripTotal ?? (float) $bookings->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
$showTripTotal = $showTripTotal ?? true;
$phase = $schedule->driverWorkflowPhase();
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
        <div class="driver-passenger-item {{ ! $loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
            <div class="mb-1">
                <strong>{{ $booking->passenger_name ?: 'Hành khách' }}</strong>
                <span class="status-pill status-pill--{{ $modeBadge }} ms-1">{{ $booking->bookingModeLabel() }}</span>
            </div>
            <div class="text-muted small">📍 Điểm đón cụ thể: <strong>{{ $booking->driverPickupDetailLabel() }}</strong></div>
            <div class="text-muted small">🏁 Điểm trả cụ thể: <strong>{{ $booking->driverDropoffDetailLabel() }}</strong></div>
            @if(($booking->booking_mode ?? 'shared') === 'shared' && ($label = $booking->seatCountLabel()))
                <div class="text-muted small">{{ $label }}</div>
            @endif
            @if($booking->notes)
                <div class="text-muted small">📝 {{ $booking->notes }}</div>
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
