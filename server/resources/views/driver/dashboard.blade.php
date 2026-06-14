@extends('layouts.app')

@section('content')
<div class="row g-4">

    {{-- Cột trái: tóm tắt + trạng thái --}}
    <div class="col-lg-4">

        <div class="card shadow-sm p-4 mb-4">
            <div class="d-flex gap-3 align-items-start mb-3">
                <div class="flex-shrink-0">
                    @if($profile?->photo_portrait)
                        <img src="{{ $profile->photoUrl('photo_portrait') }}" alt="Chân dung"
                             class="rounded-circle object-fit-cover border" style="width:60px;height:60px;">
                    @else
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center border"
                             style="width:60px;height:60px;font-size:1.4rem;font-weight:700;">
                            {{ mb_substr($user->name, 0, 1) }}
                        </div>
                    @endif
                </div>
                <div>
                    <h5 class="mb-0 fw-bold">{{ $user->name }}</h5>
                    <span class="badge bg-primary small">Tài xế</span>
                    @if($profile)
                        <span class="badge bg-{{ match($profile->status) { 'active'=>'success','suspended'=>'danger',default=>'secondary' } }} small ms-1">
                            {{ match($profile->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' } }}
                        </span>
                    @endif
                </div>
            </div>

            @if($profile)
            <div class="d-flex flex-column gap-2 small mb-3">
                @if($profile->driver_code)
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Mã tài xế</span>
                    <code class="fw-bold">{{ $profile->driver_code }}</code>
                </div>
                @endif
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Điện thoại</span>
                    <span>{{ $user->phone ?? '—' }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Hạng bằng</span>
                    <span class="badge bg-primary">Hạng {{ $profile->license_class }}</span>
                </div>
                @if($profile->operator)
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Quản lý</span>
                    <span>{{ $profile->operator->name }}</span>
                </div>
                @endif
            </div>
            <a href="{{ route('driver.profile') }}" class="btn btn-outline-primary btn-sm w-100">
                Cập nhật hồ sơ & ảnh →
            </a>
            @else
            <div class="alert alert-warning py-2 small mb-0">
                Chưa có hồ sơ tài xế. Liên hệ quản lý.
            </div>
            @endif
        </div>

        @if($profile)
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Trạng thái hoạt động</h5>
            @php
                $avail = $profile->availability_status ?? 'off_duty';
                $availConfig = [
                    'available' => ['label' => 'Sẵn sàng nhận chuyến', 'color' => 'success',  'icon' => '🟢'],
                    'on_trip'   => ['label' => 'Đang chạy chuyến',     'color' => 'primary',   'icon' => '🔵'],
                    'off_duty'  => ['label' => 'Nghỉ / Không nhận',    'color' => 'secondary', 'icon' => '⚫'],
                ];
            @endphp
            <div class="mb-3 text-center">
                <span class="fs-4">{{ $availConfig[$avail]['icon'] }}</span>
                <div class="mt-1">
                    <span class="badge bg-{{ $availConfig[$avail]['color'] }} fs-6 px-3 py-2">
                        {{ $availConfig[$avail]['label'] }}
                    </span>
                </div>
            </div>
            <form method="POST" action="{{ route('driver.availability.update') }}">
                @csrf @method('PATCH')
                <div class="d-flex flex-column gap-2">
                    @foreach($availConfig as $val => $cfg)
                        <button type="submit" name="availability_status" value="{{ $val }}"
                            class="btn btn-{{ $avail === $val ? $cfg['color'] : 'outline-'.$cfg['color'] }} text-start">
                            {{ $cfg['icon'] }} {{ $cfg['label'] }}
                        </button>
                    @endforeach
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- Cột phải: yêu cầu + lịch chạy --}}
    <div class="col-lg-8">
        <div class="card shadow-sm p-4 mb-4" id="pending-requests-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title-bar mb-0">Yêu cầu nhận chuyến</h4>
                <small class="text-muted" id="driver-sync-indicator">Đang cập nhật...</small>
            </div>
            @if($pendingRequests->isEmpty())
                <p class="text-muted mb-0" id="no-pending-msg">Không có yêu cầu mới.</p>
            @else
                <div class="d-flex flex-column gap-3" id="pending-requests-list">
                    @foreach($pendingRequests as $req)
                    <div class="border border-warning rounded-3 p-3 bg-warning-subtle">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <strong>{{ $req->schedule->route->departure }} → {{ $req->schedule->route->destination }}</strong><br>
                                <span class="text-muted small">{{ $req->schedule->departure_time->format('H:i · d/m/Y') }}</span><br>
                                <span class="small">Khách: <strong>{{ $req->customer->name }}</strong> · {{ $req->customer->phone ?? '—' }}</span>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <form method="POST" action="{{ route('driver.tripRequests.accept', $req) }}">@csrf
                                    <button class="btn btn-success btn-sm">Nhận chuyến</button>
                                </form>
                                <form method="POST" action="{{ route('driver.tripRequests.reject', $req) }}">@csrf
                                    <button class="btn btn-outline-danger btn-sm">Từ chối</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card shadow-sm p-4">
            <h4 class="card-title-bar mb-3">Lịch chạy của tôi</h4>
            @if($schedules->isEmpty())
                <p class="text-muted">Chưa có lịch chạy nào được phân công.</p>
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach($schedules as $s)
                    @php $isToday = $s->departure_time->isToday(); @endphp
                    <div class="border rounded-3 p-3 {{ $isToday ? 'border-primary bg-light' : '' }}">
                        <div class="row align-items-center g-2">
                            <div class="col-md-4">
                                @if($isToday)
                                    <span class="badge bg-primary mb-1">Hôm nay</span><br>
                                @endif
                                <strong>{{ $s->route->departure }} → {{ $s->route->destination }}</strong><br>
                                <span class="text-muted small">{{ $s->departure_time->format('H:i · d/m/Y') }}</span>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Xe</span>
                                {{ ucfirst($s->vehicle->type) }}<br>
                                <small class="text-muted">{{ $s->vehicle->license_plate }} · {{ $s->vehicle->capacity }} ghế</small>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Đã đặt</span>
                                <strong>{{ $s->bookedSeatsCount() }}</strong>
                                <span class="text-muted">/ {{ $s->capacity() }} ghế</span>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge bg-{{ match($s->status) {
                                    'running'   => 'primary',
                                    'completed' => 'secondary',
                                    'cancelled' => 'danger',
                                    default     => 'warning text-dark'
                                } }}">
                                    {{ match($s->status) {
                                        'scheduled' => 'Đã lên lịch',
                                        'running'   => 'Đang chạy',
                                        'completed' => 'Hoàn thành',
                                        'cancelled' => 'Đã hủy',
                                        default     => ucfirst($s->status)
                                    } }}
                                </span>
                            </div>
                        </div>

                        @if($s->bookings->isNotEmpty())
                        <hr class="my-2">
                        <div class="small">
                            <span class="text-muted fw-semibold">Hành khách ({{ $s->bookings->count() }}):</span>
                            <div class="d-flex flex-column gap-2 mt-2">
                                @foreach($s->bookings as $booking)
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 rounded-2 px-3 py-2 border {{ $booking->isConfirmedForDriver() ? 'bg-success-subtle border-success' : 'bg-white' }}">
                                    <div>
                                        <strong>{{ $booking->customer->name }}</strong>
                                        <span class="text-muted"> · {{ $booking->customer->phone ?? '—' }}</span><br>
                                        <span class="text-muted">Ghế {{ implode(', ', (array) $booking->seat_numbers) }}</span>
                                        @if($booking->pickup_address)
                                            · <span class="text-muted">Đón: {{ $booking->pickup_address }}</span>
                                        @endif
                                        @if($booking->dropoff_address)
                                            · <span class="text-muted">Trả: {{ $booking->dropoff_address }}</span>
                                        @endif
                                    </div>
                                    <div class="text-end">
                                        @include('partials.booking-status', ['booking' => $booking])
                                        @if($booking->trip_status === 'confirmed')
                                            <form method="POST" action="{{ route('driver.bookings.complete', $booking) }}" class="mt-1"
                                                onsubmit="return confirm('Báo hoàn thành chuyến cho khách {{ $booking->customer->name }}?')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success">Báo hoàn thành chuyến</button>
                                            </form>
                                        @elseif($booking->trip_status === 'awaiting_completion')
                                            <span class="badge bg-info text-dark mt-1">Chờ khách xác nhận</span>
                                        @elseif($booking->trip_status === 'completed')
                                            <span class="badge bg-success mt-1">Đã hoàn tất</span>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @else
                        <hr class="my-2">
                        <p class="text-muted small mb-0">Chưa có hành khách đặt vé cho chuyến này.</p>
                        @endif
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
    var syncUrl = @json(route('driver.liveSync'));
    function poll() {
        fetch(syncUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var el = document.getElementById('driver-sync-indicator');
                if (el) el.textContent = 'Cập nhật: ' + new Date().toLocaleTimeString('vi-VN');
                var list = document.getElementById('pending-requests-list');
                var empty = document.getElementById('no-pending-msg');
                if (!data.pending_requests.length) {
                    if (list) list.innerHTML = '';
                    if (empty) empty.style.display = 'block';
                    return;
                }
                if (empty) empty.style.display = 'none';
                if (!list) return;
                list.innerHTML = data.pending_requests.map(function (req) {
                    return '<div class="border border-warning rounded-3 p-3 bg-warning-subtle">' +
                        '<strong>' + req.route + '</strong><br>' +
                        '<span class="text-muted small">' + req.departure_time + '</span><br>' +
                        '<span class="small">Khách: <strong>' + req.customer_name + '</strong></span>' +
                        '<div class="mt-2"><span class="badge bg-warning text-dark">Chờ bạn phản hồi</span></div></div>';
                }).join('');
            }).catch(function () {});
    }
    poll();
    setInterval(poll, 10000);
})();
</script>
@endpush
