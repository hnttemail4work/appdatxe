@php
/** @var \App\Models\DriverTripRequest $req */
/** @var \App\Models\Booking|null $reqBooking */
$reqBooking = $reqBooking ?? $req->relatedBooking();
$mode = $reqBooking?->booking_mode ?? 'shared';
$modeLabel = $reqBooking?->bookingModeLabel() ?? 'Ghép xe';
$modeBadge = $mode === 'whole_car' ? 'primary' : 'info text-dark';
$schedule = $req->schedule;
$walletBlocked = (bool) ($walletBlockReason ?? null);
@endphp
<div class="driver-request-card" data-request-id="{{ $req->id }}">
    <div class="driver-card-top">
        <div>
            <div class="route">{{ $schedule->route->departure }} → {{ $schedule->route->destination }}</div>
            <div class="meta">{{ $schedule->tripMetaLabel() }}</div>
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">Mã chuyến · <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code></div>
            @endif
            @if($label = $req->acceptTimeRemainingLabel())
                <div class="meta text-warning">Còn {{ $label }} để nhận</div>
            @endif
        </div>
        <div class="driver-card-top-aside text-end">
            <span class="badge bg-warning text-dark">Cuốc mới</span>
        </div>
    </div>
    <div class="driver-card-body">
        @if($reqBooking)
            <div class="mb-2">
                <strong>{{ $reqBooking->passenger_name ?: 'Hành khách' }}</strong>
                <span class="badge bg-{{ $modeBadge }} ms-1">{{ $modeLabel }}</span>
            </div>
            <div class="small">📍 Điểm đón cụ thể: <strong>{{ $reqBooking->driverPickupDetailLabel() }}</strong></div>
            <div class="small">🏁 Điểm trả cụ thể: <strong>{{ $reqBooking->driverDropoffDetailLabel() }}</strong></div>
            @if($label = $reqBooking->seatCountLabel())
                <div class="text-muted small mt-1">{{ $label }}</div>
            @endif
            @if($reqBooking->notes)
                <div class="text-muted small mt-1">📝 {{ $reqBooking->notes }}</div>
            @endif
            <div class="driver-trip-total mt-2">
                Tổng chuyến: <strong>{{ number_format($reqBooking->total_price, 0, ',', '.') }} đ</strong>
            </div>
        @else
            <p class="text-muted small mb-0">Chưa có chi tiết hành khách.</p>
        @endif
    </div>
    <div class="driver-card-actions d-flex gap-2 flex-wrap justify-content-end">
        <form method="POST" action="{{ route('driver.tripRequests.accept', $req) }}">@csrf
            <button class="btn btn-success btn-sm px-4" @if($walletBlocked) disabled @endif>Nhận cuốc</button>
        </form>
        <form method="POST" action="{{ route('driver.tripRequests.reject', $req) }}">@csrf
            <button class="btn btn-driver-reject btn-sm px-4" @if($walletBlocked) disabled @endif>Từ chối</button>
        </form>
    </div>
</div>
