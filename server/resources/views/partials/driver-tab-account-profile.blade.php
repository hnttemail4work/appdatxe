@php
    use App\Support\DriverVehicleOptions;

    $user = $user ?? auth()->user();
    $profile = $profile ?? ($user?->driverProfile);
    $vehicleTypes = DriverVehicleOptions::labels();
    $embedded = (bool) ($embedded ?? false);
@endphp
@if($embedded)
    <div class="driver-settings-card" aria-label="Hồ sơ">
@else
<section class="driver-account-panel" aria-label="Hồ sơ">
    <h2 class="driver-panel-title mb-3" data-i18n="account_profile">Hồ sơ</h2>
    <div class="driver-settings-card">
@endif
        <div class="driver-account-profile-rows">
            <div class="driver-account-profile-row">
                <span class="driver-account-profile-row__label" data-i18n="account_name">Họ tên</span>
                <strong>{{ $user?->preferredDisplayName() ?: '—' }}</strong>
            </div>
            <div class="driver-account-profile-row">
                <span class="driver-account-profile-row__label" data-i18n="account_phone">Số điện thoại</span>
                <strong>{{ $user?->phone ?: '—' }}</strong>
            </div>
            @if($profile?->driver_code)
                <div class="driver-account-profile-row">
                    <span class="driver-account-profile-row__label" data-i18n="account_code">Mã tài xế</span>
                    <strong>{{ $profile->driver_code }}</strong>
                </div>
            @endif
            @if($profile?->vehicle_license_plate)
                <div class="driver-account-profile-row">
                    <span class="driver-account-profile-row__label">Biển số</span>
                    <strong>{{ $profile->vehicle_license_plate }}</strong>
                </div>
            @endif
            @if($profile?->vehicle_type)
                <div class="driver-account-profile-row">
                    <span class="driver-account-profile-row__label">Loại xe</span>
                    <strong>{{ $vehicleTypes[$profile->vehicle_type] ?? $profile->vehicle_type }}</strong>
                </div>
            @endif
            @if($profile?->vehicle_seats)
                <div class="driver-account-profile-row">
                    <span class="driver-account-profile-row__label">Số ghế</span>
                    <strong>{{ $profile->vehicle_seats }}</strong>
                </div>
            @endif
            @if($profile?->bank_name || $profile?->bank_account)
                <div class="driver-account-profile-row">
                    <span class="driver-account-profile-row__label">Ngân hàng</span>
                    <strong>{{ trim(($profile->bank_name ?? '') . ' ' . ($profile->bank_account ?? '')) ?: '—' }}</strong>
                </div>
            @endif
        </div>
@if($embedded)
    </div>
@else
    </div>
</section>
@endif
