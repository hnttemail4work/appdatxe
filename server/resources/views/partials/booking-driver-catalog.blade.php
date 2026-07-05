@php
/** @var \Illuminate\Support\Collection<int, array<string, mixed>> $driverOffers */
$driverOffers = $driverOffers ?? collect();
$filterCapacities = $driverOffers->pluck('capacity')->filter(fn ($c) => (int) $c > 0)->unique()->sort()->values();
$filterTypes = $driverOffers->pluck('vehicle_type')->filter()->unique()->sort()->values();
$driverCount = $driverOffers->count();
@endphp

<div id="booking-results-main" class="booking-results-main">
    <div class="booking-toolbar">
        <div class="booking-toolbar__head">
            <div>
                <h2 class="booking-list-title">Chọn xe</h2>
                <p class="booking-toolbar__sub">
                    @if($driverCount > 0)
                        {{ $driverCount }} tài xế sẵn sàng · Ghim điểm đón trả để xem giá
                    @else
                        Đang cập nhật danh sách xe
                    @endif
                </p>
            </div>
        </div>

        @if($driverOffers->isNotEmpty())
        <div class="booking-toolbar__filters" role="group" aria-label="Lọc xe">
            <div class="booking-filter-field">
                <label class="booking-filter-label" for="booking-filter-capacity">Số chỗ</label>
                <div class="booking-filter-select-wrap">
                    <select id="booking-filter-capacity" class="booking-filter-select">
                        <option value="">Tất cả chỗ</option>
                        @foreach($filterCapacities as $capacity)
                            <option value="{{ $capacity }}">{{ \App\Support\VehicleCapacityOptions::label((int) $capacity) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="booking-filter-field">
                <label class="booking-filter-label" for="booking-filter-type">Loại xe</label>
                <div class="booking-filter-select-wrap">
                    <select id="booking-filter-type" class="booking-filter-select">
                        <option value="">Tất cả loại</option>
                        @foreach($filterTypes as $type)
                            <option value="{{ $type }}">{{ \App\Support\VehicleDisplay::typeLabel($type) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div id="booking-filter-empty" class="booking-empty-state booking-empty-state--card d-none">
        <div class="booking-empty-state__icon" aria-hidden="true">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
            </svg>
        </div>
        <h3 class="booking-empty-state__title">Không có xe phù hợp</h3>
        <p class="booking-empty-state__text">Thử đổi bộ lọc số chỗ hoặc loại xe.</p>
    </div>

    <div id="trips-list" class="vehicle-offer-grid">
        @forelse($driverOffers as $offer)
        @php
            $vehiclePhotoUrl = $offer['vehicle_photo'] ?? null;
            $capacityLabel = $offer['capacity_label'] ?? '—';
            $driverName = $offer['driver_name'] ?? '—';
            $typeLabel = $offer['type_label'] ?? '—';
            $licensePlate = $offer['license_plate'] ?? '—';
            $availabilityLabel = $offer['driver_availability_label'] ?? '—';
            $availabilityTone = $offer['driver_availability_tone'] ?? 'neutral';
            $bookingActionLabel = $offer['booking_action_label'] ?? 'Đặt sau';
            $bookingActionTone = $offer['booking_action_tone'] ?? 'later';
            $offerLabel = $offer['offer_label'] ?? collect([$driverName, $licensePlate, $typeLabel, $capacityLabel])->filter(fn ($p) => filled($p) && $p !== '—')->implode(' - ');
            $vehicleType = $offer['vehicle_type'] ?? '';
            $capacity = (int) ($offer['capacity'] ?? 0);
        @endphp
        <article class="vehicle-offer-card trip-card-pro"
                 data-driver-profile-id="{{ $offer['driver_profile_id'] }}"
                 data-capacity="{{ $capacity }}"
                 data-vehicle-type="{{ $vehicleType }}">
            <div class="vehicle-offer-card__media">
                @if($vehiclePhotoUrl)
                    <img src="{{ $vehiclePhotoUrl }}" alt="" class="vehicle-offer-card__photo" loading="lazy" decoding="async">
                @else
                    <div class="vehicle-offer-card__photo vehicle-offer-card__photo--empty">{{ strtoupper(substr($vehicleType ?: 'X', 0, 1)) }}</div>
                @endif
                <span class="vehicle-offer-card__status status-pill status-pill--{{ $availabilityTone }}">{{ $availabilityLabel }}</span>
            </div>

            <div class="vehicle-offer-card__body">
                <div class="vehicle-offer-card__headline">
                    <div class="vehicle-offer-card__plate">{{ $licensePlate }}</div>
                    <div class="vehicle-offer-card__driver-line">
                        <span class="vehicle-offer-card__driver-label">Tài xế</span>
                        <span class="vehicle-offer-card__driver-name">{{ $driverName }}</span>
                        @if(! empty($offer['driver_code']))
                            <span class="vehicle-offer-card__driver-code">{{ $offer['driver_code'] }}</span>
                        @endif
                    </div>
                </div>

                <div class="vehicle-offer-card__chips">
                    <span class="vehicle-offer-chip">{{ $typeLabel }}</span>
                    <span class="vehicle-offer-chip">{{ $capacityLabel }}</span>
                </div>
            </div>

            <button type="button" class="vehicle-offer-card__cta btn-book vehicle-offer-card__cta--{{ $bookingActionTone }}"
                data-open-booking
                data-driver-profile-id="{{ $offer['driver_profile_id'] }}"
                data-vehicle-id="{{ $offer['vehicle_id'] ?? '' }}"
                data-template-id="{{ $offer['template_id'] ?? '' }}"
                data-license-plate="{{ $offer['license_plate'] }}"
                data-capacity-label="{{ $capacityLabel }}"
                data-type-label="{{ $typeLabel }}"
                data-vehicle-photo="{{ $vehiclePhotoUrl ?? '' }}"
                data-driver-name="{{ $driverName }}"
                data-offer-label="{{ $offerLabel }}">
                <span>{{ $bookingActionLabel }}</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </article>
        @empty
        <div class="booking-empty-state booking-empty-state--card booking-empty-state--full">
            <div class="booking-empty-state__icon" aria-hidden="true">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M5 17h14v-4H5v4zM6 13l2-7h8l2 7M7 17v2M17 17v2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="booking-empty-state__title">Chưa có tài xế</h3>
            <p class="booking-empty-state__text">Chưa có tài xế đã duyệt với đủ thông tin xe. Liên hệ tổng đài <strong>{{ config('app.contact_phone') }}</strong>.</p>
        </div>
        @endforelse
    </div>
</div>
