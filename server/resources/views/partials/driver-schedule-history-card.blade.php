@php
/** @var \App\Models\Schedule $schedule */
/** @var int $driverUserId */
$bookings = $schedule->driverHistoryBookingsFor($driverUserId);
$outcome = $schedule->driverHistoryOutcomeFor($driverUserId);
$revenue = $schedule->completedRevenueTotalFor($driverUserId);
@endphp

<article class="driver-trip-card driver-trip-card--compact driver-trip-card--history">
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $schedule->route->departure,
                'to' => $schedule->route->destination,
            ])
            <div class="meta">
                {{ $schedule->departure_time->format('H:i, d/m/Y') }}
                @if($bookings->count() > 0)
                    <span class="ms-1">{{ $bookings->count() }} khách</span>
                @endif
            </div>
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">
                    Mã chuyến: <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code>
                </div>
            @endif
        </div>
        <span class="status-pill status-pill--{{ $schedule->driverHistoryColorFor($driverUserId) }}">{{ $schedule->driverHistoryLabelFor($driverUserId) }}</span>
    </div>

    <div class="driver-card-body driver-card-body--compact">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $bookings,
            'showTripTotal' => false,
            'showCancelDetail' => true,
            'phase' => $outcome === 'completed' ? 'settled' : 'other',
        ])

        @if($outcome === 'completed' && $revenue > 0)
            <div class="driver-trip-total border-top pt-2 mt-2">
                Doanh thu: <strong class="text-success">{{ number_format($revenue, 0, ',', '.') }} đ</strong>
            </div>
        @endif
    </div>
</article>
