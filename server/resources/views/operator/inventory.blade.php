@extends('layouts.console')

@section('console')
@php
$categories = ['fuel' => 'Nhiên liệu', 'tire' => 'Lốp xe', 'spare_part' => 'Phụ tùng', 'other' => 'Khác'];
$inventoryDefaultTab = old('name') || old('quantity') ? 'add' : 'history';
@endphp

<div class="console-stats mb-4">
    @include('partials.console-stat', ['icon' => '📥', 'value' => number_format($summary['total_import'], 0, ',', '.') . ' đ', 'label' => 'Tổng nhập', 'tone' => 'success'])
    @include('partials.console-stat', ['icon' => '📤', 'value' => number_format($summary['total_export'], 0, ',', '.') . ' đ', 'label' => 'Tổng xuất', 'tone' => 'danger'])
    @include('partials.console-stat', ['icon' => '📋', 'value' => $items->count(), 'label' => 'Giao dịch', 'tone' => 'primary'])
</div>

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.screen-tabs-start', [
            'prefix' => 'inventory-main',
            'activeKey' => $inventoryDefaultTab,
            'tabs' => [
                ['key' => 'add', 'label' => 'Thêm giao dịch'],
                ['key' => 'history', 'label' => 'Lịch sử', 'badge' => $items->count()],
            ],
        ])

        @include('partials.screen-tab-pane', ['prefix' => 'inventory-main', 'key' => 'add', 'active' => $inventoryDefaultTab === 'add'])
        <div class="console-panel-head px-0 pt-0">
            <div class="console-panel-head-accent">
                <h2>Thêm giao dịch</h2>
            </div>
        </div>
        <form method="POST" action="{{ route('operator.inventory.store') }}" class="console-form">
            @csrf
            <div class="mb-3">
                <label class="form-label">Loại giao dịch</label>
                <select name="type" class="form-select" required>
                    <option value="import" {{ old('type','import') === 'import' ? 'selected' : '' }}>Nhập vật tư</option>
                    <option value="export" {{ old('type') === 'export' ? 'selected' : '' }}>Xuất/Sử dụng</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Danh mục</label>
                <select name="category" class="form-select" required>
                    @foreach($categories as $val => $label)
                        <option value="{{ $val }}" {{ old('category') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Tên vật tư <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror"
                    placeholder="vd: Dầu nhớt Castrol 10W40" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Số lượng</label>
                    <input type="number" name="quantity" value="{{ old('quantity') }}" step="0.01" min="0.01" class="form-control @error('quantity') is-invalid @enderror" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Đơn vị</label>
                    <select name="unit" class="form-select">
                        @foreach(['lít','kg','cái','bộ','m','cuộn'] as $u)
                            <option value="{{ $u }}" {{ old('unit') === $u ? 'selected' : '' }}>{{ $u }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Giá/ĐV</label>
                    <input type="number" name="unit_price" value="{{ old('unit_price',0) }}" step="1000" min="0" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Xe áp dụng</label>
                <select name="vehicle_id" class="form-select">
                    <option value="">— Tất cả —</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v->id }}" {{ old('vehicle_id') == $v->id ? 'selected' : '' }}>
                            {{ $v->license_plate }} · {{ ucfirst($v->type) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Ngày giao dịch</label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Ghi chú</label>
                <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
            </div>
            <button class="btn btn-primary fw-semibold">Lưu giao dịch</button>
        </form>
        @include('partials.screen-tab-pane-end')

        @include('partials.screen-tab-pane', ['prefix' => 'inventory-main', 'key' => 'history', 'active' => $inventoryDefaultTab === 'history'])
        <div class="console-panel-head px-0 pt-0">
            <div class="console-panel-head-accent">
                <h2>Lịch sử giao dịch</h2>
                <p class="subtitle">{{ $items->count() }} bản ghi</p>
            </div>
        </div>
        @if($items->isEmpty())
            <div class="console-empty">
                <div class="console-empty-icon">📦</div>
                <p class="mb-0">Chưa có giao dịch nào.</p>
            </div>
        @else
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Ngày</th><th>Vật tư</th><th>DM</th><th>SL</th>
                            <th>Đơn giá</th><th>Thành tiền</th><th>Loại</th><th>Xe</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        <tr>
                            <td class="cell-muted">{{ $item->transaction_date->format('d/m/Y') }}</td>
                            <td>
                                <div class="cell-primary">{{ $item->name }}</div>
                                @if($item->note)<div class="cell-muted">{{ $item->note }}</div>@endif
                            </td>
                            <td><span class="badge bg-secondary">{{ $categories[$item->category] ?? $item->category }}</span></td>
                            <td>{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                            <td class="cell-muted">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td class="cell-primary">{{ number_format($item->total_value, 0, ',', '.') }} đ</td>
                            <td>
                                <span class="badge bg-{{ $item->type === 'import' ? 'success' : 'danger' }}">
                                    {{ $item->type === 'import' ? 'Nhập' : 'Xuất' }}
                                </span>
                            </td>
                            <td class="cell-muted">{{ $item->vehicle?->license_plate ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('operator.inventory.destroy', $item) }}"
                                    data-confirm="Xóa bản ghi này?"
                                    data-confirm-variant="danger"
                                    data-confirm-ok="Xóa">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @include('partials.screen-tab-pane-end')

        @include('partials.screen-tabs-end')
    </div>
</div>
@endsection
