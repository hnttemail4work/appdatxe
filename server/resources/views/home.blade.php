@extends('layouts.app')

@section('content')
<div class="hero-section rounded-4 mb-4" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: #fff; padding: 60px 40px 80px;">
    <div class="row align-items-center">
        <div class="col-lg-6">
            <h1 class="mb-3" style="font-size:2.2rem; font-weight:800; line-height:1.2;">
                Đặt vé xe liên tỉnh
            </h1>
            <p class="mb-4" style="font-size:1.05rem; opacity:.9;">
                Tìm chuyến, chọn ghế, đặt vé nhanh.
            </p>
            @guest
                <a href="{{ route('booking.index') }}" class="btn btn-light text-primary fw-bold me-2">Đặt vé ngay</a>
                <a href="{{ route('register') }}" class="btn btn-outline-light">Đăng ký tài xế</a>
            @else
                @php $role = auth()->user()->role; @endphp
                @if(in_array($role, ['operator', 'admin', 'driver'], true))
                    <a href="{{ route($role === 'driver' ? 'driver.dashboard' : ($role === 'admin' ? 'admin.dashboard' : 'operator.dashboard')) }}" class="btn btn-light text-primary fw-bold">
                        {{ $role === 'admin' ? 'Quản trị' : ($role === 'driver' ? 'Xem lịch của tôi' : 'Vào tổng quan') }}
                    </a>
                @else
                    <a href="{{ route('booking.index') }}" class="btn btn-light text-primary fw-bold">Đặt vé ngay</a>
                @endif
            @endguest
        </div>
        <div class="col-lg-6 d-none d-lg-block text-center">
            <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=600&q=80"
                 alt="Xe VIP" class="img-fluid rounded-4" style="max-height:260px; object-fit:cover;">
        </div>
    </div>
</div>

{{-- Search card --}}
<div class="card shadow-sm p-4 mb-5" style="margin-top: -40px; position:relative; z-index:10;">
    <h5 class="fw-bold mb-4">Tìm chuyến xe</h5>
    <form action="{{ route('trips.search') }}" method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Điểm đi</label>
                <select name="departure" class="form-select">
                    <option value="">Tất cả</option>
                    @include('partials.province-options', ['selected' => request('departure', '')])
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Điểm đến</label>
                <select name="destination" class="form-select">
                    <option value="">Tất cả</option>
                    @include('partials.province-options', ['selected' => request('destination', '')])
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ngày đi</label>
                <input type="date" name="date" class="form-control" id="homeDate">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Tìm chuyến</button>
            </div>
        </div>
        @guest
            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">
                Không cần tài khoản — <a href="{{ route('booking.index') }}">đặt ngay</a>.
            </p>
        @endguest
    </form>
</div>

{{-- Popular routes --}}
<div class="row g-4 mb-5">
    <div class="col-12"><h4 class="fw-bold">Tuyến phổ biến</h4></div>
    @foreach([
        ['TP.HCM → Vũng Tàu', '200.000 đ', 'Limousine VIP · ~2 giờ · Hàng ngày'],
        ['TP.HCM → Đà Lạt',   '350.000 đ', 'Limousine VIP · ~7 giờ · Ban đêm'],
        ['TP.HCM → Mũi Né',   '280.000 đ', 'Sedan · ~4 giờ · Hàng ngày'],
    ] as [$route, $price, $desc])
    <div class="col-md-4">
        <div class="card p-4 h-100 border shadow-sm" style="border-radius:1rem;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <strong>{{ $route }}</strong>
                <span class="badge bg-primary">{{ $price }}</span>
            </div>
            <p class="text-muted mb-3" style="font-size:.9rem;">{{ $desc }}</p>
            <a href="{{ route('booking.index') }}"
               class="btn btn-sm btn-outline-primary mt-auto">Đặt vé</a>
        </div>
    </div>
    @endforeach
</div>

{{-- Why Tam Long Limo --}}
<div class="row g-4 mb-5">
    <div class="col-12"><h4 class="fw-bold">Tại sao chọn {{ config('app.name') }}?</h4></div>
    @foreach([
        ['🪑', 'Chọn ghế linh hoạt', 'Chọn ghế theo ý bạn.'],
        ['💳', 'Thanh toán đơn giản', 'Nhận mã vé ngay sau khi đặt.'],
        ['🔔', 'Quản lý đơn dễ dàng', 'Quản lý vé trong tài khoản.'],
    ] as [$icon, $title, $desc])
    <div class="col-md-4 text-center">
        <div class="p-4 bg-white rounded-4 border h-100 shadow-sm">
            <div class="fs-2 mb-2">{{ $icon }}</div>
            <h6 class="fw-bold">{{ $title }}</h6>
            <p class="text-muted small mb-0">{{ $desc }}</p>
        </div>
    </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const d = document.getElementById('homeDate');
    if (d) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        d.value = tomorrow.toISOString().split('T')[0];
    }
});
</script>
@endpush
