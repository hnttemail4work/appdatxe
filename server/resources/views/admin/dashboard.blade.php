@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="card-title-bar mb-1">Quản trị hệ thống</h3>
                    <p class="text-muted mb-0">Toàn quyền quản lý người dùng, tài xế, booking và cấu hình.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('operator.drivers') }}" class="btn btn-outline-secondary btn-sm">Tất cả tài xế</a>
                    <a href="{{ route('admin.bookings') }}" class="btn btn-primary btn-sm">Booking & Chi tiêu →</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Hoa hồng + Tài khoản chờ duyệt --}}
    <div class="col-lg-6">
        <div class="card shadow-sm p-4 mb-4">
            <h5 class="card-title-bar">Thiết lập hoa hồng</h5>
            <form method="POST" action="{{ route('admin.commission.update') }}" class="mt-3">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Tỷ lệ hoa hồng (%)</label>
                    <input type="number" name="commission_percentage"
                        value="{{ $commissionSetting['value'] ?? 10 }}"
                        min="0" max="100" step="0.1" class="form-control" required>
                    <div class="form-text">Phần trăm VinaRoute thu trên mỗi giao dịch.</div>
                </div>
                <button class="btn btn-primary">Cập nhật</button>
            </form>
        </div>

        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar">Tài khoản chờ duyệt</h5>
            @if($merchants->isEmpty())
                <p class="text-muted mt-2">Không có tài khoản nào chờ duyệt.</p>
            @else
                <div class="d-flex flex-column gap-3 mt-3">
                    @foreach($merchants as $merchant)
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong>{{ $merchant->user->name }}</strong>
                                    <small class="text-muted ms-2">{{ $merchant->user->email }}</small><br>
                                    <span class="badge bg-{{ match($merchant->kyc_status) {
                                        'approved'=>'primary','rejected'=>'danger',default=>'warning text-dark'
                                    } }}">
                                        {{ match($merchant->kyc_status) { 'approved'=>'Đã duyệt','rejected'=>'Từ chối',default=>'Chờ duyệt' } }}
                                    </span>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    @if($merchant->kyc_status !== 'approved')
                                    <form method="POST" action="{{ route('admin.merchants.approve', $merchant) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-primary">Duyệt</button>
                                    </form>
                                    @endif
                                    @if($merchant->kyc_status !== 'rejected')
                                    <form method="POST" action="{{ route('admin.merchants.reject', $merchant) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Từ chối</button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.merchants.suspend', $merchant) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">Tạm ngưng</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Đơn hàng gần đây --}}
    <div class="col-lg-6">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar">Đơn hàng gần đây</h5>
            @if($orderSummary->isEmpty())
                <p class="text-muted mt-2">Chưa có đơn hàng.</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr><th>Mã</th><th>Khách</th><th>Chuyến</th><th>TT</th></tr>
                        </thead>
                        <tbody>
                            @foreach($orderSummary as $booking)
                                <tr class="border-bottom">
                                    <td><code class="small">{{ $booking->booking_reference }}</code></td>
                                    <td>{{ $booking->customer->name }}</td>
                                    <td class="small">{{ $booking->schedule->route->departure }} → {{ $booking->schedule->route->destination }}</td>
                                    <td>
                                        <span class="badge bg-{{ match($booking->payment_status) { 'paid'=>'primary','refunded'=>'secondary',default=>'warning text-dark' } }}">
                                            {{ match($booking->payment_status) { 'paid'=>'Đã TT','refunded'=>'Hoàn',default=>'Chưa TT' } }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Danh sách Quản lý (Operator) --}}
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Danh sách Quản lý ({{ $operators->count() }})</h5>
            @if($operators->isEmpty())
                <p class="text-muted">Chưa có quản lý nào.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Tài xế</th><th>Trạng thái</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            @foreach($operators as $op)
                            @php $opDrivers = $drivers->where('operator_id', $op->id); @endphp
                            <tr class="border-bottom">
                                <td><strong>{{ $op->name }}</strong></td>
                                <td class="text-muted small">{{ $op->email }}</td>
                                <td class="text-muted small">{{ $op->phone ?? '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $opDrivers->count() }} tài xế</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.users.status', $op) }}"
                                          class="d-flex gap-1 align-items-center">
                                        @csrf @method('PATCH')
                                        <select name="status" class="form-select form-select-sm" style="width:120px">
                                            @foreach(['active','inactive','suspended'] as $st)
                                                <option value="{{ $st }}" {{ $op->status === $st ? 'selected' : '' }}>
                                                    {{ match($st){ 'active'=>'Hoạt động','inactive'=>'Không HĐ','suspended'=>'Tạm ngưng' } }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                    </form>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#edit-op-{{ $op->id }}">Sửa</button>
                                </td>
                            </tr>
                            <tr class="collapse" id="edit-op-{{ $op->id }}">
                                <td colspan="6" class="bg-light">
                                    <form method="POST" action="{{ route('admin.users.update', $op) }}"
                                          class="row g-2 p-2">
                                        @csrf @method('PATCH')
                                        <div class="col-md-3">
                                            <input type="text" name="name" value="{{ $op->name }}" class="form-control form-control-sm" placeholder="Họ tên" required>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="email" name="email" value="{{ $op->email }}" class="form-control form-control-sm" placeholder="Email" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="tel" name="phone" value="{{ $op->phone }}" class="form-control form-control-sm" placeholder="SĐT">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Mật khẩu mới">
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-sm btn-primary w-100">Lưu</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Danh sách tài xế --}}
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Danh sách tài xế ({{ $drivers->count() }})</h5>
            @if($drivers->isEmpty())
                <p class="text-muted">Chưa có tài xế nào.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Hạng</th><th>Kinh nghiệm</th><th>Quản lý bởi</th><th>Trạng thái</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            @foreach($drivers as $d)
                                <tr class="border-bottom">
                                    <td><strong>{{ $d->user->name }}</strong></td>
                                    <td class="text-muted small">{{ $d->user->email }}</td>
                                    <td class="text-muted small">{{ $d->user->phone ?? '—' }}</td>
                                    <td><span class="badge bg-primary">Hạng {{ $d->license_class }}</span></td>
                                    <td>{{ $d->experience_years }} năm</td>
                                    <td>{{ $d->operator?->name ?? '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.users.status', $d->user) }}"
                                              class="d-flex gap-1 align-items-center">
                                            @csrf @method('PATCH')
                                            <select name="status" class="form-select form-select-sm" style="width:120px">
                                                @foreach(['active','inactive','suspended'] as $st)
                                                    <option value="{{ $st }}" {{ $d->user->status === $st ? 'selected' : '' }}>
                                                        {{ match($st){ 'active'=>'Hoạt động','inactive'=>'Không HĐ','suspended'=>'Tạm ngưng' } }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#edit-drv-{{ $d->id }}">Sửa</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="edit-drv-{{ $d->id }}">
                                    <td colspan="8" class="bg-light">
                                        <div class="p-2">
                                            {{-- Edit user info --}}
                                            <small class="fw-semibold text-muted d-block mb-1">Thông tin tài khoản</small>
                                            <form method="POST" action="{{ route('admin.users.update', $d->user) }}"
                                                  class="row g-2 mb-3">
                                                @csrf @method('PATCH')
                                                <div class="col-md-3">
                                                    <label class="form-label mb-0 small">Họ tên</label>
                                                    <input type="text" name="name" value="{{ $d->user->name }}" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label mb-0 small">Email</label>
                                                    <input type="email" name="email" value="{{ $d->user->email }}" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-0 small">Điện thoại</label>
                                                    <input type="tel" name="phone" value="{{ $d->user->phone }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-0 small">CCCD/CMND</label>
                                                    <input type="text" name="id_number" value="{{ $d->user->id_number }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-0 small">Mật khẩu mới</label>
                                                    <input type="password" name="password" class="form-control form-control-sm" placeholder="(để trống = không đổi)">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label mb-0 small">Địa chỉ</label>
                                                    <input type="text" name="address" value="{{ $d->user->address }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button class="btn btn-sm btn-primary w-100">Lưu tài khoản</button>
                                                </div>
                                            </form>
                                            {{-- Edit driver profile --}}
                                            <small class="fw-semibold text-muted d-block mb-1">Hồ sơ tài xế</small>
                                            <form method="POST" action="{{ route('admin.drivers.update', $d) }}"
                                                  class="row g-2">
                                                @csrf @method('PATCH')
                                                <div class="col-md-2">
                                                    <label class="form-label mb-0 small">Số bằng lái</label>
                                                    <input type="text" name="license_number" value="{{ $d->license_number }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-1">
                                                    <label class="form-label mb-0 small">Hạng</label>
                                                    <select name="license_class" class="form-select form-select-sm">
                                                        @foreach(['B1','B2','C','D','E','F'] as $cls)
                                                            <option value="{{ $cls }}" {{ $d->license_class === $cls ? 'selected':'' }}>{{ $cls }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-0 small">Hết hạn bằng</label>
                                                    <input type="date" name="license_expiry" value="{{ $d->license_expiry->format('Y-m-d') }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-1">
                                                    <label class="form-label mb-0 small">Năm KN</label>
                                                    <input type="number" name="experience_years" value="{{ $d->experience_years }}" min="0" max="50" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-0 small">Quản lý bởi</label>
                                                    <select name="operator_id" class="form-select form-select-sm">
                                                        <option value="">— Không có —</option>
                                                        @foreach($operators as $op)
                                                            <option value="{{ $op->id }}" {{ $d->operator_id == $op->id ? 'selected':'' }}>{{ $op->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-0 small">Ghi chú</label>
                                                    <input type="text" name="notes" value="{{ $d->notes }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button class="btn btn-sm btn-primary w-100">Lưu hồ sơ</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Danh sách khách hàng --}}
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Danh sách khách hàng ({{ $customers->count() }})</h5>
            @if($customers->isEmpty())
                <p class="text-muted">Chưa có khách hàng nào.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Ngày ĐK</th><th>Trạng thái</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $c)
                                <tr class="border-bottom">
                                    <td><strong>{{ $c->name }}</strong></td>
                                    <td class="text-muted small">{{ $c->email }}</td>
                                    <td class="text-muted small">{{ $c->phone ?? '—' }}</td>
                                    <td class="text-muted small">{{ $c->created_at->format('d/m/Y') }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.users.status', $c) }}"
                                              class="d-flex gap-1 align-items-center">
                                            @csrf @method('PATCH')
                                            <select name="status" class="form-select form-select-sm" style="width:120px">
                                                @foreach(['active','inactive','suspended'] as $st)
                                                    <option value="{{ $st }}" {{ $c->status === $st ? 'selected' : '' }}>
                                                        {{ match($st){ 'active'=>'Hoạt động','inactive'=>'Không HĐ','suspended'=>'Tạm ngưng' } }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#edit-cust-{{ $c->id }}">Sửa</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="edit-cust-{{ $c->id }}">
                                    <td colspan="6" class="bg-light">
                                        <form method="POST" action="{{ route('admin.users.update', $c) }}"
                                              class="row g-2 p-2">
                                            @csrf @method('PATCH')
                                            <div class="col-md-3">
                                                <input type="text" name="name" value="{{ $c->name }}" class="form-control form-control-sm" placeholder="Họ tên" required>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="email" name="email" value="{{ $c->email }}" class="form-control form-control-sm" placeholder="Email" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="tel" name="phone" value="{{ $c->phone }}" class="form-control form-control-sm" placeholder="SĐT">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="password" name="password" class="form-control form-control-sm" placeholder="Mật khẩu mới">
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-sm btn-primary w-100">Lưu</button>
                                            </div>
                                        </form>
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
