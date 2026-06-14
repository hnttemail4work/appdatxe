@extends('layouts.app')

@section('content')
@php
$provinces = ['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng','Vũng Tàu','Đà Lạt','Nha Trang','Mũi Né','Huế','Quy Nhơn','Buôn Ma Thuột','Phan Thiết','Long Xuyên','Mỹ Tho','Vinh','Thanh Hóa','Hạ Long'];
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h3 class="mb-1 card-title-bar">Dashboard</h3>
                    <p class="text-muted mb-0">Quản lý xe, lịch trình và tài xế.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('operator.drivers') }}"
                       class="btn btn-outline-primary btn-sm {{ request()->routeIs('operator.drivers*') ? 'active' : '' }}">
                        Quản lý tài xế
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        {{-- Form thêm xe --}}
        <div class="card shadow-sm p-4 mb-4">
            <h4>Thêm xe mới</h4>
            <form method="POST" action="{{ route('operator.vehicles.store') }}" class="mt-3">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Biển số xe</label>
                    <input name="license_plate" class="form-control" placeholder="vd: 51A-12345" required value="{{ old('license_plate') }}">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Loại xe</label>
                        <select name="type" class="form-select" required>
                            <option value="limousine" {{ old('type') === 'limousine' ? 'selected' : '' }}>Limousine</option>
                            <option value="sedan" {{ old('type') === 'sedan' ? 'selected' : '' }}>Sedan</option>
                            <option value="suv" {{ old('type') === 'suv' ? 'selected' : '' }}>SUV</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số ghế</label>
                        <input type="number" name="capacity" min="1" max="50" class="form-control"
                            placeholder="9" value="{{ old('capacity') }}" required>
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select" required>
                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Hoạt động</option>
                        <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Đang bảo trì</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Không hoạt động</option>
                    </select>
                </div>
                <button class="btn btn-primary">Lưu xe</button>
            </form>
        </div>

        {{-- Form tạo chuyến chạy hằng ngày --}}
        <div class="card shadow-sm p-4">
            <h4>Tạo chuyến chạy hằng ngày</h4>
            <p class="text-muted small">Chọn giờ khởi hành — hệ thống tự tạo chuyến mỗi ngày, tự chuyển trạng thái theo giờ thực.</p>
            <form method="POST" action="{{ route('operator.schedules.store') }}" class="mt-3">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Tuyến đường</label>
                    @if($routes->isNotEmpty())
                        <select name="route_id" class="form-select" required>
                            <option value="">-- Chọn tuyến --</option>
                            @foreach($routes as $route)
                                <option value="{{ $route->id }}" {{ old('route_id') == $route->id ? 'selected' : '' }}>
                                    {{ $route->departure }} → {{ $route->destination }} · {{ number_format($route->base_price, 0, ',', '.') }} đ
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="alert alert-warning py-2 mb-0">Chưa có tuyến nào trong hệ thống. Liên hệ admin để thêm tuyến.</div>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label">Xe</label>
                    @if($vehicles->isNotEmpty())
                        <select name="vehicle_id" class="form-select" required>
                            <option value="">-- Chọn xe --</option>
                            @foreach($vehicles as $vehicle)
                                @if($vehicle->status === 'active')
                                    <option value="{{ $vehicle->id }}" {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->license_plate }} · {{ ucfirst($vehicle->type) }} · {{ $vehicle->capacity }} ghế
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    @else
                        <div class="alert alert-warning py-2 mb-0">Bạn chưa có xe. Thêm xe ở trên trước.</div>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label">Tài xế</label>
                    @if($drivers->isNotEmpty())
                        <select name="driver_id" class="form-select mb-2" onchange="fillDriverName(this)">
                            <option value="">-- Chọn từ danh sách --</option>
                            @foreach($drivers as $d)
                                <option value="{{ $d->user_id }}" data-name="{{ $d->user->name }}"
                                    {{ old('driver_id') == $d->user_id ? 'selected' : '' }}>
                                    {{ $d->user->name }} · Hạng {{ $d->license_class }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <p class="small text-muted mb-1">Chưa có tài xế. <a href="{{ route('operator.drivers.create') }}">Thêm tài xế →</a></p>
                    @endif
                    <input name="driver_name" id="driver_name_input" class="form-control" placeholder="Để trống nếu chưa có tài xế"
                        value="{{ old('driver_name') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Giờ khởi hành hằng ngày <span class="text-danger">*</span></label>
                    <input type="time" name="departure_time" class="form-control"
                        value="{{ old('departure_time', '07:00') }}" required>
                    <div class="form-text">Ví dụ 07:00 — áp dụng mỗi ngày theo giờ Việt Nam.</div>
                </div>
                <button class="btn btn-primary">Tạo chuyến hằng ngày</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        {{-- Chuyến hôm nay --}}
        <div class="card shadow-sm p-4 mb-4">
            <h4>Chuyến hôm nay ({{ now()->format('d/m/Y') }})</h4>
            @if($todayTrips->isEmpty())
                <p class="text-muted mt-2 mb-0">Chưa có chuyến nào hôm nay. Tạo chuyến hằng ngày bên trái.</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Giờ</th>
                                <th>Tuyến</th>
                                <th>Tài xế</th>
                                <th>Ghế</th>
                                <th>TT</th>
                            </tr>
                        </thead>
                        <tbody id="operator-today-trips">
                            @foreach($todayTrips as $trip)
                            <tr data-trip-id="{{ $trip->id }}">
                                <td><strong>{{ $trip->departure_time->format('H:i') }}</strong></td>
                                <td class="small">{{ $trip->route->departure }} → {{ $trip->route->destination }}</td>
                                <td class="small trip-driver-cell">
                                    @if($trip->driver_id)
                                        {{ $trip->driver?->name ?? $trip->driver_name }}
                                    @else
                                        <span class="text-muted">Chờ phân bổ</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $trip->bookedSeatsCount() >= $trip->capacity() ? 'danger' : 'info' }} trip-seats-cell">{{ $trip->seatsLabel() }}</span></td>
                                <td>@include('partials.schedule-status', ['schedule' => $trip])</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Danh sách xe --}}
        <div class="card shadow-sm p-4 mb-4">
            <h4>Đội xe của tôi</h4>
            @if($vehicles->isEmpty())
                <p class="text-muted mt-2">Chưa có xe nào. Thêm xe ở bên trái.</p>
            @else
                <ul class="list-group list-group-flush mt-3">
                    @foreach($vehicles as $vehicle)
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $vehicle->license_plate }}</strong>
                                    <span class="text-muted ms-2">{{ ucfirst($vehicle->type) }} · {{ $vehicle->capacity }} ghế</span>
                                </div>
                                <span class="badge bg-{{ $vehicle->status === 'active' ? 'success' : ($vehicle->status === 'maintenance' ? 'warning' : 'secondary') }}">
                                    {{ match($vehicle->status) { 'active' => 'Hoạt động', 'maintenance' => 'Bảo trì', default => 'Không HĐ' } }}
                                </span>
                            </div>
                            @if($vehicle->schedules->isNotEmpty())
                                <small class="text-muted">{{ $vehicle->schedules->count() }} lịch trình</small>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Danh sách hành khách / booking --}}
        <div class="card shadow-sm p-4">
            <h4>Hành khách & Booking</h4>
            <p class="text-muted small mb-0">
                Luồng: Khách báo CK → <strong>QL/Admin xác nhận TT</strong> → <strong>QL/Admin duyệt chuyến</strong> → Tài xế thấy hành khách → Tài xế báo hoàn → <strong>Khách xác nhận hoàn chuyến</strong>.
            </p>
            @if($passengers->isEmpty())
                <p class="text-muted mt-2">Chưa có hành khách nào.</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Khách</th>
                                <th>Chuyến</th>
                                <th>Ghế</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($passengers as $booking)
                                <tr class="border-bottom">
                                    <td>
                                        <strong>{{ $booking->customer->name }}</strong><br>
                                        <small class="text-muted">{{ $booking->customer->email }}</small>
                                    </td>
                                    <td>
                                        {{ $booking->schedule->route->departure }} → {{ $booking->schedule->route->destination }}<br>
                                        <small class="text-muted">{{ $booking->schedule->departure_time->format('H:i d/m') }}</small>
                                    </td>
                                    <td>{{ implode(', ', (array)$booking->seat_numbers) }}</td>
                                    <td>
                                        @include('partials.booking-status', ['booking' => $booking])
                                    </td>
                                    <td>
                                        @include('partials.booking-actions', ['booking' => $booking, 'routePrefix' => 'operator'])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fillDriverName(select) {
    const opt = select.options[select.selectedIndex];
    const name = opt.getAttribute('data-name');
    if (name) document.getElementById('driver_name_input').value = name;
}

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
                    if (driver) driver.textContent = trip.driver;
                    if (seats) seats.textContent = trip.seats_label;
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
