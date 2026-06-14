@extends('layouts.app')

@section('content')
@php
$provinces = ['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng','Vũng Tàu','Đà Lạt','Nha Trang','Mũi Né','Huế','Quy Nhơn','Buôn Ma Thuột','Phan Thiết','Long Xuyên','Mỹ Tho','Vinh','Thanh Hóa','Hạ Long'];
$departures = $routeOptions->pluck('departure')->unique()->sort()->values();
$destinations = $routeOptions->pluck('destination')->unique()->sort()->values();
@endphp
<div class="row g-4">

    <div class="col-lg-8">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title-bar mb-0">Chuyến đang mở</h4>
                <small class="text-muted" id="sync-indicator">Đang cập nhật...</small>
            </div>
            <form class="row g-3" method="GET" action="{{ route('customer.dashboard') }}" id="trip-filter-form">
                <div class="col-md-3">
                    <label class="form-label">Điểm đi</label>
                    <select name="departure" class="form-select">
                        <option value="">Tất cả</option>
                        @foreach($departures as $p)
                            <option value="{{ $p }}" {{ ($filters['departure'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Điểm đến</label>
                    <select name="destination" class="form-select">
                        <option value="">Tất cả</option>
                        @foreach($destinations as $p)
                            <option value="{{ $p }}" {{ ($filters['destination'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ngày</label>
                    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">Đang mở + đang chạy</option>
                        <option value="scheduled" {{ ($filters['status'] ?? '') === 'scheduled' ? 'selected' : '' }}>Sắp chạy</option>
                        <option value="running" {{ ($filters['status'] ?? '') === 'running' ? 'selected' : '' }}>Đang chạy</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Loại xe</label>
                    <select name="vehicle_type" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="limousine" {{ ($filters['vehicle_type'] ?? '') === 'limousine' ? 'selected' : '' }}>Limousine</option>
                        <option value="sedan" {{ ($filters['vehicle_type'] ?? '') === 'sedan' ? 'selected' : '' }}>Sedan</option>
                        <option value="suv" {{ ($filters['vehicle_type'] ?? '') === 'suv' ? 'selected' : '' }}>SUV</option>
                    </select>
                </div>
                <div class="col-md-8 d-flex align-items-end gap-2">
                    <button class="btn btn-primary">Lọc chuyến</button>
                    <a href="{{ route('customer.dashboard') }}" class="btn btn-outline-secondary">Xóa lọc</a>
                </div>
            </form>
        </div>

        <div class="card shadow-sm p-4 mt-4">
            <h4 class="card-title-bar mb-3">Danh sách chuyến <span class="badge bg-secondary" id="trip-count">{{ $schedules->count() }}</span></h4>

            <div id="trips-list">
            @forelse($schedules as $s)
            @php
                $occupiedMap = $s->occupied_seat_map ?? [];
                $capacity = $s->capacity();
                $booked = $s->bookedSeatsCount();
                $driverRequest = $pendingDriverRequests->get($s->id);
            @endphp
            <div class="border rounded-3 p-3 mb-3 trip-card" data-schedule-id="{{ $s->id }}">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <strong class="d-block trip-route">{{ $s->route->departure }} → {{ $s->route->destination }}</strong>
                        <span class="text-muted small trip-time">{{ $s->departure_time->format('H:i · d/m/Y') }}</span><br>
                        @include('partials.schedule-status', ['schedule' => $s])
                    </div>
                    <div class="col-md-3">
                        <span class="small text-muted d-block">Xe</span>
                        {{ ucfirst($s->vehicle->type) }}<br>
                        <small class="text-muted">{{ $s->vehicle->license_plate }}</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <span class="badge trip-seats {{ $booked >= $capacity ? 'bg-danger' : 'bg-primary' }} mb-1">
                            {{ $booked }}/{{ $capacity }} ghế
                        </span><br>
                        <strong class="text-primary">{{ number_format($s->route->base_price, 0, ',', '.') }} đ</strong>
                    </div>
                    <div class="col-md-2 text-end">
                        @if($s->isBookable())
                            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#book-{{ $s->id }}">Đặt vé</button>
                        @else
                            <span class="text-muted small">{{ $s->status === 'running' ? 'Đang chạy' : 'Không mở đặt' }}</span>
                        @endif
                    </div>
                </div>

                @if($s->isBookable())
                <div class="collapse mt-3" id="book-{{ $s->id }}">
                    <hr class="my-2">
                    @if($driverRequest?->isPending())
                        <form method="POST" action="{{ route('customer.driverRequests.cancel', $driverRequest) }}" class="mb-2 text-end">
                            @csrf
                            <button class="btn btn-link btn-sm text-danger p-0">Hủy yêu cầu tài xế</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('bookings.store') }}">
                        @csrf
                        <input type="hidden" name="schedule_id" value="{{ $s->id }}">
                        <input type="hidden" name="seat_numbers" id="seat-input-{{ $s->id }}" required>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Chọn ghế</label>
                                <div class="d-flex flex-wrap gap-1 mb-2 seat-grid" data-schedule="{{ $s->id }}">
                                    @for($seat = 1; $seat <= $capacity; $seat++)
                                        @php $taken = isset($occupiedMap[(string)$seat]); @endphp
                                        <button type="button"
                                            class="btn btn-sm seat-pick {{ $taken ? 'btn-secondary disabled' : 'btn-outline-primary' }}"
                                            data-schedule="{{ $s->id }}" data-seat="{{ $seat }}"
                                            @if($taken) disabled title="Ghế đã được chọn" @endif>
                                            @if($taken)<span class="small">✓</span>@endif {{ $seat }}
                                        </button>
                                    @endfor
                                </div>
                                <div class="form-text">Đã chọn: <span id="seat-selected-{{ $s->id }}">—</span></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mã tài xế <small class="text-muted">(tùy chọn)</small></label>
                                <input type="text" name="driver_code" class="form-control"
                                    placeholder="VD: TX000001"
                                    {{ $driverRequest?->isPending() ? 'disabled' : '' }}>
                                <div class="form-text">Để trống — quản lý/admin tự phân bổ tài xế.</div>
                                @if($driverRequest?->isPending())
                                    <div class="mt-1 driver-request-status" data-schedule-id="{{ $s->id }}">
                                        <span class="badge bg-warning text-dark">⏳ Đang chờ tài xế phản hồi...</span>
                                    </div>
                                @elseif($driverRequest?->status === 'accepted')
                                    <div class="mt-1"><span class="badge bg-success">✓ Tài xế đã nhận chuyến</span></div>
                                @endif
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Điểm đón</label>
                                <select name="pickup_address" class="form-select">
                                    @foreach($provinces as $p)
                                        <option value="Bến xe {{ $p }}" {{ $p === $s->route->departure ? 'selected' : '' }}>Bến xe {{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Điểm trả</label>
                                <select name="dropoff_address" class="form-select">
                                    @foreach($provinces as $p)
                                        <option value="Bến xe {{ $p }}" {{ $p === $s->route->destination ? 'selected' : '' }}>Bến xe {{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ghi chú</label>
                                <input type="text" name="notes" class="form-control" placeholder="Yêu cầu đặc biệt...">
                            </div>
                            <div class="col-md-6 d-flex align-items-end justify-content-end">
                                <button class="btn btn-primary px-4">Xác nhận đặt vé</button>
                            </div>
                        </div>
                    </form>
                </div>
                @endif
            </div>
            @empty
            <p class="text-muted mb-0" id="no-trips-msg">Không có chuyến nào phù hợp bộ lọc.</p>
            @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm p-4" id="my-bookings-panel">
            <h4 class="card-title-bar mb-3">Vé của tôi</h4>
            @if($bookings->isEmpty())
                <p class="text-muted">Bạn chưa có vé nào.</p>
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach($bookings as $b)
                    <div class="border rounded-3 p-3">
                        <strong>{{ $b->schedule->route->departure }} → {{ $b->schedule->route->destination }}</strong><br>
                        <small class="text-muted">{{ $b->schedule->departure_time->format('H:i · d/m/Y') }}</small>
                        <div class="small mt-1">Ghế: <strong>{{ implode(', ', (array)$b->seat_numbers) }}</strong></div>
                        <div class="small">Mã vé: <code>{{ $b->ticket_code }}</code></div>
                        @include('partials.booking-status', ['booking' => $b])
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            @if($b->payment_status === 'unpaid' && ! $b->hasPendingPaymentClaim() && !in_array($b->booking_status, ['cancelled','rejected']))
                                <form method="POST" action="{{ route('bookings.claimPayment', $b) }}">@csrf
                                    <button class="btn btn-sm btn-outline-primary">Báo chuyển khoản</button>
                                </form>
                            @endif
                            @if($b->trip_status === 'awaiting_completion')
                                <form method="POST" action="{{ route('bookings.confirmComplete', $b) }}">@csrf
                                    <button class="btn btn-sm btn-success">Xác nhận hoàn chuyến</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var syncUrl = @json(route('customer.liveSync'));

    document.querySelectorAll('.seat-pick:not(.disabled)').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sid = btn.dataset.schedule, seat = btn.dataset.seat;
            var picks = window.__seatPicks = window.__seatPicks || {};
            if (!picks[sid]) picks[sid] = new Set();
            if (picks[sid].has(seat)) { picks[sid].delete(seat); btn.classList.replace('btn-primary', 'btn-outline-primary'); }
            else { picks[sid].add(seat); btn.classList.replace('btn-outline-primary', 'btn-primary'); }
            var list = Array.from(picks[sid]).sort(function (a,b){ return Number(a)-Number(b); });
            var lbl = document.getElementById('seat-selected-' + sid);
            var inp = document.getElementById('seat-input-' + sid);
            if (lbl) lbl.textContent = list.length ? list.join(', ') : '—';
            if (inp) inp.value = list.join(',');
        });
    });

    function updateTripCard(card, trip) {
        var seats = card.querySelector('.trip-seats');
        if (seats) {
            seats.textContent = trip.seats_label + ' ghế';
            seats.className = 'badge trip-seats mb-1 ' + (trip.booked >= trip.capacity ? 'bg-danger' : 'bg-primary');
        }
    }

    function poll() {
        var params = new URLSearchParams(new FormData(document.getElementById('trip-filter-form')));
        fetch(syncUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.getElementById('sync-indicator').textContent = 'Cập nhật: ' + new Date().toLocaleTimeString('vi-VN');
                document.getElementById('trip-count').textContent = data.trips.length;
                data.trips.forEach(function (trip) {
                    var card = document.querySelector('.trip-card[data-schedule-id="' + trip.id + '"]');
                    if (card) updateTripCard(card, trip);
                });
            }).catch(function () {});
    }

    poll();
    setInterval(poll, 12000);
})();
</script>
@endpush
