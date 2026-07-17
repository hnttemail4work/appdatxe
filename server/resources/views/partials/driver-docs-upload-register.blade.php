{{-- Upload giấy tờ — bước 1 đăng ký tài xế --}}
@php
    $docFields = [
        ['name' => 'photo_portrait', 'label' => 'Chân dung', 'required' => true],
        ['name' => 'photo_id_card', 'label' => 'CCCD trước', 'required' => true],
        ['name' => 'photo_id_card_back', 'label' => 'CCCD sau', 'required' => true],
        ['name' => 'photo_license_front', 'label' => 'Bằng lái trước', 'required' => true],
        ['name' => 'photo_license_back', 'label' => 'Bằng lái sau', 'required' => false],
    ];
@endphp
<div class="register-section register-section--docs" data-field-section="documents">
    <div class="register-doc-grid">
        @foreach($docFields as $doc)
        <div class="register-doc-item">
            <div class="register-file-field register-file-tile @error($doc['name']) is-invalid @enderror">
                <input type="file"
                       name="{{ $doc['name'] }}"
                       id="reg-{{ $doc['name'] }}"
                       accept="image/jpeg,image/png,image/webp"
                       class="register-file-input @error($doc['name']) is-invalid @enderror"
                       @if($doc['required']) required @endif>
                <button type="button" class="register-file-tile-btn" data-file-trigger aria-label="Chọn {{ $doc['label'] }}">
                    <img data-doc-preview class="register-file-tile-preview d-none" alt="">
                    <span class="register-file-tile-plus" aria-hidden="true">+</span>
                    <span class="register-file-tile-label">{{ $doc['label'] }}@if($doc['required']) <span class="text-danger">*</span>@endif</span>
                    <span class="register-file-name register-file-tile-status" data-file-name>Chưa chọn</span>
                </button>
            </div>
            <div class="invalid-feedback" data-client-feedback="{{ $doc['name'] }}">@error($doc['name']){{ $message }}@enderror</div>
        </div>
        @endforeach
    </div>

    <div class="register-doc-vehicles">
        <div class="register-file-field @error('photo_vehicles') is-invalid @enderror @error('photo_vehicles.*') is-invalid @enderror">
            <input type="file"
                   name="photo_vehicles[]"
                   id="reg-photo-vehicles"
                   accept="image/jpeg,image/png,image/webp"
                   multiple
                   data-vehicle-picker
                   class="register-file-input @error('photo_vehicles') is-invalid @enderror @error('photo_vehicles.*') is-invalid @enderror">
            <button type="button" class="btn btn-outline-primary btn-sm register-file-btn" data-file-trigger>
                Chọn ảnh xe
            </button>
            <span class="register-file-name" data-file-name>Chưa chọn</span>
        </div>
        <div class="invalid-feedback" data-client-feedback="photo_vehicles[]">
            @error('photo_vehicles'){{ $message }}@enderror
            @error('photo_vehicles.*'){{ $message }}@enderror
        </div>
        <div data-vehicle-preview class="register-vehicle-preview"></div>
    </div>
    @error('photos')<div class="alert alert-danger py-2 mt-2 mb-0">{{ $message }}</div>@enderror
</div>
