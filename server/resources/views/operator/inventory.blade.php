@extends('layouts.app')

@section('content')
@php
$categories = ['fuel' => 'Nhiên liệu', 'tire' => 'Lốp xe', 'spare_part' => 'Phụ tùng', 'other' => 'Khác'];
@endphp
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0 card-title-bar">Nhập xuất vật tư</h3>
            <p class="text-muted mb-0">Theo dõi nhiên liệu, lốp xe, phụ tùng cho đội xe.</p>
        </div>
        <a href="{{ route('operator.dashboard') }}" class="btn btn-outline-primary btn-sm">← Về Dashboard</a>
    </div>

    {{-- Tóm tắt --}}
    <div class="col-md-4">
        <div class="card shadow-sm p-3 text-center" style="border-left:4px solid #0d6efd;">
            <div class="text-muted small mb-1">Tổng nhập</div>
            <strong class="text-primary fs-5">{{ number_format($summary['total_import'], 0, ',', '.') }} đ</strong>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm p-3 text-center" style="border-left:4px solid #dc3545;">
            <div class="text-muted small mb-1">Tổng xuất</div>
            <strong class="text-danger fs-5">{{ number_format($summary['total_export'], 0, ',', '.') }} đ</strong>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm p-3 text-center" style="border-left:4px solid #6c757d;">
            <div class="text-muted small mb-1">Số giao dịch</div>
            <strong class="text-secondary fs-5">{{ $items->count() }}</strong>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm p-4">
            <h5>Thêm giao dịch</h5>
            <form method="POST" action="{{ route('operator.inventory.store') }}" class="mt-3">
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
                    <div class="col-5">
                        <label class="form-label">Số lượng</label>
                        <input type="number" name="quantity" value="{{ old('quantity') }}" step="0.01" min="0.01" class="form-control @error('quantity') is-invalid @enderror" required>
                        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-4">
                        <label class="form-label">Đơn vị</label>
                        <select name="unit" class="form-select">
                            @foreach(['lít','kg','cái','bộ','m','cuộn'] as $u)
                                <option value="{{ $u }}" {{ old('unit') === $u ? 'selected' : '' }}>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label">&nbsp;</label>
                        <input type="number" name="unit_price" value="{{ old('unit_price',0) }}" step="1000" min="0" class="form-control" placeholder="Giá/đơn vị">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Xe áp dụng</label>
                    <select name="vehicle_id" class="form-select">
                        <option value="">-- Tất cả / Không chọn --</option>
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
                    <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú thêm...">{{ old('note') }}</textarea>
                </div>
                <button class="btn btn-primary w-100">Lưu giao dịch</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm p-4">
            <h5>Lịch sử giao dịch</h5>
            @if($items->isEmpty())
                <p class="text-muted mt-3">Chưa có giao dịch nào.</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-borderless align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ngày</th>
                                <th>Tên vật tư</th>
                                <th>Danh mục</th>
                                <th>SL</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                                <th>Loại</th>
                                <th>Xe</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                            <tr class="border-bottom">
                                <td>{{ $item->transaction_date->format('d/m/Y') }}</td>
                                <td><strong>{{ $item->name }}</strong>@if($item->note)<br><small class="text-muted">{{ $item->note }}</small>@endif</td>
                                <td><span class="badge bg-secondary">{{ $categories[$item->category] ?? $item->category }}</span></td>
                                <td>{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                                <td>{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td><strong>{{ number_format($item->total_value, 0, ',', '.') }} đ</strong></td>
                                <td>
                                    <span class="badge bg-{{ $item->type === 'import' ? 'success' : 'danger' }}">
                                        {{ $item->type === 'import' ? 'Nhập' : 'Xuất' }}
                                    </span>
                                </td>
                                <td>{{ $item->vehicle?->license_plate ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('operator.inventory.destroy', $item) }}"
                                        onsubmit="return confirm('Xóa bản ghi này?')">
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
        </div>
    </div>
</div>
@endsection
