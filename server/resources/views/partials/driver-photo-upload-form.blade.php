{{-- Form upload ảnh tài xế — một lần lưu, chỉ cập nhật ảnh đã chọn --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      class="border rounded p-2 bg-light mb-3 driver-photo-form">
    @csrf
    <small class="fw-semibold text-muted d-block mb-2">{{ $title ?? 'Upload / cập nhật ảnh hồ sơ' }}</small>
    <p class="text-muted mb-2" style="font-size:.75rem;">
        Chọn ảnh cần thay đổi (bỏ trống ảnh không đổi). JPG, PNG, WebP.
    </p>
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">Chân dung</label>
            <input type="file" name="photo_portrait" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm @error('photo_portrait') is-invalid @enderror">
            @error('photo_portrait')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt trước</label>
            <input type="file" name="photo_id_card" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm @error('photo_id_card') is-invalid @enderror">
            @error('photo_id_card')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt sau</label>
            <input type="file" name="photo_id_card_back" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm @error('photo_id_card_back') is-invalid @enderror">
            @error('photo_id_card_back')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">Bằng lái mặt trước</label>
            <input type="file" name="photo_license_front" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm @error('photo_license_front') is-invalid @enderror">
            @error('photo_license_front')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">Bằng lái mặt sau</label>
            <input type="file" name="photo_license_back" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm @error('photo_license_back') is-invalid @enderror">
            @error('photo_license_back')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label mb-0" style="font-size:.75rem;">Thêm ảnh xe (có thể chọn nhiều)</label>
            <input type="file" name="photo_vehicles[]" accept="image/jpeg,image/png,image/webp" multiple
                   class="form-control form-control-sm @error('photo_vehicles.*') is-invalid @enderror @error('photo_vehicles.0') is-invalid @enderror">
            @error('photo_vehicles.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('photo_vehicles.0')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
    <button class="btn btn-sm btn-primary mt-2">{{ $submitLabel ?? 'Lưu ảnh' }}</button>
</form>
