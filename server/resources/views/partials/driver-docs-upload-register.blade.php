{{-- Upload giấy tờ — bước 1 đăng ký tài xế --}}

<div class="register-section" data-field-section="documents">

    <div class="register-section-title"><span class="section-icon">📷</span> Giấy tờ</div>

    <div class="row g-3">

        <div class="col-md-6 col-lg-4">

            <label class="form-label">Ảnh chân dung <span class="text-danger">*</span></label>

            <input type="file" name="photo_portrait" accept="image/jpeg,image/png,image/webp"

                   class="form-control form-control-sm @error('photo_portrait') is-invalid @enderror" required>

            @error('photo_portrait')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <img data-doc-preview class="img-fluid rounded border mt-2 d-none" alt="Xem trước chân dung" style="max-height:100px">

        </div>

        <div class="col-md-6 col-lg-4">

            <label class="form-label">CCCD mặt trước <span class="text-danger">*</span></label>

            <input type="file" name="photo_id_card" accept="image/jpeg,image/png,image/webp"

                   class="form-control form-control-sm @error('photo_id_card') is-invalid @enderror" required>

            @error('photo_id_card')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <img data-doc-preview class="img-fluid rounded border mt-2 d-none" alt="Xem trước CCCD" style="max-height:100px">

        </div>

        <div class="col-md-6 col-lg-4">

            <label class="form-label">CCCD mặt sau <span class="text-danger">*</span></label>

            <input type="file" name="photo_id_card_back" accept="image/jpeg,image/png,image/webp"

                   class="form-control form-control-sm @error('photo_id_card_back') is-invalid @enderror" required>

            @error('photo_id_card_back')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <img data-doc-preview class="img-fluid rounded border mt-2 d-none" alt="Xem trước CCCD sau" style="max-height:100px">

        </div>

        <div class="col-md-6 col-lg-4">

            <label class="form-label">Bằng lái mặt trước <span class="text-danger">*</span></label>

            <input type="file" name="photo_license_front" accept="image/jpeg,image/png,image/webp"

                   class="form-control form-control-sm @error('photo_license_front') is-invalid @enderror" required>

            @error('photo_license_front')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <img data-doc-preview class="img-fluid rounded border mt-2 d-none" alt="Xem trước bằng lái" style="max-height:100px">

        </div>

        <div class="col-md-6 col-lg-4">

            <label class="form-label">Bằng lái mặt sau</label>

            <input type="file" name="photo_license_back" accept="image/jpeg,image/png,image/webp"

                   class="form-control form-control-sm @error('photo_license_back') is-invalid @enderror">

            @error('photo_license_back')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <img data-doc-preview class="img-fluid rounded border mt-2 d-none" alt="Xem trước bằng lái sau" style="max-height:100px">

        </div>

        <div class="col-12">
            <label class="form-label">Ảnh xe <span class="text-danger">*</span></label>
            <input type="file" name="photo_vehicles[]" accept="image/jpeg,image/png,image/webp" multiple
                   data-vehicle-picker
                   class="form-control form-control-sm @error('photo_vehicles') is-invalid @enderror @error('photo_vehicles.*') is-invalid @enderror">
            @error('photo_vehicles')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @error('photo_vehicles.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="text-muted small mb-2 mt-1">Chọn nhiều ảnh, có thể bấm chọn thêm. Bấm × trên ảnh để bỏ ảnh chưa đúng.</p>
            <div data-vehicle-preview class="register-vehicle-preview"></div>
        </div>
    </div>

    @error('photos')<div class="alert alert-danger py-2 mt-2">{{ $message }}</div>@enderror

</div>

