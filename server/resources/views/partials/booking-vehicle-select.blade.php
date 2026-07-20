@php
/** @var \Illuminate\Support\Collection<int, array<string, mixed>> $driverOffers */
$driverOffers = $driverOffers ?? collect();
@endphp
<div class="be-vehicle-panel">
    <div class="be-vehicle-list" id="trips-list" role="listbox" aria-label="Chọn loại xe">
        @forelse($driverOffers as $offer)
        @php
            $capacityLabel = $offer['capacity_label'] ?? '—';
            $typeLabel = $offer['type_label'] ?? '—';
            $vehicleType = $offer['vehicle_type'] ?? '';
            $capacity = (int) ($offer['capacity'] ?? 0);
            $offerLabel = $offer['offer_label']
                ?? collect([$typeLabel, $capacityLabel])->filter(fn ($p) => filled($p) && $p !== '—')->implode(' · ');
            $iconKey = $offer['icon_key'] ?? 'other';
        @endphp
        <button type="button"
                class="be-vehicle-row"
                role="option"
                aria-selected="false"
                data-capacity="{{ $capacity }}"
                data-vehicle-type="{{ $vehicleType }}"
                data-capacity-label="{{ $capacityLabel }}"
                data-type-label="{{ $typeLabel }}"
                data-offer-label="{{ $offerLabel }}"
                data-select-vehicle>
            <span class="be-vehicle-row__media be-vehicle-row__media--icon be-vehicle-icon be-vehicle-icon--{{ $iconKey }}" aria-hidden="true">
                @include('partials.booking-vehicle-icon', ['iconKey' => $iconKey])
            </span>
            <span class="be-vehicle-row__body">
                <strong class="be-vehicle-row__title">{{ $typeLabel }}</strong>
            </span>
            <span class="be-vehicle-row__price" data-price-slot>…</span>
        </button>
        @empty
        <div class="booking-empty-state booking-empty-state--card">
            <h3 class="booking-empty-state__title mb-0">Chưa có loại xe khả dụng.</h3>
        </div>
        @endforelse
    </div>
    <span id="booking-vehicle-extra-count" class="visually-hidden" data-extra-count="0" hidden></span>
</div>
