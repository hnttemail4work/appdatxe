@php
/** @var \App\Models\DriverTripRequest $tripRequest */
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $passengers */

use App\Support\DriverWaitProgress;

$schedule = $schedule ?? $tripRequest->schedule;
$passengers = $passengers ?? $schedule->driverRelevantBookings();
$waitProgress = DriverWaitProgress::forTripRequest($tripRequest);
@endphp

<article class="driver-request-card driver-action-card driver-trip-request-card" data-trip-request-id="{{ $tripRequest->id }}">
    @include('partials.wait-progress', ['waitProgress' => $waitProgress, 'variant' => 'driver'])

    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $schedule->route->departure ?? '',
                'to' => $schedule->route->destination ?? '',
            ])
            <div class="meta">
                {{ $schedule->departure_time->format('H:i, d/m/Y') }}
                @if($passengers->count() > 0)
                    · {{ $passengers->count() }} khách
                @endif
                @if($schedule->shortTripCode())
                    · Mã {{ $schedule->shortTripCode() }}
                @endif
            </div>
        </div>
        <div class="driver-card-top-aside">
            <span class="status-pill status-pill--pending">Cuốc mới</span>
        </div>
    </div>

    <div class="driver-card-body">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $passengers,
            'showTripTotal' => true,
        ])
    </div>

    <div class="driver-card-actions driver-card-actions--job">
        <form method="POST" action="{{ route('driver.tripRequests.accept', $tripRequest) }}" class="driver-accept-form"
              data-confirm="Xác nhận nhận cuốc này?"
              data-confirm-title="Nhận cuốc"
              data-confirm-ok="Nhận cuốc"
              data-confirm-variant="success">
            @csrf
            <button type="submit" class="btn btn-success driver-btn-accept">Nhận cuốc</button>
        </form>
        <form method="POST" action="{{ route('driver.tripRequests.reject', $tripRequest) }}" class="driver-reject-form"
              data-confirm="Từ chối cuốc — hệ thống sẽ gán cho tài xế khác?"
              data-confirm-title="Từ chối cuốc"
              data-confirm-variant="danger"
              data-confirm-ok="Từ chối">
            @csrf
            <button type="submit" class="btn btn-driver-reject-ghost">Từ chối</button>
        </form>
    </div>
</article>
