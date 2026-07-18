@php
/** @var \App\Models\Booking|null $booking */
/** @var array{label: string, url: string}|null $mapNav */
$booking = $booking ?? null;
$mapNav = $mapNav ?? null;
$chatOpen = $booking
    ? app(\App\Services\TripChatService::class)->isOpen($booking)
    : false;
$phoneRaw = trim((string) ($booking?->contact_phone ?? ''));
$phoneTel = $phoneRaw !== '' ? preg_replace('/[^\d+]/', '', $phoneRaw) : '';
$phoneDisplay = $phoneRaw !== '' ? $phoneRaw : '';
$bookingKey = $booking?->booking_reference ?: (string) ($booking?->id ?? '');
@endphp
@if($booking || ! empty($mapNav['url']) || ! empty($mapNav['google_url']))
<nav class="driver-trip-quick-actions" aria-label="Liên hệ nhanh"
     @if($phoneTel) data-driver-call-root data-booking-key="{{ $bookingKey }}" data-phone-tel="{{ $phoneTel }}" data-phone-display="{{ $phoneDisplay }}" @endif>
    @if($phoneTel)
        <a href="tel:{{ $phoneTel }}"
           class="driver-trip-quick-action"
           data-driver-call-btn
           aria-label="Gọi khách">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
            </svg>
            <span data-driver-call-label>Gọi</span>
        </a>
    @else
        <span class="driver-trip-quick-action is-disabled" aria-disabled="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
            </svg>
            <span>Gọi</span>
        </span>
    @endif

    @if($chatOpen && $booking)
        <button type="button"
                class="driver-trip-quick-action"
                data-driver-open-chat="{{ $booking->booking_reference }}"
                data-chat-peer="{{ $booking->passenger_name ?: 'Hành khách' }}"
                data-chat-list-url="{{ route('driver.bookings.chat.messages', $booking) }}"
                data-chat-send-url="{{ route('driver.bookings.chat.send', $booking) }}"
                data-chat-open="1"
                aria-label="Chat với khách">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <span>Chat</span>
        </button>
    @else
        <span class="driver-trip-quick-action is-disabled" aria-disabled="true" title="Chat chưa mở">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <span>Chat</span>
        </span>
    @endif

    @if(! empty($mapNav['google_url']) || ! empty($mapNav['url']))
        <a href="{{ $mapNav['google_url'] ?? $mapNav['url'] }}"
           class="driver-trip-quick-action"
           data-driver-map-nav
           data-map-nav-provider="google"
           @if(! empty($mapNav['use_current_origin'])) data-map-nav-use-current-origin="1" @endif
           @if(! empty($mapNav['dest_lat']) && ! empty($mapNav['dest_lng']))
               data-dest-lat="{{ $mapNav['dest_lat'] }}"
               data-dest-lng="{{ $mapNav['dest_lng'] }}"
           @endif
           @if(! empty($mapNav['origin_lat']) && ! empty($mapNav['origin_lng']))
               data-origin-lat="{{ $mapNav['origin_lat'] }}"
               data-origin-lng="{{ $mapNav['origin_lng'] }}"
           @endif
           target="_blank"
           rel="noopener noreferrer"
           aria-label="{{ $mapNav['label'] ?? 'Điều hướng' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
            </svg>
            <span>Điều hướng</span>
        </a>
    @else
        <span class="driver-trip-quick-action is-disabled" aria-disabled="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
            </svg>
            <span>Điều hướng</span>
        </span>
    @endif

    @if($phoneTel)
        <p class="driver-trip-call__reveal d-none" data-driver-call-reveal>
            <span class="driver-trip-call__hint">Gọi 2 lần chưa được? Gọi trực tiếp số khách:</span>
            <a href="tel:{{ $phoneTel }}" class="driver-trip-call__number">{{ $phoneDisplay }}</a>
        </p>
    @endif
</nav>
@endif
