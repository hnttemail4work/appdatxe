<article class="customer-trip-card">
    <div class="customer-trip-card__head">
        <div>
            <div class="customer-trip-card__route">{{ $trip['route_label'] ?? (($trip['pickup_address'] ?? '—') . ' → ' . ($trip['dropoff_address'] ?? '—')) }}</div>
            <div class="customer-trip-card__meta">{{ $trip['completed_at_label'] ?? $trip['service_date_label'] ?? $trip['created_at_label'] ?? '' }}</div>
        </div>
        <span class="customer-trip-card__status">{{ $trip['guest_status_label'] ?? '' }}</span>
    </div>
    <div class="customer-trip-card__foot">
        <div class="customer-trip-card__left">
            @if(! empty($trip['driver_code']))
                <span class="customer-trip-card__driver-code">Mã TX {{ $trip['driver_code'] }}</span>
            @endif
            @if(! empty($trip['review']))
                <span class="customer-trip-card__review">{{ $trip['review']['icon'] ?? '' }} {{ $trip['review']['label'] ?? '' }}</span>
            @elseif(! empty($trip['can_review']))
                <a href="{{ route('booking.trips', ['review' => $trip['booking_reference'] ?? '']) }}"
                   class="btn btn-sm btn-outline-warning">
                    Đánh giá chuyến
                </a>
            @endif
        </div>
        <span class="customer-trip-card__price">{{ $trip['total_price_label'] ?? '' }}</span>
    </div>
</article>
