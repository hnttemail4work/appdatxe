@php /** @var \Illuminate\Support\Collection<int, \App\Models\CancellationReason> $reasons */ @endphp
<div class="row g-4">
    <div class="col-lg-5">
        <h3 class="h6 text-muted mb-3">Thêm lý do hủy</h3>
        <form method="POST" action="{{ route('admin.cancellationReasons.store') }}" class="console-form">
            @csrf
            <div class="mb-3">
                <label class="form-label">Nội dung lý do <span class="text-danger">*</span></label>
                <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                       value="{{ old('label') }}" maxlength="200" required placeholder="VD: Đổi lịch đi lại">
                @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Áp dụng cho</label>
                <select name="audience" class="form-select @error('audience') is-invalid @enderror">
                    <option value="both" @selected(old('audience', 'both') === 'both')>Khách & tài xế</option>
                    <option value="customer" @selected(old('audience') === 'customer')>Chỉ khách hàng</option>
                    <option value="driver" @selected(old('audience') === 'driver')>Chỉ tài xế</option>
                </select>
                @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Thứ tự hiển thị</label>
                <input type="number" name="sort_order" class="form-control" min="0" max="9999"
                       value="{{ old('sort_order', 0) }}">
            </div>
            <button class="btn btn-primary">Thêm lý do</button>
        </form>
        <p class="small text-muted mt-3 mb-0">
            Khách hủy lần thứ 4 trở đi và tài xế hủy chuyến phải chọn một lý do trong danh sách.
        </p>
    </div>
    <div class="col-lg-7">
        <h3 class="h6 text-muted mb-3">Danh sách lý do</h3>
        @if($reasons->isEmpty())
            <div class="console-empty py-4">
                <p class="mb-0">Chưa có lý do nào — thêm ít nhất một mục để khách/tài xế chọn khi hủy.</p>
            </div>
        @else
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Lý do</th>
                            <th>Đối tượng</th>
                            <th>TT</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reasons as $reason)
                        <tr class="{{ ! $reason->is_active ? 'opacity-50' : '' }}">
                            <td>{{ $reason->label }}</td>
                            <td class="small cell-muted">{{ $reason->audienceLabel() }}</td>
                            <td class="small">{{ $reason->sort_order }}</td>
                            <td>
                                <span class="status-pill status-pill--{{ $reason->is_active ? 'success' : 'neutral' }}">
                                    {{ $reason->is_active ? 'Đang dùng' : 'Đã ẩn' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if($reason->is_active)
                                <form method="POST" action="{{ route('admin.cancellationReasons.destroy', $reason) }}"
                                      data-confirm="Xóa lý do «{{ $reason->label }}»?"
                                      data-confirm-variant="danger"
                                      data-confirm-ok="Xóa">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
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
