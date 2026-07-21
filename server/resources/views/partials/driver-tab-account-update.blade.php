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

    $updateTab = request('update_tab');
    if (! in_array($updateTab, ['profile', 'docs'], true)) {
        $updateTab = ($errors->any() || old('_token')) ? 'docs' : 'profile';
    }

    $pendingVehiclePaths = $pendingDocs?->photos['photo_vehicles'] ?? null;
    $hasPendingVehicles = is_array($pendingVehiclePaths) && $pendingVehiclePaths !== [];
    $liveVehicleUrls = $profile ? $profile->vehiclePhotoUrls() : [];
    $pendingVehicleUrls = ($profile && $hasPendingVehicles) ? $pendingDocs->vehiclePhotoUrls() : [];
    $vehicleCount = count($profile?->photo_vehicles ?? []);
    $vehicleDefault = $vehicleCount > 0
        ? ($vehicleCount . ' ảnh hiện tại')
        : 'Chưa có ảnh';

    $makeDocSlots = function (array $fields) use ($profile, $pendingDocs): array {
        $slots = [];
        foreach ($fields as $field => $label) {
            $pendingPath = $pendingDocs?->photos[$field] ?? null;
            $slots[] = [
                'field' => $field,
                'label' => $label,
                'url' => $pendingDocs?->photoUrl($field) ?: $profile?->photoUrl($field),
                'ratio' => $field === 'photo_portrait' ? 'portrait' : 'landscape',
                'badge' => (is_string($pendingPath) && $pendingPath !== '') ? 'Chờ duyệt' : null,
            ];
        }

        return $slots;
    };

    $portraitSlots = $makeDocSlots(['photo_portrait' => 'Chân dung']);
    $idCardSlots = $makeDocSlots([
        'photo_id_card' => 'CCCD trước',
        'photo_id_card_back' => 'CCCD sau',
    ]);
    $licenseSlots = $makeDocSlots([
        'photo_license_front' => 'GPLX trước',
        'photo_license_back' => 'GPLX sau',
    ]);
@endphp
<section class="driver-account-panel driver-update" aria-label="Hồ sơ tài xế" data-driver-update-panel>
    <h2 class="driver-panel-title mb-3" data-i18n="account_update">Hồ sơ tài xế</h2>

    <div class="driver-update-tabs mb-3" role="tablist" aria-label="Mục hồ sơ">
        <button type="button"
                class="driver-update-tabs__btn {{ $updateTab === 'profile' ? 'is-active' : '' }}"
                data-driver-update-tab="profile"
                role="tab"
                aria-selected="{{ $updateTab === 'profile' ? 'true' : 'false' }}">
            Thông tin
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
                  class="driver-docs-form">
                @csrf
                <input type="hidden" name="update_tab" value="docs">

                <details class="driver-docs-accordion">
                    <summary class="driver-docs-accordion__summary">
                        <span class="driver-docs-accordion__title">Chân dung</span>
                        <span class="driver-docs-accordion__chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="driver-docs-accordion__body">
                        <div class="driver-docs-photos">
                            @include('partials.photo-upload-slots', [
                                'columnsClass' => 'row g-2 row-cols-1 row-cols-sm-2',
                                'slots' => $portraitSlots,
                            ])
                        </div>
                    </div>
                </details>

                <details class="driver-docs-accordion">
                    <summary class="driver-docs-accordion__summary">
                        <span class="driver-docs-accordion__title">Thông tin xe</span>
                        <span class="driver-docs-accordion__chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="driver-docs-accordion__body">
                        <div class="driver-docs-grid">
                            <div class="driver-docs-field">
                                <label class="driver-docs-label" for="driver-update-plate">Biển số</label>
                                <input type="text"
                                       name="vehicle_license_plate"
                                       id="driver-update-plate"
                                       class="form-control form-control-sm"
                                       value="{{ old('vehicle_license_plate', $pendingPayload['vehicle_license_plate'] ?? $profile->vehicle_license_plate) }}">
                            </div>
                            <div class="driver-docs-field">
                                <label class="driver-docs-label" for="driver-update-vehicle-type">Loại xe</label>
                                <select name="vehicle_type"
                                        id="driver-update-vehicle-type"
                                        class="form-select form-select-sm vehicle-type-select">
                                    <option value="">—</option>
                                    @foreach($vehicleTypes as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}" @selected($vehicleType === $typeKey)>{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="driver-docs-field mt-3">
                            <div class="driver-docs-label-row">
                                <span class="driver-docs-label">Ảnh xe</span>
                                <span class="driver-docs-meta">Tối đa 6 ảnh</span>
                            </div>
                            <div class="driver-docs-thumbs" aria-label="Ảnh xe hiện tại">
                                @foreach($liveVehicleUrls as $url)
                                    <a href="{{ $url }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="driver-docs-thumb">
                                        <img src="{{ $url }}" alt="Ảnh xe" loading="lazy">
                                    </a>
                                @endforeach
                                @foreach($pendingVehicleUrls as $url)
                                    <a href="{{ $url }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="driver-docs-thumb is-pending">
                                        <img src="{{ $url }}" alt="Ảnh xe chờ duyệt" loading="lazy">
                                        <span>Chờ duyệt</span>
                                    </a>
                                @endforeach
                                @if($liveVehicleUrls === [] && $pendingVehicleUrls === [])
                                    <div class="driver-docs-thumb driver-docs-thumb--empty" aria-hidden="true">
                                        <span>Chưa có</span>
                                    </div>
                                @endif
                            </div>
                            <div class="driver-docs-thumbs d-none mt-2" data-vehicle-new-preview aria-label="Ảnh xe vừa chọn"></div>
                            <div class="driver-file-field mt-2" data-driver-file-field data-vehicle-multi-field
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
                </details>

                <details class="driver-docs-accordion">
                    <summary class="driver-docs-accordion__summary">
                        <span class="driver-docs-accordion__title">Ngân hàng</span>
                        <span class="driver-docs-accordion__chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="driver-docs-accordion__body">
                        <div class="driver-docs-grid">
                            <div class="driver-docs-field">
                                <label class="driver-docs-label" for="driver-update-bank-name">Ngân hàng</label>
                                <select name="bank_name"
                                        id="driver-update-bank-name"
                                        class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach($bankOptions as $bank)
                                        <option value="{{ $bank }}" @selected($currentBank === $bank)>{{ $bank }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="driver-docs-field">
                                <label class="driver-docs-label" for="driver-update-bank-account">Số tài khoản</label>
                                <input type="text"
                                       name="bank_account"
                                       id="driver-update-bank-account"
                                       class="form-control form-control-sm"
                                       inputmode="numeric"
                                       autocomplete="off"
                                       value="{{ old('bank_account', $pendingPayload['bank_account'] ?? $profile->bank_account) }}">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="driver-docs-accordion">
                    <summary class="driver-docs-accordion__summary">
                        <span class="driver-docs-accordion__title">CCCD</span>
                        <span class="driver-docs-accordion__chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="driver-docs-accordion__body">
                        <div class="driver-docs-photos">
                            @include('partials.photo-upload-slots', [
                                'columnsClass' => 'row g-2 row-cols-2',
                                'slots' => $idCardSlots,
                            ])
                        </div>
                    </div>
                </details>

                <details class="driver-docs-accordion">
                    <summary class="driver-docs-accordion__summary">
                        <span class="driver-docs-accordion__title">Giấy phép lái xe</span>
                        <span class="driver-docs-accordion__chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="driver-docs-accordion__body">
                        <div class="driver-docs-photos">
                            @include('partials.photo-upload-slots', [
                                'columnsClass' => 'row g-2 row-cols-2',
                                'slots' => $licenseSlots,
                            ])
                        </div>
                    </div>
                </details>

                <button type="submit" class="btn btn-warning w-100 fw-semibold driver-docs-submit">Cập nhật</button>
            </form>
        @endif
    </div>
</section>
