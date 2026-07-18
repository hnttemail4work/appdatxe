@php
    use App\Support\BankOptions;
    use App\Support\DriverVehicleOptions;

    $profile = $profile ?? auth()->user()?->driverProfile;
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

    $fileLabel = static function (?string $path): string {
        if (! is_string($path) || $path === '') {
            return 'Chưa có ảnh';
        }

        return basename($path);
    };
@endphp
<section class="driver-account-panel" aria-label="Cập nhật thông tin">
    <h2 class="driver-panel-title mb-3" data-i18n="account_update">Cập nhật thông tin</h2>

    @if(! $profile)
        @include('partials.driver-empty-state', ['title' => 'Chưa có hồ sơ tài xế'])
    @else
        @if($pendingDocs)
            <div class="driver-notice driver-notice-warning mb-3">
                Đã có yêu cầu chờ duyệt — gửi lại sẽ thay thế yêu cầu cũ.
            </div>
        @endif

        <form method="POST"
              action="{{ route('driver.settings.documents') }}"
              enctype="multipart/form-data"
              class="driver-settings-docs-form fields-auto-size">
            @csrf

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
                        $vehicleUrls = $hasPendingVehicles
                            ? $pendingDocs->vehiclePhotoUrls()
                            : $profile->vehiclePhotoUrls();
                        $vehicleCount = $hasPendingVehicles
                            ? count($pendingVehiclePaths)
                            : count($profile->photo_vehicles ?? []);
                        $vehicleDefault = $vehicleCount > 0
                            ? ($vehicleCount . ' ảnh hiện tại')
                            : 'Chưa có ảnh';
                    @endphp
                    <div class="col-12">
                        <label class="form-label" for="driver-update-photo-vehicles">Ảnh xe (có thể chọn nhiều)</label>
                        @if($vehicleUrls !== [])
                            <div class="driver-doc-thumb-row mb-1">
                                @foreach($vehicleUrls as $url)
                                    <a href="{{ $url }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="driver-doc-thumb {{ $hasPendingVehicles ? 'is-pending' : '' }}">
                                        <img src="{{ $url }}" alt="Ảnh xe" loading="lazy">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <div class="driver-file-field" data-driver-file-field>
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
                <div class="row g-2">
                    @foreach($docFields as $field => $label)
                        @php
                            $pendingPath = $pendingDocs?->photos[$field] ?? null;
                            $currentPath = is_string($pendingPath) && $pendingPath !== ''
                                ? $pendingPath
                                : ($profile->{$field} ?? null);
                            $currentUrl = $pendingDocs?->photoUrl($field) ?: $profile->photoUrl($field);
                            $currentName = $fileLabel($currentPath);
                        @endphp
                        <div class="col-6">
                            <label class="form-label" for="driver-update-{{ $field }}">{{ $label }}</label>
                            @if($currentUrl)
                                <a href="{{ $currentUrl }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="driver-doc-thumb {{ $pendingPath ? 'is-pending' : '' }}">
                                    <img src="{{ $currentUrl }}" alt="{{ $label }}" loading="lazy">
                                    @if($pendingPath)
                                        <span>Chờ duyệt</span>
                                    @endif
                                </a>
                            @else
                                <div class="driver-doc-thumb driver-doc-thumb--empty">Chưa có ảnh</div>
                            @endif
                            <div class="driver-file-field mt-1" data-driver-file-field>
                                <input type="file"
                                       name="{{ $field }}"
                                       id="driver-update-{{ $field }}"
                                       accept="image/*"
                                       class="driver-file-field__input"
                                       data-file-default="{{ $currentName }}">
                                <button type="button" class="btn btn-sm btn-outline-light driver-file-field__btn" data-file-trigger>
                                    Chọn tệp
                                </button>
                                <span class="driver-file-field__name" data-file-name>{{ $currentName }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="btn btn-warning w-100 fw-semibold">Cập nhật</button>
        </form>
    @endif
</section>
