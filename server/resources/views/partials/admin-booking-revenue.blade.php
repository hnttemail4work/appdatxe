@php
/** @var \App\Models\Booking $booking */
$cancelled = in_array($booking->booking_status, ['cancelled', 'rejected'], true);
$hasReferrerCommission = $booking->appliedReferralCode?->type === \App\Models\ReferralCode::TYPE_REFERRER;
$appFee = $booking->projectedPlatformFeeAmount();
$refFee = $booking->projectedReferrerCommissionAmount();
@endphp
@if($cancelled)
    <div class="cell-muted small">Phí: —</div>
    <div class="cell-muted small mt-1">HH: —</div>
@else
    <div class="cell-muted small">
        Phí: {{ $appFee > 0 ? number_format($appFee, 0, ',', '.') . ' đ' : '—' }}
    </div>
    <div class="cell-muted small mt-1">
        HH: @if($hasReferrerCommission && $refFee > 0){{ number_format($refFee, 0, ',', '.') }} đ@else—@endif
    </div>
@endif
