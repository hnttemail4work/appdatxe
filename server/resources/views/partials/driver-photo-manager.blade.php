{{--
    Quản lý ảnh hồ sơ — xem / chỉnh sửa (cùng layout).
    $driver, $viewOnly (bool), $action, $submitLabel, $allowVehicleDelete (bool)
--}}
@php
    $viewOnly = $viewOnly ?? false;
    $allowVehicleDelete = $allowVehicleDelete ?? false;
    $lockIdentityPhotos = $lockIdentityPhotos ?? false;
    $vehicleUrls = $driver->vehiclePhotoUrls();
    $vehicleCount = count($vehicleUrls);
    $catalogPhotoIndex = $driver->catalogVehiclePhotoIndex();
    $singleSlots = [
        'photo_portrait'       => ['label' => 'Chân dung', 'ratio' => 'portrait', 'identity' => true],
        'photo_id_card'        => ['label' => 'CCCD mặt trước', 'ratio' => 'landscape', 'identity' => true],
        'photo_id_card_back'   => ['label' => 'CCCD mặt sau', 'ratio' => 'landscape', 'identity' => true],
        'photo_license_front'  => ['label' => 'Bằng lái mặt trước', 'ratio' => 'landscape', 'identity' => true],
        'photo_license_back'   => ['label' => 'Bằng lái mặt sau', 'ratio' => 'landscape', 'identity' => true],
    ];
@endphp

@if($viewOnly)
<div class="driver-photo-manager">
    <div class="photo-vehicles-block mb-4">
        <h6 class="mb-2">
            Ảnh xe
            @if($vehicleCount > 0)
                <span class="text-muted fw-normal">({{ $vehicleCount }})</span>
            @endif
        </h6>
        @if($vehicleCount > 0)
            <div class="photo-vehicle-grid">
                @foreach($vehicleUrls as $idx => $url)
                    <div class="photo-vehicle-item {{ $idx === $catalogPhotoIndex ? 'is-catalog-photo' : '' }}">
                        <a href="{{ $url }}" data-photo-zoom title="Bấm để phóng to">
                            <img src="{{ $url }}" alt="Xe {{ $idx + 1 }}">
                        </a>
                        @if($idx === $catalogPhotoIndex)
                            <span class="photo-vehicle-catalog-badge">Hiển thị đặt xe</span>
                        @endif
                        <span class="photo-vehicle-num">#{{ $idx + 1 }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-muted small mb-0">Chưa có ảnh xe.</p>
        @endif
    </div>

    <h6 class="text-muted mb-3">Giấy tờ</h6>
    <div class="row g-2 row-cols-2 row-cols-md-3 row-cols-xl-5">
        @foreach($singleSlots as $field => $meta)
            @php $photoUrl = $driver->photoUrl($field); @endphp
            <div class="col">
                <div class="photo-slot {{ $photoUrl ? 'has-photo' : 'is-empty' }}">
                    <div class="photo-slot-header">
                        <span class="photo-slot-title">{{ $meta['label'] }}</span>
                    </div>
                    <div class="photo-slot-preview photo-ratio-{{ $meta['ratio'] }}">
                        @if($photoUrl)
                            <a href="{{ $photoUrl }}" data-photo-zoom
                               class="photo-thumb photo-current-link" title="Bấm để phóng to">
                                <img src="{{ $photoUrl }}" alt="{{ $meta['label'] }}" class="photo-current-img">
                                <span class="photo-zoom-hint">Phóng to</span>
                            </a>
                        @else
                            <div class="photo-placeholder"><span>—</span></div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@else
@if($vehicleCount > 0)
<section class="photo-vehicles-section photo-vehicles-block mb-0">
    <h6 class="mb-2">
        Ảnh xe <span class="text-muted fw-normal">({{ $vehicleCount }})</span>
    </h6>
    <div class="photo-vehicle-grid mb-0">
        @foreach($vehicleUrls as $idx => $url)
            <div class="photo-vehicle-item {{ $idx === $catalogPhotoIndex ? 'is-catalog-photo' : '' }}">
                <a href="{{ $url }}" data-photo-zoom title="Bấm để phóng to">
                    <img src="{{ $url }}" alt="Xe {{ $idx + 1 }}">
                </a>
                @if($allowVehicleDelete)
                    <form method="POST" action="{{ $action }}" class="photo-vehicle-delete"
                          data-confirm="Xóa ảnh xe này?"
                          data-confirm-variant="danger"
                          data-confirm-ok="Xóa">
                        @csrf
                        <input type="hidden" name="delete_vehicle_idx" value="{{ $idx }}">
                        <button type="submit" class="btn btn-danger btn-sm" title="Xóa ảnh">×</button>
                    </form>
                @endif
                <label class="photo-vehicle-catalog-pick">
                    <input type="radio"
                           name="catalog_vehicle_photo_index"
                           value="{{ $idx }}"
                           form="driver-photo-catalog-form"
                           @checked($idx === $catalogPhotoIndex)>
                    <span>Hiển thị đặt xe</span>
                </label>
                <span class="photo-vehicle-num">#{{ $idx + 1 }}</span>
            </div>
        @endforeach
    </div>
</section>
@endif

@if($vehicleCount > 0)
<form method="POST" action="{{ $action }}" id="driver-photo-catalog-form" class="mb-3">
    @csrf
    <p class="text-muted small mb-2">Chọn ảnh hiển thị trên trang đặt xe, rồi bấm lưu.</p>
    <button type="submit" class="btn btn-outline-primary btn-sm">Lưu ảnh hiển thị đặt xe</button>
</form>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="driver-photo-manager">
    @csrf

    <section class="photo-vehicles-section photo-vehicles-block mb-4">
        @if($vehicleCount === 0)
            <h6 class="mb-2">Ảnh xe</h6>
        @endif

        <div class="photo-vehicles-upload {{ $vehicleCount > 0 ? 'pt-3 mt-3 border-top' : '' }}">
            <label class="photo-vehicle-add-btn mb-0">
                <span data-file-label>
                    @if($vehicleCount > 0)
                        Thêm ảnh xe
                    @else
                        Ảnh xe
                    @endif
                </span>
                <input type="file" name="photo_vehicles[]" accept="image/jpeg,image/png,image/webp" multiple
                       class="photo-file-input @error('photo_vehicles') is-invalid @enderror @error('photo_vehicles.*') is-invalid @enderror"
                       data-photo-input="photo_vehicles" data-multiple>
            </label>
            <div class="photo-vehicle-new-grid d-none mt-2" data-vehicle-new-grid></div>
                @error('photo_vehicles')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                @error('photo_vehicles.*')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
        </div>
    </section>

    <h6 class="text-muted mb-3">Giấy tờ</h6>

    <div class="row g-2 row-cols-2 row-cols-md-3 row-cols-xl-5">
        @foreach($singleSlots as $field => $meta)
            @php
                $photoUrl = $driver->photoUrl($field);
                $hasStoredPath = filled($driver->{$field});
                $isLocked = $lockIdentityPhotos && ($meta['identity'] ?? false);
            @endphp
            <div class="col">
                <div class="photo-slot {{ $photoUrl ? 'has-photo' : 'is-empty' }} {{ $isLocked ? 'is-locked' : '' }}" data-photo-slot="{{ $field }}">
                    <div class="photo-slot-header">
                        <span class="photo-slot-title">{{ $meta['label'] }}</span>
                    </div>
                    <div class="photo-slot-preview photo-ratio-{{ $meta['ratio'] }}">
                        @if($photoUrl)
                            <a href="{{ $photoUrl }}" data-photo-zoom
                               class="photo-thumb photo-current-link" data-current-wrap
                               title="Bấm để phóng to">
                                <img src="{{ $photoUrl }}" alt="{{ $meta['label'] }}" class="photo-current-img" data-current-img>
                                <span class="photo-zoom-hint">Phóng to</span>
                            </a>
                        @else
                            <div class="photo-placeholder" data-current-wrap><span>—</span></div>
                        @endif
                        <div class="photo-thumb photo-new-wrap d-none" data-new-wrap>
                            <img src="" alt="Ảnh mới" class="photo-new-img" data-new-img>
                            <span class="photo-new-label">Mới</span>
                        </div>
                    </div>
                    @unless($isLocked)
                        <label class="photo-file-label">
                            <span data-file-label>{{ $hasStoredPath ? 'Thay ảnh' : 'Chọn ảnh' }}</span>
                            <input type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp"
                                   class="photo-file-input @error($field) is-invalid @enderror"
                                   data-photo-input="{{ $field }}">
                        </label>
                        @error($field)<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                    @endunless
                </div>
            </div>
        @endforeach
    </div>

    @error('photos')<div class="alert alert-danger py-2 mt-3 mb-0">{{ $message }}</div>@enderror

    <button type="submit" class="btn btn-primary mt-4">{{ $submitLabel ?? 'Lưu thay đổi ảnh' }}</button>
</form>
@endif

@once
    @push('scripts')
    <script src="{{ asset('js/driver-photo-manager.js') }}?v={{ filemtime(public_path('js/driver-photo-manager.js')) }}"></script>
    @endpush
@endonce
