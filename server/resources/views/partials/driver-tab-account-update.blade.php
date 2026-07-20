@php
    use App\Support\BankOptions;
    use App\Support\DriverVehicleOptions;

    $profile = $profile ?? auth()->user()?->driverProfile;
    $user = $user ?? auth()->user();
    $pendingDocs = $pendingChangeRequest ?? null;
    $pendingPayload = $pendingDocs?->payload ?? [];
    $vehicleType = old('vehicle_type', $pendingPayload['vehicle_type'] ?? $profile?->vehicle_type);
    $bankOptions = BankOptions::names();
    $currentBank = old('bank_name', $pendingPayload['bank_name'] ?? $profile?->bank_name ?? '');
    $vehicleTypes = DriverVehicleOptions::labels();
    $docFields = [
        'photo_portrait' => 'Chân dung',
        'photo_id_card' => 'CCCD trước',
        'photo_id_card_back' => 'CCCD sau',
        'photo_license_front' => 'GPLX trước',
        'photo_license_back' => 'GPLX sau',
    ];

    $updateTab = request('update_tab');
    if (! in_array($updateTab, ['profile', 'docs'], true)) {
        $updateTab = ($errors->any() || old('_token')) ? 'docs' : 'profile';
    }
@endphp
<section class="driver-account-panel" aria-label="Cập nhật thông tin" data-driver-update-panel>
    <h2 class="driver-panel-title mb-3" data-i18n="account_update">Cập nhật thông tin</h2>

    <div class="driver-update-tabs mb-3" role="tablist" aria-label="Mục cập nhật">
        <button type="button"
                class="driver-update-tabs__btn {{ $updateTab === 'profile' ? 'is-active' : '' }}"
                data-driver-update-tab="profile"
                role="tab"
                aria-selected="{{ $updateTab === 'profile' ? 'true' : 'false' }}">
            Hồ sơ
        </button>
        <button type="button"
                class="driver-update-tabs__btn {{ $updateTab === 'docs' ? 'is-active' : '' }}"
                data-driver-update-tab="docs"
                role="tab"
                aria-selected="{{ $updateTab === 'docs' ? 'true' : 'false' }}">
            Giấy tờ
            @if($pendingDocs)
                <span class="driver-update-tabs__badge" aria-label="Đang chờ duyệt">!</span>
            @endif
        </button>
    </div>

    <div class="driver-update-pane {{ $updateTab === 'profile' ? 'is-active' : '' }}"
         data-driver-update-pane="profile"
         @if($updateTab !== 'profile') hidden @endif>
        @include('partials.driver-tab-account-profile', [
            'user' => $user,
            'profile' => $profile,
            'embedded' => true,
        ])
    </div>

    <div class="driver-update-pane {{ $updateTab === 'docs' ? 'is-active' : '' }}"
         data-driver-update-pane="docs"
         @if($updateTab !== 'docs') hidden @endif>
        @if(! $profile)
            @include('partials.driver-empty-state', ['title' => 'Chưa có hồ sơ tài xế'])
        @else
        <form method="POST"
                  action="{{ route('driver.settings.documents') }}"
                  enctype="multipart/form-data"
                  class="driver-settings-docs-form fields-auto-size">
                @csrf
                <input type="hidden" name="update_tab" value="docs">

                <div class="driver-settings-card mb-3">
                    <h3 class="driver-settings-card__title">Xe</h3>
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label" for="driver-update-plate">Biển số</label>
                            <input type="text"
                                   name="vehicle_license_plate"
                                   id="driver-update-plate"
                                   class="form-control form-control-sm field-auto-size"
                                   value="{{ old('vehicle_license_plate', $pendingPayload['vehicle_license_plate'] ?? $profile->vehicle_license_plate) }}">
                        </div>
                        <div class="col-auto" style="min-width:0;max-width:100%">
                            <label class="form-label" for="driver-update-vehicle-type">Loại xe</label>
                            <select name="vehicle_type"
                                    id="driver-update-vehicle-type"
                                    class="form-select form-select-sm vehicle-type-select field-auto-size">
                                <option value="">—</option>
                                @foreach($vehicleTypes as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}" @selected($vehicleType === $typeKey)>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $pendingVehiclePaths = $pendingDocs?->photos['photo_vehicles'] ?? null;
                            $hasPendingVehicles = is_array($pendingVehiclePaths) && $pendingVehiclePaths !== [];
                            $liveVehicleUrls = $profile->vehiclePhotoUrls();
                            $pendingVehicleUrls = $hasPendingVehicles ? $pendingDocs->vehiclePhotoUrls() : [];
                            $vehicleCount = count($profile->photo_vehicles ?? []);
                            $vehicleDefault = $vehicleCount > 0
                                ? ($vehicleCount . ' ảnh hiện tại')
                                : 'Chưa có ảnh';
                        @endphp
                        <div class="col-12">
                            <label class="form-label" for="driver-update-photo-vehicles">Ảnh xe (có thể chọn nhiều)</label>
                            <p class="small text-muted mb-1">Bấm «Thêm ảnh xe» nhiều lần để cộng dồn — tối đa 6 ảnh. Ảnh mới sẽ thêm vào ảnh đang có sau khi duyệt.</p>
                            @if($liveVehicleUrls !== [])
                                <div class="driver-doc-thumb-row mb-1">
                                    @foreach($liveVehicleUrls as $url)
                                        <a href="{{ $url }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="driver-doc-thumb">
                                            <img src="{{ $url }}" alt="Ảnh xe" loading="lazy">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            @if($pendingVehicleUrls !== [])
                                <div class="driver-doc-thumb-row mb-1">
                                    @foreach($pendingVehicleUrls as $url)
                                        <a href="{{ $url }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="driver-doc-thumb is-pending">
                                            <img src="{{ $url }}" alt="Ảnh xe chờ duyệt" loading="lazy">
                                            <span>Chờ duyệt</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            <div class="driver-doc-thumb-row mb-1 d-none" data-vehicle-new-preview aria-label="Ảnh xe vừa chọn"></div>
                            <div class="driver-file-field" data-driver-file-field data-vehicle-multi-field
                                 data-existing-count="{{ $vehicleCount }}"
                                 data-max-vehicles="6">
                                <input type="file"
                                       name="photo_vehicles[]"
                                       id="driver-update-photo-vehicles"
                                       accept="image/*"
                                       class="driver-file-field__input"
                                       data-file-default="{{ $vehicleDefault }}"
                                       multiple>
                                <button type="button" class="btn btn-sm btn-outline-light driver-file-field__btn" data-file-trigger>
                                    Thêm ảnh xe
                                </button>
                                <span class="driver-file-field__name" data-file-name>{{ $vehicleDefault }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="driver-settings-card mb-3">
                    <h3 class="driver-settings-card__title">Ngân hàng</h3>
                    <div class="row g-2 align-items-end">
                        <div class="col-auto" style="min-width:0;max-width:100%">
                            <label class="form-label" for="driver-update-bank-name">Ngân hàng</label>
                            <select name="bank_name" id="driver-update-bank-name" class="form-select form-select-sm field-auto-size">
                                <option value="">—</option>
                                @foreach($bankOptions as $bank)
                                    <option value="{{ $bank }}" @selected($currentBank === $bank)>{{ $bank }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label" for="driver-update-bank-account">Số tài khoản</label>
                            <input type="text"
                                   name="bank_account"
                                   id="driver-update-bank-account"
                                   class="form-control form-control-sm field-auto-size"
                                   value="{{ old('bank_account', $pendingPayload['bank_account'] ?? $profile->bank_account) }}">
                        </div>
                    </div>
                </div>

                <div class="driver-settings-card mb-3">
                    <h3 class="driver-settings-card__title">Ảnh giấy tờ</h3>
                    <p class="small text-muted mb-2">Bấm «Thay ảnh» chọn ảnh mới, rồi «Cập nhật» để gửi duyệt.</p>
                    @php
                        $docSlots = [];
                        foreach ($docFields as $field => $label) {
                            $pendingPath = $pendingDocs?->photos[$field] ?? null;
                            $docSlots[] = [
                                'field' => $field,
                                'label' => $label,
                                'url' => $pendingDocs?->photoUrl($field) ?: $profile->photoUrl($field),
                                'ratio' => $field === 'photo_portrait' ? 'portrait' : 'landscape',
                                'badge' => (is_string($pendingPath) && $pendingPath !== '') ? 'Chờ duyệt' : null,
                            ];
                        }
                    @endphp
                    @include('partials.photo-upload-slots', [
                        'columnsClass' => 'row g-2 row-cols-2',
                        'slots' => $docSlots,
                    ])
                </div>

                <button type="submit" class="btn btn-warning w-100 fw-semibold">Cập nhật</button>
            </form>
        @endif
    </div>
</section>
