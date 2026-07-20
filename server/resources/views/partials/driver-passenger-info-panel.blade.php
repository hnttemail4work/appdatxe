@php
/** @var \App\Models\Booking $booking */
/** @var bool $showCall Hiện nút gọi — chỉ sau khi TX đã đến điểm đón. */
$showCall = (bool) ($showCall ?? false);
$chatOpen = app(\App\Services\TripChatService::class)->isOpen($booking);
$phoneRaw = trim((string) ($booking->contact_phone ?? ''));
$phoneTel = $phoneRaw !== '' ? preg_replace('/[^\d+]/', '', $phoneRaw) : '';
$phoneDisplay = $phoneRaw !== '' ? $phoneRaw : '';
$bookingKey = $booking->booking_reference ?: (string) $booking->id;
$profile = $booking->passengerProfileDetail();
$name = $booking->passenger_name ?: 'Hành khách';
$hasActions = ($showCall && $phoneTel) || $chatOpen;
$focusLat = $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null;
$focusLng = $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null;
$hasFocus = $focusLat !== null && $focusLng !== null;
@endphp

<section class="driver-pax-panel {{ $hasActions ? 'driver-pax-panel--has-actions' : '' }}{{ $hasFocus ? ' driver-pax-panel--focusable' : '' }}"
         @if($hasFocus)
         data-passenger-focus
         data-focus-lat="{{ $focusLat }}"
         data-focus-lng="{{ $focusLng }}"
         role="button"
         tabindex="0"
         aria-label="Nhấn để xem vị trí khách trên bản đồ; nhấn lần nữa để thu sheet và xem khoảng cách"
         title="Nhấn: zoom khách · Nhấn lại: thu sheet + khoảng cách"
         @endif
         @if($showCall && $phoneTel) data-driver-call-root data-booking-key="{{ $bookingKey }}" data-booking-reference="{{ $booking->booking_reference }}" data-phone-tel="{{ $phoneTel }}" data-phone-display="{{ $phoneDisplay }}" data-call-log-url="{{ route('driver.bookings.callLog', $booking) }}" @endif>
    <div class="driver-pax-stops" aria-label="Lộ trình">
        <div class="driver-pax-stop driver-pax-stop--pickup">
            <span class="driver-pax-stop__rail" aria-hidden="true"></span>
            <span class="driver-pax-stop__marker" aria-hidden="true"></span>
            <div class="driver-pax-stop__body">
                <span class="driver-pax-stop__label">Điểm đón</span>
                <p class="driver-pax-stop__address">{{ $booking->driverPickupDetailLabel() }}</p>
            </div>
        </div>
        <div class="driver-pax-stop driver-pax-stop--dropoff">
            <span class="driver-pax-stop__marker" aria-hidden="true"></span>
            <div class="driver-pax-stop__body">
                <span class="driver-pax-stop__label">Điểm trả</span>
                <p class="driver-pax-stop__address">{{ $booking->driverDropoffDetailLabel() }}</p>
            </div>
        </div>
    </div>

    <div class="driver-pax-card">
        <div class="driver-pax-card__info">
            <strong class="driver-pax-card__name">{{ $name }}</strong>
            @if($profile)
                <div class="driver-pax-card__profile">{{ $profile }}</div>
            @endif
        </div>

        <div class="driver-pax-card__actions">
            @if($showCall && $phoneTel)
                <a href="#"
                   class="driver-pax-card__call"
                   data-driver-call-btn
                   aria-label="Gọi khách qua app"
                   title="Gọi khách qua app">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <span class="visually-hidden" data-driver-call-label>Gọi</span>
                </a>
            @endif

            @if($chatOpen)
                <div class="trip-chat-slot driver-pax-card__chat">
                    @include('partials.trip-chat-panel', [
                        'mode' => 'driver',
                        'booking' => $booking,
                    ])
                </div>
            @endif
        </div>
    </div>

    @if($showCall && $phoneTel)
        <p class="driver-trip-call__reveal d-none" data-driver-call-reveal>
            <span class="driver-trip-call__hint">Gọi app 2 lần chưa được? Lần 3 gọi số điện thoại:</span>
            <a href="tel:{{ $phoneTel }}" class="driver-trip-call__number">{{ $phoneDisplay }}</a>
        </p>
    @endif

    @if($booking->notes)
        <p class="driver-pax-note">{{ $booking->notes }}</p>
    @endif
</section>
