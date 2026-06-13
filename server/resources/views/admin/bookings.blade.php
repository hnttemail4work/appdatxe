@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title-bar mb-1">Quản lý Booking &amp; Chi tiêu</h3>
                <p class="text-muted mb-0">Toàn bộ đặt chuyến trong hệ thống.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-primary btn-sm">← Về Dashboard</a>
        </div>
    </div>

    {{-- Thống kê tổng quan --}}
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['total']) }}</div>
            <div class="text-muted small">Tổng booking</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-success">{{ number_format($stats['paid']) }}</div>
            <div class="text-muted small">Đã thanh toán</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-warning">{{ number_format($stats['unpaid']) }}</div>
            <div class="text-muted small">Chưa thanh toán</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['revenue'], 0, ',', '.') }} đ</div>
            <div class="text-muted small">Doanh thu (đã TT)</div>
        </div>
    </div>

    {{-- Bảng booking --}}
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Chi tiết booking</h5>
            <div class="table-responsive">
                <table class="table table-borderless align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Mã</th>
                            <th>Khách hàng</th>
                            <th>Chuyến</th>
                            <th>Tài xế</th>
                            <th>Ghế</th>
                            <th>Tổng tiền</th>
                            <th>Thanh toán</th>
                            <th>Booking</th>
                            <th>Ngày đặt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bookings as $b)
                        <tr class="border-bottom">
                            <td>
                                <code class="small">{{ $b->ticket_code }}</code><br>
                                <span class="text-muted" style="font-size:.7rem;">{{ $b->booking_reference }}</span>
                            </td>
                            <td>
                                <strong>{{ $b->customer->name }}</strong><br>
                                <small class="text-muted">{{ $b->customer->email }}</small>
                            </td>
                            <td>
                                <strong>{{ $b->schedule->route->departure }} → {{ $b->schedule->route->destination }}</strong><br>
                                <small class="text-muted">{{ $b->schedule->departure_time->format('H:i d/m/Y') }}</small><br>
                                <small class="text-muted">{{ ucfirst($b->schedule->vehicle->type) }} · {{ $b->schedule->vehicle->license_plate }}</small>
                            </td>
                            <td class="small">
                                @if($b->schedule->driver)
                                    {{ $b->schedule->driver->name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">{{ implode(', ', (array) $b->seat_numbers) }}</td>
                            <td class="fw-semibold">{{ number_format($b->total_price, 0, ',', '.') }} đ</td>
                            <td>
                                <span class="badge bg-{{ match($b->payment_status) {
                                    'paid'=>'primary','refunded'=>'secondary',default=>'warning text-dark'
                                } }}">
                                    {{ match($b->payment_status) { 'paid'=>'Đã TT','refunded'=>'Hoàn tiền',default=>'Chưa TT' } }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ match($b->booking_status) {
                                    'confirmed'=>'primary','cancelled','rejected'=>'danger',default=>'warning text-dark'
                                } }}">
                                    {{ match($b->booking_status) {
                                        'confirmed'=>'Xác nhận','cancelled'=>'Đã hủy','rejected'=>'Từ chối',default=>'Chờ'
                                    } }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $b->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-3">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
