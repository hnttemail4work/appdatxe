@php
    /** @var \App\Models\User $user */
    $viewOnly = (bool) ($viewOnly ?? false);
    $frontUrl = $user->idCardPhotoUrl('photo_id_card');
    $backUrl = $user->idCardPhotoUrl('photo_id_card_back');
@endphp
<div class="mb-3">
    <p class="form-label mb-2">Ảnh CCCD hiện tại</p>
    <div class="d-flex flex-wrap gap-2">
        @if($frontUrl)
            <a href="{{ $frontUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Mặt trước</a>
        @else
            <span class="text-muted small">Chưa có mặt trước</span>
        @endif
        @if($backUrl)
            <a href="{{ $backUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Mặt sau</a>
        @else
            <span class="text-muted small">Chưa có mặt sau</span>
        @endif
    </div>
</div>

@if(! $viewOnly)
<form method="POST" action="{{ route('admin.users.photos', $user) }}" enctype="multipart/form-data" class="console-form">
    @csrf
    <p class="small text-muted mb-3">Tải ảnh mới sẽ ghi đè ảnh hiện tại trên hồ sơ (không qua hàng chờ duyệt).</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="admin-cust-front">CCCD trước</label>
            <input type="file" name="photo_id_card" id="admin-cust-front"
                   class="form-control @error('photo_id_card') is-invalid @enderror"
                   accept="image/jpeg,image/png,image/webp">
            @error('photo_id_card')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="admin-cust-back">CCCD sau</label>
            <input type="file" name="photo_id_card_back" id="admin-cust-back"
                   class="form-control @error('photo_id_card_back') is-invalid @enderror"
                   accept="image/jpeg,image/png,image/webp">
            @error('photo_id_card_back')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    @error('photos')<div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>@enderror
    <button type="submit" class="btn btn-primary mt-3">Lưu ảnh</button>
</form>
@endif
