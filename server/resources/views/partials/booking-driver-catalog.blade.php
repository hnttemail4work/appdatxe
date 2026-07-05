@php
/** @var \Illuminate\Support\Collection<int, array<string, mixed>> $driverOffers */
$driverOffers = $driverOffers ?? collect();
@endphp

<div id="booking-results-main" class="booking-results-main">
    <h2 class="booking-list-title h5 mb-3">Xe của chúng tôi</h2>

    <div id="trips-list">
        @forelse($driverOffers as $offer)
        @php
            $vehiclePhotoUrl = $offer['vehicle_photo'] ?? null;
            $capacityLabel = $offer['capacity_label'] ?? '—';
            $driverName = $offer['driver_name'] ?? '—';
            $typeLabel = $offer['type_label'] ?? '—';
            $licensePlate = $offer['license_plate'] ?? '—';
            $availabilityLabel = $offer['driver_availability_label'] ?? '—';
            $availabilityTone = $offer['driver_availability_tone'] ?? 'neutral';
            $offerLabel = $offer['offer_label'] ?? collect([$driverName, $licensePlate, $typeLabel, $capacityLabel])->filter(fn ($p) => filled($p) && $p !== '—')->implode(' - ');
        @endphp
        <article class="trip-card-pro" data-driver-profile-id="{{ $offer['driver_profile_id'] }}">
            <div class="trip-card-layout trip-card-layout--catalog">
                <div class="trip-vehicle-thumb" aria-hidden="true">
                    @if($vehiclePhotoUrl)
                        <img src="{{ $vehiclePhotoUrl }}" alt="" class="trip-vehicle-photo" loading="lazy" decoding="async">
                    @else
                        <div class="trip-vehicle-photo trip-vehicle-photo--empty">{{ strtoupper(substr($offer['vehicle_type'] ?? 'X', 0, 1)) }}</div>
                    @endif
                </div>
                <div class="trip-card-body">
                    <div class="trip-card-head">
                        <div class="trip-card-head-main">
                            <dl class="trip-card-specs">
                                <div class="trip-card-spec trip-card-spec--plate">
                                    <dt>Bs:</dt>
                                    <dd class="trip-vehicle-plate">{{ $licensePlate }}</dd>
                                </div>
                                <div class="trip-card-spec">
                                    <dt>Tx:</dt>
                                    <dd>{{ $driverName }}</dd>
                                </div>
                                <div class="trip-card-spec">
                                    <dt>Lx:</dt>
                                    <dd>{{ $typeLabel }}</dd>
                                </div>
                                <div class="trip-card-spec">
                                    <dt>Sc:</dt>
                                    <dd>{{ $capacityLabel }}</dd>
                                </div>
                            </dl>
                            <div class="trip-card-availability">
                                <span class="status-pill status-pill--{{ $availabilityTone }}">{{ $availabilityLabel }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="trip-card-side">
                    <button type="button" class="btn btn-outline-primary btn-book fw-semibold"
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
                        Đặt xe
                    </button>
                </div>
            </div>
        </article>
        @empty
        <div class="booking-empty-state">
            <h3 class="h6 fw-bold">Chưa có tài xế</h3>
            <p class="text-muted small mb-0">Chưa có tài xế đã duyệt với đủ thông tin xe. Liên hệ tổng đài {{ config('app.contact_phone') }}.</p>
        </div>
        @endforelse
    </div>
</div>
