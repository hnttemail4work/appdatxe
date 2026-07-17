@php
/** @var \Illuminate\Support\Collection<int, array<string, mixed>> $driverOffers */
$driverOffers = $driverOffers ?? collect();
$canBookNow = auth()->check() && auth()->user()->role === 'customer';
@endphp
<div class="vehicle-select-list" id="trips-list">
    @forelse($driverOffers as $offer)
    @php
        $vehiclePhotoUrl = $offer['sample_photo'] ?? null;
        $capacityLabel = $offer['capacity_label'] ?? '—';
        $typeLabel = $offer['type_label'] ?? '—';
        $bookingActionLabel = $offer['booking_action_label'] ?? 'Đặt sau';
        $bookingActionTone = $offer['booking_action_tone'] ?? 'later';
        $vehicleType = $offer['vehicle_type'] ?? '';
        $capacity = (int) ($offer['capacity'] ?? 0);
        $availableCount = (int) ($offer['available_count'] ?? 0);
        $offerLabel = collect([$typeLabel, $capacityLabel])->filter(fn ($p) => filled($p) && $p !== '—')->implode(' - ');
    @endphp
    <article class="vehicle-select-row trip-card-pro"
             data-capacity="{{ $capacity }}"
             data-vehicle-type="{{ $vehicleType }}">
        <div class="vehicle-select-row__media">
            @if($vehiclePhotoUrl)
                <img src="{{ $vehiclePhotoUrl }}" alt="" class="vehicle-select-row__photo" loading="lazy" decoding="async">
            @else
                <div class="vehicle-select-row__photo vehicle-select-row__photo--empty">{{ strtoupper(substr($vehicleType ?: 'X', 0, 1)) }}</div>
            @endif
        </div>

        <div class="vehicle-select-row__body">
            <div class="vehicle-select-row__title">{{ $typeLabel }}</div>
            <div class="vehicle-select-row__meta">
                <span>{{ $capacityLabel }}</span>
                <span class="vehicle-select-row__dot" aria-hidden="true">·</span>
                <span>{{ $availableCount }} xe khả dụng</span>
            </div>
            <div class="vehicle-select-row__price" data-price-slot>Chọn điểm đi/đến để xem giá</div>
        </div>

        @if($canBookNow)
        <button type="button" class="vehicle-select-row__cta vehicle-select-row__cta--{{ $bookingActionTone }}"
            data-open-booking
            data-capacity="{{ $capacity }}"
            data-vehicle-type="{{ $vehicleType }}"
            data-capacity-label="{{ $capacityLabel }}"
            data-type-label="{{ $typeLabel }}"
            data-vehicle-photo="{{ $vehiclePhotoUrl ?? '' }}"
            data-offer-label="{{ $offerLabel }}">
            <span>{{ $bookingActionLabel }}</span>
        </button>
        @else
        <a href="{{ auth()->check() ? route('dashboard') : route('booking.start') }}" class="vehicle-select-row__cta vehicle-select-row__cta--{{ $bookingActionTone }}">
            <span>{{ auth()->check() ? 'Tài khoản' : 'Đăng nhập' }}</span>
        </a>
        @endif
    </article>
    @empty
    <div class="booking-empty-state booking-empty-state--card booking-empty-state--full">
        <div class="booking-empty-state__icon" aria-hidden="true">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M5 17h14v-4H5v4zM6 13l2-7h8l2 7M7 17v2M17 17v2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h3 class="booking-empty-state__title">Chưa có xe khả dụng.</h3>
    </div>
    @endforelse
</div>
