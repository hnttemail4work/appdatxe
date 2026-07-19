<article class="customer-trip-card">
    <div class="customer-trip-card__head">
        <div>
            <div class="customer-trip-card__route">{{ $trip['route_label'] ?? (($trip['pickup_address'] ?? '—') . ' → ' . ($trip['dropoff_address'] ?? '—')) }}</div>
            <div class="customer-trip-card__meta">{{ $trip['service_date_label'] ?? $trip['created_at_label'] ?? '' }}</div>
        </div>
        <span class="customer-trip-card__status">{{ $trip['guest_status_label'] ?? '' }}</span>
    </div>
    <div class="customer-trip-card__foot">
        <span class="customer-trip-card__price">{{ $trip['total_price_label'] ?? '' }}</span>
        <div class="customer-trip-card__actions">
            @if(! empty($trip['review']))
                <span class="customer-trip-card__review">{{ $trip['review']['icon'] ?? '' }} {{ $trip['review']['label'] ?? '' }}</span>
            @elseif(! empty($trip['can_review']))
                <span class="text-warning small">Chờ đánh giá</span>
            @endif
            <a href="{{ $trip['trips_url'] ?? route('booking.trips') }}" class="btn btn-sm btn-outline-primary">Chi tiết</a>
        </div>
    </div>
</article>
