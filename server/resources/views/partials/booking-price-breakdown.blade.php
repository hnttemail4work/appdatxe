@php
    /** @var \App\Models\Booking $booking */
    $bd = is_array($booking->price_breakdown) ? $booking->price_breakdown : [];
    $subtotal = (int) ($booking->price_subtotal ?? ($bd['price_subtotal'] ?? 0));
    $holiday = (int) ($booking->surcharge_holiday ?? ($bd['surcharge_holiday'] ?? 0));
    $peak = (int) ($booking->surcharge_peak ?? ($bd['surcharge_peak'] ?? 0));
    $rain = (int) ($booking->surcharge_rain ?? ($bd['surcharge_rain'] ?? 0));
    $toll = (int) ($booking->toll_amount ?? ($bd['toll_amount'] ?? 0));
    $discount = (int) ($booking->referral_discount_amount ?? ($bd['referral_discount_amount'] ?? 0));
    $discountPct = (float) ($booking->referral_discount_percent ?? ($bd['referral_discount_percent'] ?? 0));
    $distance = (int) ($booking->distance_km ?? ($bd['distance_km'] ?? 0));
    $vehiclePrice = (int) ($bd['price_vehicle'] ?? ($booking->price_base ?? 0));
    $compact = !empty($compact);
@endphp
@if($subtotal > 0 || (float) $booking->total_price > 0)
<div class="booking-price-breakdown {{ $compact ? 'booking-price-breakdown--compact' : '' }}">
    @if($distance > 0)
        <div class="d-flex justify-content-between small {{ $compact ? '' : 'mb-1' }}">
            <span class="text-muted">Quãng đường</span>
            <span>{{ number_format($distance, 0, ',', '.') }} km</span>
        </div>
    @endif
    @if($vehiclePrice > 0)
        <div class="d-flex justify-content-between small {{ $compact ? '' : 'mb-1' }}">
            <span class="text-muted">Giá xe</span>
            <span>{{ number_format($vehiclePrice, 0, ',', '.') }} đ</span>
        </div>
    @endif
    @if($holiday > 0)
        <div class="d-flex justify-content-between small {{ $compact ? '' : 'mb-1' }}">
            <span class="text-muted">Phụ phí lễ/tết</span>
            <span>+{{ number_format($holiday, 0, ',', '.') }} đ</span>
        </div>
    @endif
    @if($peak > 0)
        <div class="d-flex justify-content-between small {{ $compact ? '' : 'mb-1' }}">
            <span class="text-muted">Phụ phí cao điểm</span>
            <span>+{{ number_format($peak, 0, ',', '.') }} đ</span>
        </div>
    @endif
    @if($rain > 0)
        <div class="d-flex justify-content-between small {{ $compact ? '' : 'mb-1' }}">
            <span class="text-muted">Phụ phí mưa</span>
            <span>+{{ number_format($rain, 0, ',', '.') }} đ</span>
        </div>
    @endif
    @if($toll > 0)
        <div class="d-flex justify-content-between small {{ $compact ? '' : 'mb-1' }}">
            <span class="text-muted">Thu phí đường</span>
            <span>+{{ number_format($toll, 0, ',', '.') }} đ</span>
        </div>
    @endif
    @if($discount > 0)
        <div class="d-flex justify-content-between small {{ $compact ? '' : 'mb-1' }}">
            <span class="text-muted">Giảm giá{{ $discountPct > 0 ? ' ('.rtrim(rtrim(number_format($discountPct, 2, '.', ''), '0'), '.').'%)' : '' }}</span>
            <span class="text-success">−{{ number_format($discount, 0, ',', '.') }} đ</span>
        </div>
    @endif
    <div class="d-flex justify-content-between fw-semibold {{ $compact ? 'small' : 'mt-1' }}">
        <span>Thành tiền</span>
        <span>{{ number_format((float) $booking->total_price, 0, ',', '.') }} đ</span>
    </div>
</div>
@endif
