@php
    $hotlinePhone = (string) ($hotlinePhone ?? config('app.contact_phone'));
    $hotlineTel = preg_replace('/[^\d+]/', '', $hotlinePhone);
    $showLocateBtn = (bool) ($showLocateBtn ?? false);
    $inTrip = (bool) ($inTrip ?? false);
@endphp

{{-- Khẩn cấp: góc phải trên — chỉ hiện khi đang trong chuyến. --}}
<a href="tel:{{ $hotlineTel }}"
   class="trip-sos-fab {{ $inTrip ? '' : 'd-none' }}"
   data-trip-sos-fab
   data-contact-hotline="phone"
   aria-label="Gọi khẩn cấp tổng đài {{ $hotlinePhone }}"
   title="Khẩn cấp"
   @if(! $inTrip) hidden @endif>
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
    </svg>
</a>

{{-- Trò chuyện: góc trái dưới — chỉ hiện khi đang trong chuyến. --}}
<button type="button"
        class="trip-chat-fab {{ $inTrip ? '' : 'd-none' }}"
        data-trip-chat-fab
        aria-label="Trò chuyện"
        title="Trò chuyện"
        @if(! $inTrip) hidden @endif>
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
</button>

@if($showLocateBtn)
    <button type="button"
            class="trip-locate-fab d-none"
            id="guest-trip-locate-btn"
            aria-label="Về vị trí hiện tại"
            title="Vị trí hiện tại"
            hidden>
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
            <circle cx="12" cy="12" r="8"/>
        </svg>
    </button>
@endif
