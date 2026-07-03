@php
/** @var \App\Models\Schedule $schedule */
/** @var int $driverUserId */
$revenue = $schedule->completedRevenueTotalFor($driverUserId);
@endphp

<article class="driver-trip-card driver-trip-card--compact driver-trip-card--history">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            @if($schedule->shortTripCode())
                <div class="fw-semibold">Mã chuyến: <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code></div>
            @endif
            <div class="small text-muted">{{ $schedule->departure_time->format('d/m/Y') }}</div>
        </div>
        <div class="fw-bold text-success">{{ number_format($revenue, 0, ',', '.') }} đ</div>
    </div>
</article>
