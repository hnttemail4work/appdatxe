@extends('layouts.app')

@section('content')
<div class="console-page">
    @include('partials.admin-nav-tabs', ['active' => 'driver-inbox'])

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 mt-3">
        <h1 class="h4 mb-0">Gửi tin tài xế</h1>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="text-muted small mb-3">
                <strong>Thông tin</strong> — khuyến mãi / tin chung.
                <strong>Thông báo</strong> — cảnh báo / cập nhật cần chú ý.
                Tin sẽ vào hộp thư tài xế và gửi push nếu đã bật thông báo.
            </p>

            <form method="POST" action="{{ route('admin.driverInbox.send') }}" class="console-form">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Loại tin</label>
                    <div class="d-flex flex-wrap gap-3">
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="category" value="notice" @checked(old('category', 'notice') === 'notice')>
                            <span class="form-check-label">Thông báo</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="category" value="info" @checked(old('category') === 'info')>
                            <span class="form-check-label">Thông tin</span>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="inbox-title">Tiêu đề</label>
                    <input type="text" name="title" id="inbox-title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" maxlength="160" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="inbox-body">Nội dung</label>
                    <textarea name="body" id="inbox-body" rows="4" class="form-control @error('body') is-invalid @enderror"
                              maxlength="2000" required>{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Người nhận</label>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="audience" value="all" id="audience-all"
                                   @checked(old('audience', 'all') === 'all')>
                            <span class="form-check-label">Tất cả tài xế đã duyệt</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="audience" value="selected" id="audience-selected"
                                   @checked(old('audience') === 'selected')>
                            <span class="form-check-label">Chọn tài xế</span>
                        </label>
                    </div>
                    <div id="driver-pick-wrap" class="{{ old('audience') === 'selected' ? '' : 'd-none' }}">
                        <select name="driver_ids[]" id="driver-ids" class="form-select @error('driver_ids') is-invalid @enderror" multiple size="10">
                            @foreach($drivers as $driver)
                                @php $uid = (int) $driver->user_id; @endphp
                                <option value="{{ $uid }}" @selected(collect(old('driver_ids', []))->contains($uid))>
                                    {{ $driver->user?->preferredDisplayName() ?: $driver->user?->name }}
                                    — {{ $driver->user?->phone }}
                                    @if($driver->driver_code) ({{ $driver->driver_code }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Giữ Ctrl/Cmd để chọn nhiều.</div>
                        @error('driver_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-warning fw-semibold"
                        data-confirm="Gửi tin này tới tài xế đã chọn?"
                        data-confirm-title="Gửi tin tài xế"
                        data-confirm-ok="Gửi">Gửi</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var all = document.getElementById('audience-all');
    var selected = document.getElementById('audience-selected');
    var wrap = document.getElementById('driver-pick-wrap');
    function sync() {
        if (!wrap) return;
        wrap.classList.toggle('d-none', !(selected && selected.checked));
    }
    if (all) all.addEventListener('change', sync);
    if (selected) selected.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
