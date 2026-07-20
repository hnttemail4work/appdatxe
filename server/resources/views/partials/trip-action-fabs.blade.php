@php
    $hotlinePhone = (string) ($hotlinePhone ?? config('app.contact_phone'));
    $hotlineTel = preg_replace('/[^\d+]/', '', $hotlinePhone);
    $showLocateBtn = (bool) ($showLocateBtn ?? false);
    $inTrip = (bool) ($inTrip ?? false);
    $showSos = (bool) ($showSos ?? true);
@endphp

@if($showSos)
{{-- Khẩn cấp (chuông): cột phải, ngay trên nút vị trí — chỉ hiện khi đang trong chuyến. --}}
<a href="tel:{{ $hotlineTel }}"
   class="trip-sos-fab {{ $inTrip ? '' : 'd-none' }}"
   data-trip-sos-fab
   data-contact-hotline="phone"
   aria-label="Gọi khẩn cấp tổng đài {{ $hotlinePhone }}"
   title="Cảnh báo khẩn cấp"
   @if(! $inTrip) hidden @endif>
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
    </svg>
</a>
@endif

@if($showLocateBtn)
    <button type="button"
            class="trip-locate-fab d-none"
            id="guest-trip-locate-btn"
            aria-label="Về vị trí hiện tại của bạn"
            title="Vị trí hiện tại của bạn"
            hidden>
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
            <circle cx="12" cy="12" r="8"/>
        </svg>
    </button>
@endif
