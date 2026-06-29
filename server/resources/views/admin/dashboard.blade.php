@extends('layouts.console')

@section('console')
@php
$adminDefaultTab = request('tab');
if (! in_array($adminDefaultTab, ['create', 'list', 'fees'], true)) {
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
                    'activeKey' => 'create',
                    'tabs' => [
                        ['key' => 'create', 'label' => 'Tạo quản lý'],
                        ['key' => 'list', 'label' => 'Danh sách', 'badge' => $operators->total()],
                    ],
                ])

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'create', 'active' => true])
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

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'list', 'active' => false])
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

                @include('partials.screen-tabs-end')
            </div>
        </div>
    </div>
</div>
@endsection
