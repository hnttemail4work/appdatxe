@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $completedTrips */
$completedTrips = $completedTrips ?? collect();
@endphp

@if($completedTrips->isEmpty())
    <p class="text-muted mb-0">Chưa có chuyến hoàn tất.</p>
@else
    <div class="console-table-wrap">
        <table class="console-table admin-revenue-trip-table">
            <thead>
                <tr>
                    <th>Hoàn tất</th>
                    <th>Hành khách</th>
                    <th>Chuyến</th>
                    <th>Mã TX</th>
                    <th>Doanh thu</th>
                    <th>Trước giảm</th>
                    <th>Giảm giá</th>
                    <th>Phụ phí</th>
                    <th>Phí app</th>
                    <th>Mã GT</th>
                    <th>Hoa hồng GT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($completedTrips as $booking)
                @php
                    $referral = $booking->appliedReferralCode;
                    $hasReferrer = $referral?->type === \App\Models\ReferralCode::TYPE_REFERRER;
                    $driverProfile = $booking->assignedDriver?->driverProfile
                        ?? $booking->schedule?->assignedDriverProfile
                        ?? $booking->activeDriverProfile();
                @endphp
                <tr>
                    <td class="cell-muted small">
                        {{ $booking->completed_at?->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td>
                        <div class="cell-primary">{{ $booking->passenger_name ?: 'Khách' }}</div>
                        <div class="cell-muted small">{{ $booking->contact_phone }}</div>
                    </td>
                    <td class="small">
                        <div class="fw-semibold">{{ $booking->schedule->routeDepartureLabel() }} → {{ $booking->schedule->routeDestinationLabel() }}</div>
                        @if($booking->schedule->shortTripCode())
                            <div class="cell-muted">{{ $booking->schedule->shortTripCode() }}</div>
                        @endif
                    </td>
                    <td class="small">
                        @include('partials.admin-booking-driver-code', ['profile' => $driverProfile])
                        @if($driverName = $booking->assignedDriver?->name ?? $driverProfile?->user?->name)
                            <div class="cell-muted">{{ $driverName }}</div>
                        @endif
                    </td>
                    <td class="fw-semibold">{{ number_format($booking->tripRevenueAmount(), 0, ',', '.') }} đ</td>
                    <td class="small cell-muted">
                        @if($booking->price_subtotal)
                            {{ number_format((int) $booking->price_subtotal, 0, ',', '.') }} đ
                        @else
                            —
                        @endif
                    </td>
                    <td class="small cell-muted">
                        @if((int) ($booking->referral_discount_amount ?? 0) > 0)
                            −{{ number_format((int) $booking->referral_discount_amount, 0, ',', '.') }} đ
                        @else
                            —
                        @endif
                    </td>
                    <td class="small cell-muted">
                        @php
                            $surchargeTotal = (int) ($booking->surcharge_holiday ?? 0)
                                + (int) ($booking->surcharge_peak ?? 0)
                                + (int) ($booking->surcharge_rain ?? 0)
                                + (int) ($booking->toll_amount ?? 0);
                        @endphp
                        {{ $surchargeTotal > 0 ? number_format($surchargeTotal, 0, ',', '.').' đ' : '—' }}
                    </td>
                    <td class="small">
                        {{ number_format($booking->platformFeeAmount(), 0, ',', '.') }} đ
                    </td>
                    <td class="small">
                        @if($hasReferrer)
                            <span class="driver-meta-code">{{ $referral->code }}</span>
                            <div class="cell-muted">{{ $referral->name }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="fw-semibold {{ $hasReferrer ? 'text-success' : 'text-muted' }}">
                        @if($hasReferrer && $booking->referrerCommissionAmount() > 0)
                            {{ number_format($booking->referrerCommissionAmount(), 0, ',', '.') }} đ
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $completedTrips])
@endif
