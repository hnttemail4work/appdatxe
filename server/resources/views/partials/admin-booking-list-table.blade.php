@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection $bookings */
/** @var \Illuminate\Support\Collection<int, \App\Models\DriverProfile> $drivers */
$bookings = $bookings ?? collect();
$showBulkDelete = (bool) ($showBulkDelete ?? false);
$bookingList = $bookingList ?? 'active';
$showAssignActions = (bool) ($showAssignActions ?? false);
$showWaitingColumn = (bool) ($showWaitingColumn ?? $bookingList === 'active');
$showFeedbackColumn = $bookingList === 'feedback';
$showPickupAlertColumn = $bookingList === 'active';
$showStatusColumn = $bookingList !== 'cancelled';
$showDriverColumn = $bookingList !== 'active';
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
                    @if($showStatusColumn)
                    <th>Trạng thái</th>
                    @endif
                    @if($showPickupAlertColumn)
                    <th>Cảnh báo</th>
                    @endif
                    @if($showWaitingColumn)
                    <th>Thời gian chờ</th>
                    @endif
                    <th>Tổng tiền</th>
                    <th>Phí &amp; hoa hồng</th>
                    @if($showFeedbackColumn)
                    <th>Phản hồi</th>
                    @endif
                    @if($showDriverColumn)
                    <th>Tài xế</th>
                    @endif
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
                        @if($catalogDriver = $booking->catalogChosenDriverProfile())
                            <div class="cell-muted small">
                                TX khách chọn:
                                <strong>{{ $catalogDriver->user->name ?? '—' }}</strong>
                                @if($catalogDriver->driver_code)
                                    <span class="driver-meta-code">{{ $catalogDriver->driver_code }}</span>
                                @endif
                            </div>
                        @endif
                        @if($activeDriver = $booking->adminTripDriverProfile())
                            @php
                                $catalogId = (int) ($booking->catalogChosenDriverProfile()?->user_id ?? 0);
                                $activeId = (int) $activeDriver->user_id;
                            @endphp
                            @if($catalogId <= 0 || $catalogId !== $activeId)
                                <div class="cell-muted small">
                                    {{ ($bookingList ?? '') === 'cancelled' ? 'TX đã nhận:' : 'TX đang nhận:' }}
                                    <strong>{{ $activeDriver->user->name ?? '—' }}</strong>
                                    @if($activeDriver->driver_code)
                                        <span class="driver-meta-code">{{ $activeDriver->driver_code }}</span>
                                    @endif
                                </div>
                            @endif
                        @endif
                        @if($bookingList === 'cancelled')
                            @include('partials.booking-cancel-detail', ['booking' => $booking])
                        @endif
                    </td>
                    @if($showStatusColumn)
                    <td class="small admin-booking-status-cell">
                        @include('partials.admin-booking-status', [
                            'booking' => $booking,
                            'bookingList' => $bookingList,
                        ])
                    </td>
                    @endif
                    @if($showPickupAlertColumn)
                    <td class="small">
                        @include('partials.admin-booking-pickup-alert', ['booking' => $booking])
                    </td>
                    @endif
                    @if($showWaitingColumn)
                    <td class="small admin-booking-waiting-cell">
                        @if($booking->needsAdminWaitingAttention())
                            @include('partials.admin-booking-actions', [
                                'booking' => $booking,
                                'drivers' => $drivers,
                            ])
                        @else
                            —
                        @endif
                    </td>
                    @endif
                    <td class="fw-semibold">{{ number_format($booking->tripRevenueAmount(), 0, ',', '.') }} đ</td>
                    <td class="small">
                        @include('partials.admin-booking-revenue', ['booking' => $booking])
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
                    @if($showDriverColumn)
                    <td class="small">
                        @if($showAssignActions)
                            @include('partials.admin-booking-assign', [
                                'booking' => $booking,
                                'drivers' => $drivers,
                                'bookingList' => $bookingList,
                                'displayOnly' => false,
                            ])
                        @else
                            @php
                                $profile = $booking->schedule->assignedDriverProfile
                                    ?? $booking->schedule->designatedDriverProfile();
                            @endphp
                            @if($profile)
                                @include('partials.admin-booking-driver-code', ['profile' => $profile])
                            @else
                                —
                            @endif
                        @endif
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if(method_exists($bookings, 'links'))
        @include('partials.pagination', ['paginator' => $bookings])
    @endif
@endif
