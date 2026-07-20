@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$stage = $schedule->resolvedDriverStage();
$showActions = $showActions ?? true;
$needsAction = $showActions && in_array($phase, ['upcoming', 'active'], true);
$isRunning = in_array($phase, ['upcoming', 'active'], true);
$primaryBooking = $bookings->first();

$useLiveSheet = in_array($stage, [
    \App\Models\Schedule::DRIVER_STAGE_ASSIGNED,
    \App\Models\Schedule::DRIVER_STAGE_AT_PICKUP,
    \App\Models\Schedule::DRIVER_STAGE_PICKED_UP,
    \App\Models\Schedule::DRIVER_STAGE_RUNNING,
], true) && $phase !== 'settled';
@endphp

@if($useLiveSheet)
    {{-- Đi đón / đã đến / đang di chuyển: cùng sheet peek/full. --}}
    @include('partials.driver-pickup-sheet', ['schedule' => $schedule])
@else
<article class="driver-trip-card driver-trip-card--compact {{ $needsAction ? 'is-action' : '' }} {{ $isRunning ? 'is-running' : '' }}" data-schedule-id="{{ $schedule->id }}">
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $primaryBooking?->driverPickupDetailLabel() ?: $schedule->routeDepartureLabel(),
                'to' => $primaryBooking?->driverDropoffDetailLabel() ?: $schedule->routeDestinationLabel(),
            ])
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">
                    Mã chuyến: <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code>
                </div>
            @endif
            @php
                $pickupTimeLabel = $primaryBooking?->pickupTimeLabel();
                $pickupDateLabel = $primaryBooking?->driverPickupDateLabel();
            @endphp
            @if($primaryBooking?->isScheduledPickup() && ($pickupTimeLabel || $pickupDateLabel))
                <div class="meta driver-schedule-pickup-meta">
                    @if($pickupTimeLabel)
                        <span>Giờ đón: <strong>{{ $pickupTimeLabel }}</strong></span>
                    @endif
                    @if($pickupDateLabel)
                        <span>Ngày đi: <strong>{{ $pickupDateLabel }}</strong></span>
                    @endif
                </div>
            @elseif($primaryBooking)
                <div class="meta driver-schedule-pickup-meta">
                    <span><strong>{{ $primaryBooking->pickupModeLabel() }}</strong></span>
                </div>
            @endif
        </div>
        <div class="driver-card-top-aside">
            <span class="status-pill status-pill--{{ $schedule->driverWorkflowColor() }}">{{ $schedule->driverWorkflowLabel() }}</span>
        </div>
    </div>

    <div class="driver-card-body driver-card-body--compact">
        @foreach($bookings as $booking)
            @include('partials.driver-passenger-info-panel', [
                'booking' => $booking,
                'showCall' => true,
            ])
        @endforeach
        @if(($showTripTotal ?? ($showActions ?? true)) && $phase !== 'settled' && $bookings->isNotEmpty())
            @php
                $tripTotal = (float) $bookings->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
            @endphp
            <div class="driver-pax-total">
                Tổng chuyến: <strong>{{ number_format($tripTotal, 0, ',', '.') }} đ</strong>
            </div>
        @endif
    </div>

    @if($showActions && $phase !== 'settled')
    <div class="driver-card-actions driver-card-actions--compact">
        @include('partials.driver-schedule-workflow', ['schedule' => $schedule])
    </div>
    @endif
</article>
@endif
