@php
    $booking = $review->booking;
    $booking->loadMissing('schedule.route');
@endphp
<article class="customer-review-card">
    <div class="customer-review-card__head">
        <span class="customer-review-card__sentiment">{{ $review->sentimentIcon() }} {{ $review->driverPreferenceLabel() }}</span>
        <time class="customer-review-card__time">{{ $review->created_at?->format('d/m/Y H:i') }}</time>
    </div>
    <div class="customer-review-card__route">
        {{ $booking?->routeDetailLabel() ?? '—' }}
    </div>
    @if($review->comment)
        <p class="customer-review-card__comment mb-0">{{ $review->comment }}</p>
    @endif
</article>
