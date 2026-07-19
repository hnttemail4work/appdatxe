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

<div class="driver-photo-manager">
@if($viewOnly)
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
                            <span class="photo-vehicle-catalog-badge">Mặc định</span>
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
    @php
        $viewSlots = [];
        foreach ($singleSlots as $field => $meta) {
            $viewSlots[] = [
                'field' => $field,
                'label' => $meta['label'],
                'url' => $driver->photoUrl($field),
                'ratio' => $meta['ratio'],
            ];
        }
    @endphp
    @include('partials.photo-upload-slots', [
        'viewOnly' => true,
        'wrapManager' => false,
        'columnsClass' => 'row g-2 row-cols-2 row-cols-md-3 row-cols-xl-5',
        'slots' => $viewSlots,
    ])
@else
    <section class="photo-vehicles-section photo-vehicles-block mb-4">
        <h6 class="mb-2">
            Ảnh xe
            @if($vehicleCount > 0)
                <span class="text-muted fw-normal">({{ $vehicleCount }})</span>
            @endif
        </h6>

        @if($vehicleCount > 0)
            <div class="photo-vehicle-grid mb-3">
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
                            <span>Mặc định</span>
                        </label>
                        <span class="photo-vehicle-num">#{{ $idx + 1 }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="photo-vehicles-actions">
            <label class="photo-vehicle-add-btn mb-0">
                <span data-file-label>Thêm ảnh xe</span>
                <input type="file"
                       name="photo_vehicles[]"
                       form="driver-photo-main-form"
                       accept="image/jpeg,image/png,image/webp"
                       multiple
                       class="photo-file-input @error('photo_vehicles') is-invalid @enderror @error('photo_vehicles.*') is-invalid @enderror"
                       data-photo-input="photo_vehicles"
                       data-multiple>
            </label>
            @if($vehicleCount > 0)
                <button type="submit"
                        form="driver-photo-catalog-form"
                        class="photo-vehicle-default-btn">
                    Hiển thị mặc định
                </button>
            @endif
        </div>
        <div class="photo-vehicle-new-grid d-none mt-2" data-vehicle-new-grid></div>
        @error('photo_vehicles')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
        @error('photo_vehicles.*')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
    </section>

    @if($vehicleCount > 0)
    <form method="POST" action="{{ $action }}" id="driver-photo-catalog-form" class="d-none">
        @csrf
    </form>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="driver-photo-main-form">
        @csrf

        <h6 class="text-muted mb-3">Giấy tờ</h6>

        @php
            $editSlots = [];
            foreach ($singleSlots as $field => $meta) {
                $editSlots[] = [
                    'field' => $field,
                    'label' => $meta['label'],
                    'url' => $driver->photoUrl($field),
                    'ratio' => $meta['ratio'],
                    'locked' => $lockIdentityPhotos && ($meta['identity'] ?? false),
                ];
            }
        @endphp
        @include('partials.photo-upload-slots', [
            'wrapManager' => false,
            'columnsClass' => 'row g-2 row-cols-2 row-cols-md-3 row-cols-xl-5',
            'slots' => $editSlots,
        ])

        @error('photos')<div class="alert alert-danger py-2 mt-3 mb-0">{{ $message }}</div>@enderror

        <button type="submit" class="btn btn-primary mt-4">{{ $submitLabel ?? 'Lưu thay đổi ảnh' }}</button>
    </form>
@endif
</div>

@once
    @push('scripts')
    <script src="{{ asset('js/photo-upload-slots.js') }}?v={{ filemtime(public_path('js/photo-upload-slots.js')) }}"></script>
    @endpush
@endonce
