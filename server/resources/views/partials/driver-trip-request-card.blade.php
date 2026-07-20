@php
/** @var \App\Models\DriverTripRequest $tripRequest */
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $passengers */

$schedule = $schedule ?? $tripRequest->schedule;
$passengers = $passengers ?? $schedule->driverRelevantBookings();
$tripTotal = (float) $passengers->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
$expiresLabel = $tripRequest->acceptTimeRemainingLabel();
$waitProgress = \App\Support\DriverWaitProgress::forTripRequest($tripRequest);
$primaryBooking = $passengers->first();

$tripDistanceKm = $primaryBooking && $primaryBooking->distance_km
    ? (int) $primaryBooking->distance_km
    : null;
$scheduleChip = null;
if ($primaryBooking) {
    if ($primaryBooking->isScheduledPickup()) {
        $parts = array_filter([
            $primaryBooking->pickupTimeLabel(),
            $primaryBooking->driverPickupDateLabel(),
        ]);
        $scheduleChip = $parts ? implode(' · ', $parts) : 'Đặt lịch';
    } else {
        $scheduleChip = $primaryBooking->pickupModeLabel() ?: 'Đón ngay';
    }
}

$fromLabel = $primaryBooking?->driverPickupDetailLabel() ?: $schedule->routeDepartureLabel();
$toLabel = $primaryBooking?->driverDropoffDetailLabel() ?: $schedule->routeDestinationLabel();
$tripCode = $schedule->shortTripCode();
@endphp

<article class="driver-request-card driver-action-card driver-trip-request-card driver-request-card--incoming driver-offer"
         data-trip-request-id="{{ $tripRequest->id }}"
         @if($tripRequest->expires_at) data-trip-request-expires="{{ $tripRequest->expires_at->toIso8601String() }}" @endif>
    <header class="driver-offer-head">
        <h2 class="driver-offer-head__title">Cuốc mới — chờ nhận</h2>
        <div class="driver-offer-head__meta">
            @if($tripCode)
                <span>Mã cuốc xe: <strong>{{ $tripCode }}</strong></span>
            @endif
            @if($primaryBooking?->passenger_name)
                <span>Hành khách: <strong>{{ $primaryBooking->passenger_name }}</strong></span>
            @endif
        </div>
        @if($passengers->count() > 0)
            <div class="driver-offer-head__count">{{ $passengers->count() }} khách</div>
        @endif
    </header>

    <div class="driver-offer-card">
        <div class="driver-offer-card__row">
            <span class="driver-offer-card__label">Tổng thu nhập</span>
            <strong class="driver-offer-card__fare">{{ number_format($tripTotal, 0, ',', '.') }} đ</strong>
        </div>
        @if($tripDistanceKm)
            <div class="driver-offer-card__row">
                <span class="driver-offer-card__label">Quãng đường</span>
                <strong class="driver-offer-card__distance">{{ $tripDistanceKm }} km</strong>
            </div>
        @endif

        <div class="driver-offer-route">
            <div class="driver-offer-route__rail" aria-hidden="true">
                <span class="driver-offer-route__pin driver-offer-route__pin--pickup">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M5 11h14l-1.5-4.5h-11L5 11zm1.5 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM7 8.5l.8-2.5h8.4l.8 2.5H7z"/>
                    </svg>
                </span>
                <span class="driver-offer-route__line"></span>
                <span class="driver-offer-route__pin driver-offer-route__pin--dropoff">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/>
                    </svg>
                </span>
            </div>
            <div class="driver-offer-route__stops">
                <div class="driver-offer-route__stop">
                    <span class="driver-offer-route__tag">Đón</span>
                    <strong class="driver-offer-route__addr">{{ $fromLabel }}</strong>
                    @if($scheduleChip)
                        <span class="driver-offer-route__sub">{{ $scheduleChip }}</span>
                    @endif
                </div>
                <div class="driver-offer-route__stop">
                    <span class="driver-offer-route__tag">Trả</span>
                    <strong class="driver-offer-route__addr">{{ $toLabel }}</strong>
                </div>
            </div>
        </div>

        @if($primaryBooking?->notes)
            <p class="driver-offer-card__note mb-0">{{ $primaryBooking->notes }}</p>
        @endif
    </div>

    @if($waitProgress)
        @include('partials.wait-progress', [
            'waitProgress' => array_merge($waitProgress, [
                'label' => 'Khách đang chờ bạn',
                'hint' => null,
            ]),
            'variant' => 'driver',
            'layout' => 'ring',
        ])
    @elseif($expiresLabel)
        <div class="driver-wait driver-wait--trip_accept driver-wait--ring" role="status">
            <div class="driver-wait-ring" aria-hidden="true">
                <svg viewBox="0 0 96 96">
                    <circle class="driver-wait-ring__track" cx="48" cy="48" r="40"/>
                    <circle class="driver-wait-ring__fill" cx="48" cy="48" r="40"/>
                </svg>
                <span class="driver-wait-ring__time">{{ $expiresLabel }}</span>
            </div>
            <div class="driver-wait-ring__label">Khách đang chờ bạn</div>
        </div>
    @endif

    <div class="driver-card-actions driver-card-actions--job driver-request-card__actions driver-offer-actions">
        <div class="driver-workflow-compact-actions">
            <form method="POST" action="{{ route('driver.tripRequests.reject', $tripRequest) }}"
                  class="driver-workflow-compact-action driver-reject-form"
                  data-audience="driver"
                  data-reason-title="Lý do từ chối"
                  data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">
                @csrf
                <button type="submit" class="btn driver-offer-btn driver-offer-btn--reject">
                    Hủy chuyến
                </button>
            </form>
            <form method="POST" action="{{ route('driver.tripRequests.accept', $tripRequest) }}"
                  class="driver-workflow-compact-action driver-accept-form driver-request-card__accept-form">
                @csrf
                <button type="submit" class="btn driver-offer-btn driver-offer-btn--accept driver-btn-accept driver-request-card__accept-btn">
                    Nhận chuyến
                </button>
            </form>
        </div>
    </div>
</article>
