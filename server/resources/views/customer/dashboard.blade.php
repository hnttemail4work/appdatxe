@extends('layouts.app')

@section('content')
@php
$provinces = ['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng','Vũng Tàu','Đà Lạt','Nha Trang','Mũi Né','Huế','Quy Nhơn','Buôn Ma Thuột','Phan Thiết','Long Xuyên','Mỹ Tho','Vinh','Thanh Hóa','Hạ Long'];
@endphp
<div class="row g-4">

    {{-- Tìm chuyến --}}
    <div class="col-lg-8">
        <div class="card shadow-sm p-4">
            <h4 class="card-title-bar mb-3">Tìm chuyến xe</h4>
            <form class="row g-3" method="GET" action="{{ route('customer.dashboard') }}">
                <div class="col-md-4">
                    <label class="form-label">Điểm đi</label>
                    <select name="departure" class="form-select" required>
                        <option value="">-- Chọn điểm đi --</option>
                        @foreach($provinces as $p)
                            <option value="{{ $p }}" {{ request('departure') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Điểm đến</label>
                    <select name="destination" class="form-select" required>
                        <option value="">-- Chọn điểm đến --</option>
                        @foreach($provinces as $p)
                            <option value="{{ $p }}" {{ request('destination') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ngày đi</label>
                    <input type="date" name="date"
                        value="{{ request('date') ?? now()->addDay()->format('Y-m-d') }}"
                        class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại xe</label>
                    <select name="vehicle_type" class="form-select">
                        <option value="">Tất cả loại xe</option>
                        <option value="limousine" {{ request('vehicle_type') === 'limousine' ? 'selected' : '' }}>Limousine</option>
                        <option value="sedan"     {{ request('vehicle_type') === 'sedan'     ? 'selected' : '' }}>Sedan</option>
                        <option value="suv"       {{ request('vehicle_type') === 'suv'       ? 'selected' : '' }}>SUV</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Tìm chuyến</button>
                </div>
            </form>
        </div>

        {{-- Tài xế đề xuất --}}
        @if($availableDrivers->isNotEmpty())
        <div class="card shadow-sm p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title-bar mb-0">Tài xế sẵn sàng</h4>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnRandomDriver">
                    🎲 Ngẫu nhiên
                </button>
            </div>
            <div class="row g-3" id="driverCards">
                @foreach($availableDrivers as $d)
                <div class="col-md-6 col-lg-4 driver-card">
                    <div class="border rounded-3 p-3 h-100 d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:44px;height:44px;font-weight:700;font-size:1.1rem;">
                            {{ mb_substr($d->user->name, 0, 1) }}
                        </div>
                        <div>
                            <strong class="d-block">{{ $d->user->name }}</strong>
                            <span class="text-muted small">
                                Hạng {{ $d->license_class }} · {{ $d->experience_years }} năm KN
                            </span><br>
                            @if($d->operator)
                            <small class="text-muted">{{ $d->operator->name }}</small>
                            @endif
                            <div class="mt-1">
                                <span class="badge bg-success">🟢 Sẵn sàng</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div id="randomResult" class="mt-3" style="display:none;">
                <div class="alert alert-primary mb-0 d-flex gap-3 align-items-center">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:44px;height:44px;font-weight:700;font-size:1.1rem;" id="randomAvatar"></div>
                    <div>
                        <div class="fw-bold" id="randomName"></div>
                        <div class="small text-muted" id="randomMeta"></div>
                        <span class="badge bg-success mt-1">🎲 Được chọn ngẫu nhiên</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Kết quả tìm kiếm --}}
        <div class="card shadow-sm p-4 mt-4">
            <h4 class="card-title-bar mb-3">Kết quả</h4>            @if($searchPerformed && $schedules->isEmpty())
                <div class="alert alert-info">Không tìm thấy chuyến phù hợp. Thử thay đổi ngày hoặc tuyến đường.</div>
            @elseif(!$searchPerformed)
                <p class="text-muted">Chọn điểm đi, điểm đến và ngày để tìm chuyến.</p>
            @endif

            @foreach($schedules as $s)
            <div class="border rounded-3 p-3 mb-3">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <strong class="d-block">{{ $s->route->departure }} → {{ $s->route->destination }}</strong>
                        <span class="text-muted small">{{ $s->departure_time->format('H:i · d/m/Y') }}</span>
                    </div>
                    <div class="col-md-2">
                        <span class="small text-muted d-block">Xe</span>
                        {{ ucfirst($s->vehicle->type) }}<br>
                        <small class="text-muted">{{ $s->vehicle->license_plate }}</small>
                    </div>
                    <div class="col-md-3">
                        <span class="small text-muted d-block">Tài xế</span>
                        @if($s->driver)
                            <strong>{{ $s->driver->name }}</strong>
                            @if($s->driver->driverProfile)
                                <br><small class="text-muted">
                                    Hạng {{ $s->driver->driverProfile->license_class }} ·
                                    {{ $s->driver->driverProfile->experience_years }} năm KN
                                </small>
                            @endif
                        @elseif($s->driver_name)
                            <span>{{ $s->driver_name }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                    <div class="col-md-2 text-center">
                        <span class="badge {{ $s->available_seats > 0 ? 'bg-primary' : 'bg-danger' }} mb-1">
                            {{ $s->available_seats }} ghế trống
                        </span><br>
                        <strong class="text-primary">{{ number_format($s->route->base_price, 0, ',', '.') }} đ</strong>
                    </div>
                    <div class="col-md-2 text-end">
                        @if($s->available_seats > 0)
                            <button class="btn btn-primary btn-sm"
                                data-bs-toggle="collapse" data-bs-target="#book-{{ $s->id }}">
                                Đặt vé
                            </button>
                        @else
                            <span class="text-muted small">Hết chỗ</span>
                        @endif
                    </div>
                </div>

                @if($s->available_seats > 0)
                <div class="collapse mt-3" id="book-{{ $s->id }}">
                    <hr class="my-2">
                    <form method="POST" action="{{ route('bookings.store') }}">
                        @csrf
                        <input type="hidden" name="schedule_id" value="{{ $s->id }}">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Ghế <small class="text-muted">(vd: 1,2,3)</small></label>
                                <input type="text" name="seat_numbers" class="form-control"
                                    placeholder="1,2" required>
                                <div class="form-text">Xe {{ $s->vehicle->capacity }} ghế (1–{{ $s->vehicle->capacity }})</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Điểm đón</label>
                                <select name="pickup_address" class="form-select">
                                    @foreach($provinces as $p)
                                        <option value="Bến xe {{ $p }}"
                                            {{ $p === $s->route->departure ? 'selected' : '' }}>
                                            Bến xe {{ $p }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Điểm trả</label>
                                <select name="dropoff_address" class="form-select">
                                    @foreach($provinces as $p)
                                        <option value="Bến xe {{ $p }}"
                                            {{ $p === $s->route->destination ? 'selected' : '' }}>
                                            Bến xe {{ $p }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ghi chú</label>
                                <input type="text" name="notes" class="form-control"
                                    placeholder="Yêu cầu đặc biệt...">
                            </div>
                            <div class="col-12 d-flex justify-content-end gap-2 align-items-center">
                                <small class="text-muted">Ghế được giữ 15 phút</small>
                                <button class="btn btn-primary px-4">Xác nhận đặt vé</button>
                            </div>
                        </div>
                    </form>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Vé của tôi --}}
    <div class="col-lg-4">
        <div class="card shadow-sm p-4">
            <h4 class="card-title-bar mb-3">Vé của tôi</h4>
            @if($bookings->isEmpty())
                <p class="text-muted">Bạn chưa có vé nào.</p>
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach($bookings as $b)
                    <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <strong>{{ $b->schedule->route->departure }} → {{ $b->schedule->route->destination }}</strong><br>
                                <small class="text-muted">{{ $b->schedule->departure_time->format('H:i · d/m/Y') }}</small>
                            </div>
                            <span class="badge bg-{{ match($b->booking_status) {
                                'confirmed' => 'primary',
                                'cancelled','rejected' => 'danger',
                                default => 'warning text-dark'
                            } }}">
                                {{ match($b->booking_status) {
                                    'confirmed' => 'Xác nhận',
                                    'cancelled' => 'Đã hủy',
                                    'rejected'  => 'Từ chối',
                                    default     => 'Chờ duyệt'
                                } }}
                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            Ghế: <strong>{{ implode(', ', (array)$b->seat_numbers) }}</strong> ·
                            {{ ucfirst($b->schedule->vehicle->type) }}
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 small">
                            <span>Tổng: <strong>{{ number_format($b->total_price, 0, ',', '.') }} đ</strong></span>
                            <span class="badge bg-{{ match($b->payment_status) {
                                'paid' => 'primary', 'refunded' => 'secondary', default => 'warning text-dark'
                            } }}">
                                {{ match($b->payment_status) {
                                    'paid' => 'Đã thanh toán', 'refunded' => 'Hoàn tiền', default => 'Chưa thanh toán'
                                } }}
                            </span>
                        </div>
                        <div class="small text-muted mb-2">Mã vé: <code>{{ $b->ticket_code }}</code></div>
                        <div class="d-flex gap-2 flex-wrap">
                            @if($b->payment_status === 'unpaid' && !in_array($b->booking_status, ['cancelled','rejected']))
                                <form method="POST" action="{{ route('bookings.markPaid', $b) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">Xác nhận đã thanh toán</button>
                                </form>
                            @endif
                            @if(!in_array($b->booking_status, ['cancelled','rejected']))
                                <form method="POST" action="{{ route('bookings.cancel', $b) }}"
                                    onsubmit="return confirm('Hủy vé {{ $b->ticket_code }}?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">Hủy vé</button>
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
    const btn = document.getElementById('btnRandomDriver');
    if (!btn) return;

    @php
        $driverJson = $availableDrivers->map(function($d) {
            return [
                'name'  => $d->user->name,
                'class' => $d->license_class,
                'exp'   => $d->experience_years,
                'team'  => $d->operator?->name ?? '',
            ];
        })->values()->all();
    @endphp
    const drivers = @json($driverJson);

    btn.addEventListener('click', function () {
        if (!drivers.length) return;
        const d = drivers[Math.floor(Math.random() * drivers.length)];
        document.getElementById('randomAvatar').textContent = d.name.charAt(0);
        document.getElementById('randomName').textContent   = d.name;
        document.getElementById('randomMeta').textContent   =
            'Hạng ' + d.class + ' · ' + d.exp + ' năm KN' + (d.team ? ' · ' + d.team : '');
        document.getElementById('randomResult').style.display = 'block';
    });
})();
</script>
@endpush
