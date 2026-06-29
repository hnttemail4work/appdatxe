@extends('layouts.console')

@section('console')
@php
$adminDefaultTab = request('tab');
if (! in_array($adminDefaultTab, ['create', 'list', 'fees', 'routes'], true)) {
    $adminDefaultTab = 'create';
}
@endphp
@include('partials.console-hero', [
    'title' => 'Quản trị hệ thống',
])

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="console-panel">
            <div class="console-panel-body">
                @include('partials.screen-tabs-start', [
                    'prefix' => 'admin-main',
                    'activeKey' => $adminDefaultTab,
                    'tabs' => [
                        ['key' => 'create', 'label' => 'Tạo quản lý'],
                        ['key' => 'list', 'label' => 'Danh sách', 'badge' => $operators->total()],
                        ['key' => 'fees', 'label' => 'Phí & giá'],
                        ['key' => 'routes', 'label' => 'Quãng đường'],
                    ],
                ])

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'create', 'active' => $adminDefaultTab === 'create'])
                <form method="POST" action="{{ route('admin.operators.store') }}" class="console-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Họ tên</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thư điện tử đăng nhập</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" name="password" class="form-control" minlength="8" required>
                        </div>
                    </div>
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Tạo quản lý</button>
                </form>
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'list', 'active' => $adminDefaultTab === 'list'])
                @if($operators->isEmpty())
                    <div class="console-empty py-4"><p class="mb-0">Chưa có quản lý nào.</p></div>
                @else
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Họ tên</th>
                                    <th>Thư điện tử</th>
                                    <th>SĐT</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($operators as $op)
                                <tr>
                                    <td class="cell-primary">{{ $op->name }}</td>
                                    <td class="cell-muted">{{ $op->email }}</td>
                                    <td class="cell-muted">{{ $op->phone ?? '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.users.status', $op) }}" class="d-flex gap-1 align-items-center">
                                            @csrf @method('PATCH')
                                            <select name="status" class="form-select form-select-sm" style="width:130px">
                                                @foreach(['active','inactive','suspended'] as $st)
                                                    <option value="{{ $st }}" {{ $op->status === $st ? 'selected' : '' }}>
                                                        {{ match($st){ 'active'=>'Hoạt động','inactive'=>'Vô hiệu','suspended'=>'Tạm ngưng' } }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('partials.pagination', ['paginator' => $operators])
                @endif
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'fees', 'active' => $adminDefaultTab === 'fees'])
                <form method="POST" action="{{ route('admin.feeSettings.update') }}" class="console-form">
                    @csrf
                    <p class="text-muted small">Giá tính theo km: ≤100 km dùng đơn giá thấp hơn, &gt;100 km dùng đơn giá cao hơn. Khứ hồi giảm theo % bên dưới (mặc định 15%).</p>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Giá / km (≤ 100 km)</label>
                            <div class="input-group">
                                <input type="number" name="km_rate_under_100" class="form-control" min="0" step="500"
                                       value="{{ old('km_rate_under_100', $feeSettings['km_rate_under_100']) }}" required>
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Giá / km (&gt; 100 km)</label>
                            <div class="input-group">
                                <input type="number" name="km_rate_over_100" class="form-control" min="0" step="500"
                                       value="{{ old('km_rate_over_100', $feeSettings['km_rate_over_100']) }}" required>
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Giảm giá khứ hồi (%)</label>
                            <div class="input-group">
                                <input type="number" name="round_trip_discount" class="form-control" min="0" max="100" step="0.5"
                                       value="{{ old('round_trip_discount', $feeSettings['round_trip_discount']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Hoa hồng app (%)</label>
                            <div class="input-group">
                                <input type="number" name="app_commission" class="form-control" min="0" max="100" step="0.1"
                                       value="{{ old('app_commission', $feeSettings['app_commission']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu cài đặt</button>
                </form>
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'routes', 'active' => $adminDefaultTab === 'routes'])
                <p class="text-muted small mb-3">Quãng đường cố định từ TP.HCM — dùng tự điền km khi quản lý tạo chuyến.</p>
                <form method="POST" action="{{ route('admin.routeDistances.update') }}" class="console-form">
                    @csrf
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Điểm đến</th>
                                    <th style="width:160px">Km</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($hubRoutes as $i => $hubRoute)
                                <tr>
                                    <td class="cell-primary">{{ $hubRoute->destination }}</td>
                                    <td>
                                        <input type="hidden" name="routes[{{ $i }}][id]" value="{{ $hubRoute->id }}">
                                        <input type="number" name="routes[{{ $i }}][distance_km]" class="form-control form-control-sm"
                                               min="1" max="2000" required value="{{ old('routes.'.$i.'.distance_km', $hubRoute->distance_km) }}">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu quãng đường</button>
                </form>
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tabs-end')
            </div>
        </div>
    </div>
</div>
@endsection
