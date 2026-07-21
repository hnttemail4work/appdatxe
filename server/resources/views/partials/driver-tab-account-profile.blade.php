@php
    use App\Support\DriverVehicleOptions;

    $user = $user ?? auth()->user();
    $profile = $profile ?? ($user?->driverProfile);
    $vehicleTypes = DriverVehicleOptions::labels();
    $embedded = (bool) ($embedded ?? false);
    $displayName = $user?->preferredDisplayName() ?: '—';
    $portraitUrl = $profile?->photoUrl('photo_portrait');
    $initial = mb_strtoupper(mb_substr(trim($displayName) !== '' && $displayName !== '—' ? $displayName : 'T', 0, 1));
@endphp
@if($embedded)
    <div class="driver-profile" aria-label="Thông tin">
@else
<section class="driver-account-panel" aria-label="Thông tin">
    <h2 class="driver-panel-title mb-3" data-i18n="account_profile">Thông tin</h2>
    <div class="driver-profile">
@endif
        <div class="driver-profile__hero">
            <div class="driver-profile__avatar" aria-hidden="true">
                @if($portraitUrl)
                    <img src="{{ $portraitUrl }}" alt="" loading="lazy">
                @else
                    <span>{{ $initial }}</span>
                @endif
            </div>
            <div class="driver-profile__identity">
                <p class="driver-profile__name">{{ $displayName }}</p>
                @if($profile?->driver_code)
                    <p class="driver-profile__code">
                        <span>Mã tài xế</span>
                        <strong>{{ $profile->driver_code }}</strong>
                    </p>
                @endif
            </div>
        </div>

        <div class="driver-profile__section">
            <h3 class="driver-profile__section-title">Liên hệ</h3>
            <dl class="driver-profile__list">
                <div class="driver-profile__item">
                    <dt>Số điện thoại</dt>
                    <dd>{{ $user?->phone ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="driver-profile__section">
            <h3 class="driver-profile__section-title">Xe</h3>
            <dl class="driver-profile__list">
                <div class="driver-profile__item">
                    <dt>Biển số</dt>
                    <dd>{{ $profile?->vehicle_license_plate ?: '—' }}</dd>
                </div>
                <div class="driver-profile__item">
                    <dt>Loại xe</dt>
                    <dd>{{ $profile?->vehicle_type ? ($vehicleTypes[$profile->vehicle_type] ?? $profile->vehicle_type) : '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="driver-profile__section">
            <h3 class="driver-profile__section-title">Ngân hàng</h3>
            <dl class="driver-profile__list">
                <div class="driver-profile__item">
                    <dt>Ngân hàng</dt>
                    <dd>{{ $profile?->bank_name ?: '—' }}</dd>
                </div>
                <div class="driver-profile__item">
                    <dt>Số tài khoản</dt>
                    <dd class="driver-profile__mono">{{ $profile?->bank_account ?: '—' }}</dd>
                </div>
            </dl>
        </div>
@if($embedded)
    </div>
@else
    </div>
</section>
@endif
