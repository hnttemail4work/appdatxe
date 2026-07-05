@php
/** @var \App\Models\Schedule $schedule */
/** @var int $driverUserId */

$historyBookings = $schedule->driverHistoryBookingsFor($driverUserId);
$outcome = $schedule->driverHistoryOutcomeFor($driverUserId);
$statusLabel = $schedule->driverHistoryLabelFor($driverUserId);
$statusColor = $schedule->driverHistoryColorFor($driverUserId);
$revenue = $schedule->completedRevenueTotalFor($driverUserId);
$guestCount = $historyBookings->count();
$passengerPreview = $historyBookings
    ->map(fn (\App\Models\Booking $booking): string => $booking->passenger_name ?: 'Khách')
    ->take(2)
    ->implode(', ');
if ($guestCount > 2) {
    $passengerPreview .= ' +' . ($guestCount - 2);
}
$cancelledBooking = $historyBookings->first(
    fn (\App\Models\Booking $booking): bool => in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        || $booking->trip_status === 'cancelled',
);
$completedAt = $historyBookings
    ->first(fn (\App\Models\Booking $booking): bool => $booking->trip_status === 'completed')
    ?->completed_at;
$whenLabel = ($completedAt ?? $schedule->departure_time)->format('H:i · d/m/Y');
@endphp

<article class="driver-trip-card driver-trip-card--history {{ $outcome === 'completed' ? 'is-completed' : '' }} {{ $outcome === 'cancelled_driver' ? 'is-cancelled' : '' }}">
    <div class="driver-card-top driver-history-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $schedule->routeDepartureLabel(),
                'to' => $schedule->routeDestinationLabel(),
            ])
            <div class="driver-history-meta">
                <span class="driver-history-meta-item">{{ $whenLabel }}</span>
                @if($schedule->shortTripCode())
                    <span class="driver-history-meta-item driver-history-meta-code">{{ $schedule->shortTripCode() }}</span>
                @endif
                @if($guestCount > 0)
                    <span class="driver-history-meta-item">{{ $guestCount }} khách</span>
                @endif
            </div>
            @if($passengerPreview !== '')
                <div class="driver-history-passengers">{{ $passengerPreview }}</div>
            @endif
        </div>
        <div class="driver-card-top-aside">
            <span class="status-pill status-pill--{{ $statusColor }}">{{ $statusLabel }}</span>
        </div>
    </div>

    @if($outcome === 'completed' && $revenue > 0)
        <div class="driver-history-card-foot">
            <span class="driver-history-foot-label">Doanh thu chuyến</span>
            <span class="driver-history-foot-amount">+{{ number_format($revenue, 0, ',', '.') }} đ</span>
        </div>
    @elseif($cancelledBooking)
        <div class="driver-history-card-foot driver-history-card-foot--muted">
            @include('partials.booking-cancel-detail', ['booking' => $cancelledBooking])
        </div>
    @endif
</article>
