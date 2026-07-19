@php
/** @var \Illuminate\Support\Collection<int, array<string, mixed>> $driverOffers */
$driverOffers = $driverOffers ?? collect();
$offerCount = $driverOffers->count();
$collapsedVisible = 3;
$extraCount = max(0, $offerCount - $collapsedVisible);
@endphp
<div class="be-vehicle-panel">
    <div class="be-vehicle-list" id="trips-list" role="listbox" aria-label="Chọn loại xe">
        @forelse($driverOffers as $index => $offer)
        @php
            $capacityLabel = $offer['capacity_label'] ?? '—';
            $typeLabel = $offer['type_label'] ?? '—';
            $vehicleType = $offer['vehicle_type'] ?? '';
            $capacity = (int) ($offer['capacity'] ?? 0);
            $offerLabel = $offer['offer_label']
                ?? collect([$typeLabel, $capacityLabel])->filter(fn ($p) => filled($p) && $p !== '—')->implode(' · ');
            $hint = $offer['hint'] ?? ($capacity >= 7 ? 'Rộng rãi, tối đa '.$capacity.' khách' : 'Giá tốt · '.$capacityLabel);
            $iconKey = $offer['icon_key'] ?? 'other';
            $isExtra = $index >= $collapsedVisible;
        @endphp
        <button type="button"
                class="be-vehicle-row{{ $isExtra ? ' be-vehicle-row--extra' : '' }}"
                role="option"
                aria-selected="false"
                data-capacity="{{ $capacity }}"
                data-vehicle-type="{{ $vehicleType }}"
                data-capacity-label="{{ $capacityLabel }}"
                data-type-label="{{ $typeLabel }}"
                data-offer-label="{{ $offerLabel }}"
                data-select-vehicle
                @if($isExtra) hidden @endif>
            <span class="be-vehicle-row__media be-vehicle-row__media--icon be-vehicle-icon be-vehicle-icon--{{ $iconKey }}" aria-hidden="true">
                @include('partials.booking-vehicle-icon', ['iconKey' => $iconKey])
            </span>
            <span class="be-vehicle-row__body">
                <strong class="be-vehicle-row__title">{{ $typeLabel }}</strong>
                <span class="be-vehicle-row__hint">{{ $hint }}</span>
            </span>
            <span class="be-vehicle-row__price" data-price-slot>…</span>
        </button>
        @empty
        <div class="booking-empty-state booking-empty-state--card">
            <h3 class="booking-empty-state__title mb-0">Chưa có loại xe khả dụng.</h3>
        </div>
        @endforelse
    </div>
    {{-- Mở rộng / thu gọn bằng vuốt thanh kéo trên sheet (booking-vehicle-sheet-handle) --}}
    <span id="booking-vehicle-extra-count" class="visually-hidden" data-extra-count="{{ $extraCount }}" hidden></span>
</div>
