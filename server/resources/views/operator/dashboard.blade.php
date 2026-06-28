@extends('layouts.console')

@section('console')
@php
$pendingBookings = $passengers->filter(fn ($b) => ! $b->isExpired() && $b->booking_status === 'pending' && ($b->needsOperatorConfirmation() || ! $b->hasDriverAccepted()))->count();
$pendingSettleCount = $pendingSettleCount ?? 0;
$operatorDefaultTab = request('tab');
if (! in_array($operatorDefaultTab, ['today', 'bookings', 'referrals'], true)) {
    $operatorDefaultTab = 'bookings';
}
@endphp

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.operator-nav-tabs', [
            'active' => $operatorDefaultTab,
            'todayCount' => $todayTrips->count(),
            'pendingBookings' => $pendingBookings,
            'referralCount' => $referralBookings->count(),
        ])

        <div class="tab-content screen-tab-panels pt-3">
        @include('partials.screen-tab-pane', ['prefix' => 'operator-main', 'key' => 'today', 'active' => $operatorDefaultTab === 'today'])
        @if($todayTrips->isEmpty())
            <div class="console-empty">
                <div class="console-empty-icon">📅</div>
                <p>Chưa có khách đặt chuyến hôm nay.</p>
            </div>
        @else
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Khởi hành → Đến</th>
                            <th>Tuyến</th>
                            <th>Tài xế</th>
                            <th>Ghép/ghế</th>
                            <th>Cả xe</th>
                            <th>Ghế</th>
                            <th>TT</th>
                        </tr>
                    </thead>
                    <tbody id="operator-today-trips">
                        @foreach($todayTrips as $trip)
                        <tr data-trip-id="{{ $trip->id }}">
                            <td class="cell-primary small">{{ $trip->tripTimeLabel() }}</td>
                            <td>{{ $trip->route->departure }} → {{ $trip->route->destination }}</td>
                            <td class="trip-driver-cell cell-muted">
                                @if($trip->driver_id)
                                    {{ $trip->driver?->name ?? $trip->driver_name }}
                                @else
                                    <span class="text-muted">Chờ phân bổ</span>
                                @endif
                            </td>
                            <td>{{ number_format($trip->seatPrice(), 0, ',', '.') }} đ</td>
                            <td>{{ number_format($trip->wholeCarPriceAmount(), 0, ',', '.') }} đ</td>
                            <td>
                                <span class="badge bg-{{ $trip->bookedSeatsCount() >= $trip->capacity() ? 'danger' : 'primary' }} trip-seats-cell">
                                    {{ $trip->seatsLabel() }}
                                </span>
                            </td>
                            <td class="trip-status-cell">@include('partials.schedule-status', ['schedule' => $trip])</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @include('partials.screen-tab-pane-end')

        @include('partials.screen-tab-pane', ['prefix' => 'operator-main', 'key' => 'bookings', 'active' => $operatorDefaultTab === 'bookings'])
        @if($passengers->isEmpty())
            <div class="console-empty py-3">
                <div class="console-empty-icon">🎫</div>
                <p class="mb-0">Chưa có đơn đặt xe nào.</p>
            </div>
        @else
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Hành khách</th>
                            <th>Chuyến</th>
                            <th>Loại</th>
                            <th>Ghế</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Tài xế</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($passengers as $booking)
                        <tr>
                            <td>
                                @include('partials.admin-booking-customer', ['booking' => $booking])
                            </td>
                            <td>
                                <div>{{ $booking->schedule->route->departure }} → {{ $booking->schedule->route->destination }}</div>
                                <div class="cell-muted">{{ $booking->schedule->departure_time->format('H:i · d/m/Y') }}</div>
                                @if($booking->schedule->shortTripCode())
                                    <div class="cell-muted small">Mã chuyến · {{ $booking->schedule->shortTripCode() }}</div>
                                @endif
                            </td>
                            <td class="small">
                                <span class="badge bg-{{ $booking->booking_mode === 'whole_car' ? 'primary' : 'info text-dark' }}">{{ $booking->bookingModeLabel() }}</span>
                            </td>
                            <td>{{ $booking->booking_mode === 'shared' ? $booking->seatCountLabel() : 'Cả xe' }}</td>
                            <td class="fw-semibold">{{ number_format($booking->total_price, 0, ',', '.') }} đ</td>
                            <td>@include('partials.booking-status-operator', ['booking' => $booking])</td>
                            <td class="small">
                                @include('partials.operator-booking-assign', ['booking' => $booking, 'drivers' => $drivers])
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @include('partials.screen-tab-pane-end')

        @include('partials.screen-tab-pane', ['prefix' => 'operator-main', 'key' => 'referrals', 'active' => $operatorDefaultTab === 'referrals'])
        @if($referralBookings->isEmpty())
            <div class="console-empty py-3">
                <div class="console-empty-icon">🤝</div>
                <p class="mb-0">Chưa có đơn đặt xe qua giới thiệu.</p>
            </div>
        @else
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Mã giới thiệu</th>
                            <th>Mã chuyến</th>
                            <th>Hoa hồng (8%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($referralBookings as $booking)
                        <tr>
                            <td><span class="cell-primary fw-semibold">{{ $booking->referral_code }}</span></td>
                            <td>
                                <span class="cell-primary">{{ $booking->schedule->shortTripCode() ?: '—' }}</span>
                                <div class="cell-muted small">{{ $booking->passenger_name }} · {{ $booking->schedule->route->departure }} → {{ $booking->schedule->route->destination }}</div>
                            </td>
                            <td class="fw-semibold">{{ number_format($booking->referralCommission(), 0, ',', '.') }} đồng</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @include('partials.screen-tab-pane-end')

        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}">
@endpush

@push('scripts')
<script>
(function () {
    var syncUrl = @json(route('operator.liveSync'));
    function poll() {
        fetch(syncUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.today_trips || []).forEach(function (trip) {
                    var row = document.querySelector('#operator-today-trips tr[data-trip-id="' + trip.id + '"]');
                    if (!row) return;
                    var driver = row.querySelector('.trip-driver-cell');
                    var seats = row.querySelector('.trip-seats-cell');
                    var status = row.querySelector('.trip-status-cell');
                    if (driver) driver.textContent = trip.driver;
                    if (seats) seats.textContent = trip.seats_label;
                    if (status && trip.status_label) {
                        status.innerHTML = '<span class="badge bg-' + trip.status_color + '">' + trip.status_label + '</span>';
                    }
                });
            }).catch(function () {});
    }
    if (document.getElementById('operator-today-trips')) {
        poll();
        setInterval(poll, 15000);
    }
})();
</script>
@endpush
