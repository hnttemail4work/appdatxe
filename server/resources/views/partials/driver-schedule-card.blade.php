@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$showActions = $showActions ?? true;
$needsAction = $showActions && in_array($phase, ['active', 'needs_settle', 'enter_settle_code'], true);
$isRunning = $phase === 'active';
@endphp

<article class="driver-trip-card driver-trip-card--compact {{ $needsAction ? 'is-action' : '' }} {{ $isRunning ? 'is-running' : '' }}">
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            <div class="route">{{ $schedule->route->departure }} → {{ $schedule->route->destination }}</div>
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
        <span class="status-pill status-pill--{{ $schedule->driverWorkflowColor() }}">{{ $schedule->driverWorkflowLabel() }}</span>
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
