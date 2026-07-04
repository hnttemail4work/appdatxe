@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$showActions = $showActions ?? true;
$needsAction = $showActions && in_array($phase, ['upcoming', 'active'], true);
$isRunning = in_array($phase, ['upcoming', 'active'], true);
$primaryBooking = $bookings->first();
$pickupAt = $primaryBooking?->tripStartAt();
$stage = $schedule->resolvedDriverStage();
$guestDriverStatus = app(\App\Services\GuestBookingDriverStatusService::class);
$driverTripStatus = $primaryBooking ? $guestDriverStatus->build($primaryBooking) : null;
$pickupDistanceLabel = $driverTripStatus['distance_label'] ?? null;
$pickupEtaLabel = $driverTripStatus['eta_label'] ?? null;
$pickupProximityHint = $driverTripStatus['proximity_hint'] ?? null;
@endphp

<article class="driver-trip-card driver-trip-card--compact {{ $needsAction ? 'is-action' : '' }} {{ $isRunning ? 'is-running' : '' }}" data-schedule-id="{{ $schedule->id }}">
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $schedule->route->departure,
                'to' => $schedule->route->destination,
            ])
            <div class="meta">
                @if($pickupAt)
                    Giờ đón: <strong>{{ $pickupAt->format('H:i, d/m/Y') }}</strong>
                @else
                    {{ $schedule->departure_time->format('H:i, d/m/Y') }}
                @endif
                @if($bookings->count() > 0)
                    <span class="ms-1">{{ $bookings->count() }} khách</span>
                @endif
            </div>
            @if($pickupProximityHint)
                <div class="meta driver-schedule-pickup-distance">
                    Đến điểm đón: <strong>{{ $pickupProximityHint }}</strong>
                </div>
            @elseif($pickupDistanceLabel)
                <div class="meta driver-schedule-pickup-distance">
                    Cách điểm đón: <strong>~{{ $pickupDistanceLabel }}</strong>@if($pickupEtaLabel) · dự kiến <strong>{{ $pickupEtaLabel }}</strong>@endif
                </div>
            @endif
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">
                    Mã chuyến: <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code>
                </div>
            @endif
        </div>
        <div class="driver-card-top-aside">
            <span class="status-pill status-pill--{{ $schedule->driverWorkflowColor() }}">{{ $schedule->driverWorkflowLabel() }}</span>
        </div>
    </div>

    <div class="driver-card-body driver-card-body--compact">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $bookings,
            'showTripTotal' => $showTripTotal ?? ($showActions ?? true),
        ])
    </div>

    @if($showActions && $phase !== 'settled')
    <div class="driver-card-actions driver-card-actions--compact">
        @include('partials.driver-schedule-workflow', ['schedule' => $schedule])
    </div>
    @endif
</article>
