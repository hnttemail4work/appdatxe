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

        {{-- Form tạo lịch trình --}}
        <div class="card shadow-sm p-4">
            <h4>Tạo lịch trình</h4>
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
                        <p class="small text-muted mb-1">Chưa có tài xế. <a href="{{ route('operator.drivers') }}">Thêm tài xế →</a></p>
                    @endif
                    <input name="driver_name" id="driver_name_input" class="form-control" placeholder="Hoặc nhập tên tài xế"
                        value="{{ old('driver_name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Giờ khởi hành</label>
                    <input type="datetime-local" name="departure_time" class="form-control"
                        value="{{ old('departure_time') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Trạng thái lịch</label>
                    <select name="status" class="form-select" required>
                        <option value="scheduled" {{ old('status') === 'scheduled' ? 'selected' : '' }}>Đã lên lịch</option>
                        <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Nháp</option>
                        <option value="running" {{ old('status') === 'running' ? 'selected' : '' }}>Đang chạy</option>
                        <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Hoàn thành</option>
                        <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>Hủy</option>
                    </select>
                </div>
                <button class="btn btn-primary">Tạo lịch trình</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
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
                                        <span class="badge bg-{{ match($booking->booking_status) {
                                            'confirmed' => 'primary',
                                            'cancelled','rejected' => 'danger',
                                            default => 'warning text-dark'
                                        } }}">{{ ucfirst($booking->booking_status) }}</span>
                                        <br>
                                        <small class="badge bg-{{ $booking->payment_status === 'paid' ? 'primary' : 'secondary' }} mt-1">
                                            {{ $booking->payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}
                                        </small>
                                    </td>
                                    <td>
                                        @if($booking->booking_status === 'pending')
                                            <div class="d-flex flex-column gap-1">
                                                <form method="POST" action="{{ route('operator.bookings.accept', $booking) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-primary w-100"
                                                        {{ $booking->payment_status !== 'paid' ? 'disabled' : '' }}
                                                        title="{{ $booking->payment_status !== 'paid' ? 'Khách chưa thanh toán' : 'Duyệt booking' }}">
                                                        Duyệt
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('operator.bookings.reject', $booking) }}"
                                                    onsubmit="return confirm('Từ chối booking này?')">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-danger w-100">Từ chối</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
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
</script>
@endpush
