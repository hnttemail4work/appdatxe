@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection $bookings */
/** @var \Illuminate\Support\Collection<int, \App\Models\DriverProfile> $drivers */
$bookings = $bookings ?? collect();
$showBulkDelete = (bool) ($showBulkDelete ?? false);
$bookingList = $bookingList ?? 'active';
$showAssignActions = (bool) ($showAssignActions ?? $bookingList !== 'cancelled');
$showFeedbackColumn = $bookingList === 'feedback';
@endphp

@if($showBulkDelete)
<div class="d-flex justify-content-end mb-2">
    <button type="submit"
            form="booking-bulk-delete"
            id="booking-bulk-delete-btn"
            class="btn btn-outline-danger btn-sm"
            disabled>
        Xóa đơn hủy đã chọn
    </button>
</div>
@error('booking_ids')
    <div class="alert alert-danger py-2 small">{{ $message }}</div>
@enderror
<form id="booking-bulk-delete"
      method="POST"
      action="{{ route('admin.bookings.bulkDismiss') }}"
      data-confirm="Xóa các đơn hủy đã chọn khỏi danh sách?"
      data-confirm-title="Xóa đơn hủy"
      data-confirm-variant="danger"
      data-confirm-ok="Xóa">
    @csrf
    @method('DELETE')
</form>
@endif

@if($bookings->isEmpty())
    <div class="console-empty py-3">
        <p class="mb-0 text-muted">Chưa có đơn trong mục này.</p>
    </div>
@else
    <div class="console-table-wrap">
        <table class="console-table">
            <thead>
                <tr>
                    @if($showBulkDelete)
                    <th class="col-check" scope="col">
                        <input type="checkbox"
                               class="form-check-input booking-select-all"
                               id="booking-select-all"
                               aria-label="Chọn tất cả đơn hủy trên trang này">
                    </th>
                    @endif
                    <th>Hành khách</th>
                    <th>Chuyến</th>
                    <th>Loại</th>
                    <th>Ghế</th>
                    <th>Tổng tiền</th>
                    <th>Giới thiệu</th>
                    @if($showFeedbackColumn)
                    <th>Phản hồi</th>
                    @endif
                    <th>Tài xế</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bookings as $booking)
                <tr>
                    @if($showBulkDelete)
                    <td class="col-check">
                        <input type="checkbox"
                               class="form-check-input booking-select"
                               name="booking_ids[]"
                               value="{{ $booking->id }}"
                               form="booking-bulk-delete"
                               aria-label="Chọn đơn {{ $booking->passenger_name }}">
                    </td>
                    @endif
                    <td>
                        @include('partials.admin-booking-customer', ['booking' => $booking, 'showTripReview' => false])
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $booking->schedule->routeDepartureLabel() }} → {{ $booking->schedule->routeDestinationLabel() }}</div>
                        @if($booking->schedule->shortTripCode())
                            <div class="cell-muted small">Mã chuyến: {{ $booking->schedule->shortTripCode() }}</div>
                        @endif
                        <div class="cell-muted small">
                            Ngày đi: {{ $booking->schedule->departure_time->format('d/m/Y') }}
                        </div>
                        @if($pickupLabel = $booking->pickupTimeLabel())
                            <div class="cell-muted small">Giờ đón: {{ $pickupLabel }}</div>
                        @else
                            <div class="cell-muted small">Giờ chạy: {{ $booking->schedule->departure_time->format('H:i') }}</div>
                        @endif
                        @if($bookingList === 'cancelled')
                            @include('partials.booking-cancel-detail', ['booking' => $booking])
                        @endif
                    </td>
                    <td class="small">
                        <span class="status-pill status-pill--gold">{{ $booking->bookingModeLabel() }}</span>
                    </td>
                    <td>
                        @if($booking->vehicleBookingLabel())
                            <div>{{ $booking->vehicleBookingLabel() }}</div>
                        @endif
                    </td>
                    <td class="fw-semibold">{{ number_format($booking->chargedTotal(), 0, ',', '.') }} đ</td>
                    <td class="small cell-muted">
                        @if($booking->appliedReferralCode)
                            <span class="driver-meta-code">{{ $booking->appliedReferralCode->code }}</span>
                            @if($booking->trip_status === 'completed')
                                <div>{{ number_format($booking->referralCommissionAmount(), 0, ',', '.') }} đ ({{ number_format($booking->appliedReferralCode->commissionPercent(), 1) }}%)</div>
                            @else
                                <div class="text-muted">Chờ hoàn tất</div>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    @if($showFeedbackColumn)
                    <td class="small">
                        @if($review = $booking->tripReview)
                            <span class="fw-semibold {{ $review->sentiment === \App\Models\TripReview::SENTIMENT_LIKE ? 'text-success' : 'text-danger' }}">
                                {{ $review->sentimentIcon() }} {{ $review->driverPreferenceLabel() }}
                            </span>
                            @if($review->comment)
                                <div class="cell-muted mt-1">“{{ \Illuminate\Support\Str::limit($review->comment, 120) }}”</div>
                            @endif
                            <div class="cell-muted small mt-1">{{ $review->created_at?->format('d/m/Y H:i') }}</div>
                        @endif
                    </td>
                    @endif
                    <td class="small">
                        @if($showAssignActions)
                            @include('partials.admin-booking-assign', [
                                'booking' => $booking,
                                'drivers' => $drivers,
                                'bookingList' => $bookingList,
                            ])
                        @else
                            @php
                                $profile = $booking->schedule->assignedDriverProfile
                                    ?? $booking->schedule->designatedDriverProfile();
                            @endphp
                            @if($profile)
                                @include('partials.booking-driver-brief', [
                                    'profile' => $profile,
                                    'compact' => true,
                                ])
                            @else
                                —
                            @endif
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if(method_exists($bookings, 'links'))
        @include('partials.pagination', ['paginator' => $bookings])
    @endif
@endif
