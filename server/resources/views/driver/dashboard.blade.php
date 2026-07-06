@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver.css') }}?v={{ filemtime(public_path('css/driver.css')) }}">
<link rel="stylesheet" href="{{ asset('css/address-map-picker.css') }}?v={{ filemtime(public_path('css/address-map-picker.css')) }}">
@endpush

@section('content')
@php
    $wallet = $driverWallet;
    $walletHistory = $walletHistory ?? collect();
    $tripSchedules = $tripSchedules ?? collect();
    $tripActionCount = $tripActionCount ?? 0;
    $revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];

    $driverDefaultTab = request('tab');
    if (! in_array($driverDefaultTab, ['trips', 'history', 'deposit'], true)) {
        $driverDefaultTab = 'trips';
    }

    $tripHistory = $tripHistory ?? collect();
    $pendingTripRequestGroups = $pendingTripRequestGroups ?? collect();

    $driverLocationAddress = $profile?->last_address;
    $driverLocationUpdated = ($profile?->last_location_at ?? null)?->format('H:i, d/m/Y');
    $availabilityStatus = $profile?->availability_status ?? 'off_duty';
    $driverTripActive = $driverTripActive ?? false;
    $driverTripUpcoming = $driverTripUpcoming ?? false;
    $driverOnTrip = $driverOnTrip ?? $driverTripActive;
    $driverPaused = $availabilityStatus === 'off_duty';
    $driverLocationReady = ! $driverPaused && $profile && $profile->hasFreshLocation();
    $driverNeedsLocationShare = $profile && ! $driverPaused && ! $driverLocationReady;
    $driverMapPickerEnabled = app()->environment('local');
    $locationSharePrompt = null;
    if ($driverNeedsLocationShare) {
        if ($driverTripUpcoming) {
            $locationSharePrompt = 'Chia sẻ vị trí GPS để khách biết bạn còn bao nhiêu km đến điểm đón.';
        } elseif ($driverTripActive) {
            $locationSharePrompt = 'Cập nhật vị trí GPS khi đang chạy chuyến.';
        } else {
            $locationSharePrompt = 'Chia sẻ vị trí GPS để nhận cuốc gần bạn.';
        }
    }

    $heroStatus = $profile
        ? $profile->heroStatusMeta($driverTripActive, $driverTripUpcoming)
        : ['key' => 'offline', 'label' => ''];
@endphp

<div class="driver-page" data-driver-tabs data-driver-tabs-active="{{ $driverDefaultTab }}" data-driver-tabs-base="{{ route('driver.dashboard') }}" data-wait-progress-root>
    @if($profile)
    @php
        $walletBalanceLabel = $driverWallet
            ? number_format($driverWallet->balance, 0, ',', '.') . ' đ'
            : '—';
        $driverInitial = mb_strtoupper(mb_substr($user->name, 0, 1));
        $driverPhotoUrl = $profile->photoUrl('photo_portrait');
        $driverDockTabs = [
            ['key' => 'trips', 'label' => 'Chuyến đang chạy', 'short' => 'Chuyến', 'badge' => $tripActionCount, 'hot' => $tripActionCount > 0],
            ['key' => 'history', 'label' => 'Lịch sử chạy', 'short' => 'Lịch sử'],
            ['key' => 'deposit', 'label' => 'Ví', 'short' => 'Ví'],
        ];
    @endphp

    <header class="driver-hero mb-3">
        <div class="driver-hero-profile">
            <div class="driver-avatar">
                @if($driverPhotoUrl)
                    <img src="{{ $driverPhotoUrl }}" alt="" class="driver-avatar-img" loading="lazy" decoding="async">
                @else
                    <span class="driver-avatar-fallback" aria-hidden="true">{{ $driverInitial }}</span>
                @endif
            </div>
            <div class="driver-hero-copy">
                <div class="driver-hero-topbar">
                    <div class="driver-hero-intro">
                        <p class="driver-hero-eyebrow">Xin chào tài xế</p>
                        <div class="driver-hero-title-row">
                            <h1 class="driver-hero-name">{{ $user->name }}</h1>
                            @if($profile->driver_code)
                                <span class="driver-hero-code-wrap">
                                    <span class="driver-hero-code-label">Mã tx:</span>
                                    <span class="driver-meta-code driver-hero-code">{{ $profile->driver_code }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="driver-activity-control">
                        <label class="driver-activity-toggle {{ $driverOnTrip ? 'is-locked' : '' }}"
                               for="driver-availability-input"
                               id="driver-activity-toggle-label">
                            <input type="checkbox"
                                   class="driver-activity-toggle-input"
                                   id="driver-availability-input"
                                   @checked(! $driverPaused)
                                   @disabled($driverOnTrip)
                                   @if($driverOnTrip) aria-describedby="driver-hero-status-label" @endif>
                            <span class="driver-activity-switch" aria-hidden="true">
                                <span class="driver-activity-switch-off">Tắt</span>
                                <span class="driver-activity-switch-knob"></span>
                                <span class="driver-activity-switch-on">Bật</span>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="driver-hero-meta-row">
                    <div class="driver-status-pill driver-status-pill--{{ $heroStatus['key'] }}" id="driver-hero-status-pill" role="status">
                        <span id="driver-hero-status-label">{{ $heroStatus['label'] }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="driver-earnings-strip">
            <div class="driver-earnings-item">
                <span class="driver-earnings-label">Hôm nay</span>
                <span class="driver-earnings-value">{{ number_format($revenueStats['day'] ?? 0, 0, ',', '.') }} đ</span>
            </div>
            <div class="driver-earnings-item">
                <span class="driver-earnings-label">Tuần này</span>
                <span class="driver-earnings-value">{{ number_format($revenueStats['week'] ?? 0, 0, ',', '.') }} đ</span>
            </div>
        <a href="{{ route('driver.dashboard', ['tab' => 'deposit']) }}" class="driver-earnings-item driver-earnings-item--wallet" data-driver-tab="deposit">
                <span class="driver-earnings-label">Số dư ví</span>
                <span class="driver-earnings-value">{{ $walletBalanceLabel }}</span>
            </a>
        </div>
    </header>

    @if($profile->isMissedTripLocked() || ($walletBlockReason ?? null))
    <div class="driver-notice-stack mb-3">
        @if($profile->isMissedTripLocked())
            <div class="driver-notice driver-notice-danger">
                <strong>Tài khoản tạm khóa</strong> — không nhận chuyến được. Liên hệ quản lý để mở khóa.
            </div>
        @elseif($walletBlockReason)
            <div class="driver-notice driver-notice-warning driver-notice--wallet-warning" role="alert">
                <div class="driver-notice-warning-icon" aria-hidden="true">!</div>
                <div class="driver-notice-warning-body">
                    <p class="driver-notice-warning-text mb-0">{{ $walletBlockReason }}</p>
                    @if($walletNotice ?? null)
                        <a href="{{ route('driver.dashboard', ['tab' => $walletNotice['cta_tab'] ?? 'deposit']) }}"
                           class="driver-notice-warning-cta"
                           data-driver-tab="{{ $walletNotice['cta_tab'] ?? 'deposit' }}">
                            {{ $walletNotice['cta_label'] ?? 'Nạp ví ngay' }} →
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
    @endif

    <section class="driver-location-sheet mb-3{{ $driverNeedsLocationShare ? ' driver-location-sheet--needs-share' : '' }}" id="driver-location-bar" aria-label="Vị trí hiện tại"
             data-driver-paused="{{ $driverPaused ? '1' : '0' }}"
             data-driver-on-trip="{{ $driverOnTrip ? '1' : '0' }}"
             data-driver-trip-active="{{ $driverTripActive ? '1' : '0' }}"
             data-driver-trip-upcoming="{{ $driverTripUpcoming ? '1' : '0' }}"
             data-needs-location="{{ $driverNeedsLocationShare ? '1' : '0' }}">
        @if($locationSharePrompt)
            <div class="driver-location-share-prompt" id="driver-location-share-prompt" role="status">
                {{ $locationSharePrompt }}
            </div>
        @endif
        <div class="driver-location-sheet-top {{ $driverPaused ? 'is-disabled' : '' }}">
            <div class="driver-location-province-wrap">
                <label class="driver-location-field-label" for="driver-location-province">Khu vực hoạt động</label>
                <select id="driver-location-province" class="form-select form-select-sm driver-province-select"
                        @disabled($driverPaused)>
                    @include('partials.province-options', ['selected' => $profile->last_province ?? 'TP.HCM'])
                </select>
            </div>
        </div>
        <div class="driver-location-sheet-head">
            <div class="driver-location-sheet-body">
                <span class="driver-location-sheet-label">Vị trí hiện tại</span>
                <p class="driver-location-address {{ $driverLocationAddress ? '' : 'is-empty' }}" id="driver-location-address">{{ $driverLocationAddress }}</p>
                <p class="driver-location-meta" id="driver-location-meta">
                    @if($driverLocationUpdated && ! $driverPaused && ! $driverNeedsLocationShare)
                        Cập nhật {{ $driverLocationUpdated }}
                    @endif
                </p>
            </div>
        </div>
        <div class="driver-location-sheet-actions {{ $driverPaused ? 'is-disabled' : '' }}">
            <button type="button" class="btn btn-warning btn-sm driver-location-share-btn" id="driver-location-share-btn"
                    @disabled($driverPaused)>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                </svg>
                Chia sẻ vị trí
            </button>
            @if($driverMapPickerEnabled)
                <details class="driver-location-test-tools">
                    <summary>Test bản đồ</summary>
                    <div class="driver-location-input-wrap mt-2">
                        <input type="text" id="driver-location-detail" class="form-control driver-location-input"
                               value="{{ $driverLocationReady ? ($driverLocationAddress ?? '') : '' }}"
                               placeholder="Chỉ dùng khi test — ghim trên bản đồ"
                               autocomplete="off"
                               @disabled($driverPaused)>
                        <button type="button" class="driver-location-map-btn address-map-trigger"
                                data-address-map-for="driver-location-detail"
                                data-address-map-lat="driver-location-lat"
                                data-address-map-lng="driver-location-lng"
                                data-address-map-province="driver-location-province"
                                data-address-map-mode="driver"
                                data-address-map-label="Chọn vị trí hoạt động"
                                aria-label="Chọn vị trí trên bản đồ (test)" title="Ghim trên bản đồ (test)"
                                @disabled($driverPaused)>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span>Bản đồ</span>
                        </button>
                    </div>
                </details>
            @endif
        </div>
        <input type="hidden" id="driver-location-lat" value="{{ $driverLocationReady ? ($profile->last_lat ?? '') : '' }}">
        <input type="hidden" id="driver-location-lng" value="{{ $driverLocationReady ? ($profile->last_lng ?? '') : '' }}">
    </section>
    @endif

    <div class="driver-main-panel">
    <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'trips' ? 'is-active' : '' }}"
             id="driver-section-trips" data-driver-tab="trips" @if($driverDefaultTab !== 'trips') hidden @endif>
        @if($tripSchedules->isEmpty())
            @if($pendingTripRequestGroups->isNotEmpty())
                <div class="driver-panel-toolbar">
                    <h2 class="driver-panel-title">Cuốc chờ nhận</h2>
                </div>
                <div class="driver-trips-list mb-3" id="driver-trip-requests-list">
                    @foreach($pendingTripRequestGroups as $group)
                        @include('partials.driver-trip-request-card', [
                            'tripRequest' => $group['primary'],
                            'schedule' => $group['schedule'],
                            'passengers' => $group['passengers'],
                        ])
                    @endforeach
                </div>
            @endif
            @include('partials.driver-empty-state', [
                'icon' => 'route',
                'title' => $pendingTripRequestGroups->isNotEmpty() ? 'Chưa có chuyến khác' : 'Chưa có chuyến',
                'hint' => $pendingTripRequestGroups->isNotEmpty()
                    ? 'Xử lý cuốc chờ nhận phía trên trước.'
                    : 'Hệ thống sẽ tự đẩy chuyến khi có khách gần bạn. Giữ trạng thái Sẵn sàng và cập nhật vị trí.',
            ])
        @else
            @if($pendingTripRequestGroups->isNotEmpty())
                <div class="driver-panel-toolbar">
                    <h2 class="driver-panel-title">Cuốc chờ nhận</h2>
                </div>
                <div class="driver-trips-list mb-3" id="driver-trip-requests-list">
                    @foreach($pendingTripRequestGroups as $group)
                        @include('partials.driver-trip-request-card', [
                            'tripRequest' => $group['primary'],
                            'schedule' => $group['schedule'],
                            'passengers' => $group['passengers'],
                        ])
                    @endforeach
                </div>
            @endif
            <div class="driver-trips-list" id="driver-trips-list">
                @foreach($tripSchedules as $schedule)
                    @include('partials.driver-schedule-card', [
                        'schedule' => $schedule,
                        'showActions' => true,
                    ])
                @endforeach
            </div>
            @include('partials.pagination', ['paginator' => $tripSchedules])
        @endif
    </section>

    <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'history' ? 'is-active' : '' }}"
             id="driver-section-history" data-driver-tab="history" @if($driverDefaultTab !== 'history') hidden @endif>
        @if($tripHistory->isEmpty())
            @include('partials.driver-empty-state', [
                'icon' => 'history',
                'title' => 'Chưa có lịch sử',
                'hint' => 'Các chuyến đã hoàn thành sẽ lưu tại đây.',
            ])
        @else
            <div class="driver-history-head">
                <p class="driver-history-intro">
                    <strong>{{ number_format($tripHistory->total()) }}</strong> chuyến trong lịch sử
                </p>
            </div>
            <div class="driver-trips-list driver-trips-list--history">
                @foreach($tripHistory as $schedule)
                    @include('partials.driver-schedule-history-card', [
                        'schedule' => $schedule,
                        'driverUserId' => $user->id,
                    ])
                @endforeach
            </div>
            @include('partials.pagination', ['paginator' => $tripHistory])
        @endif
    </section>

    <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'deposit' ? 'is-active' : '' }}"
             id="driver-section-deposit" data-driver-tab="deposit" @if($driverDefaultTab !== 'deposit') hidden @endif>
        @if($wallet)
            @include('partials.driver-tab-deposit', [
                'wallet' => $wallet,
                'revenueStats' => $revenueStats,
                'walletHistory' => $walletHistory ?? collect(),
            ])
        @else
            @include('partials.driver-empty-state', [
                'title' => 'Chưa có hồ sơ tài xế',
            ])
        @endif
    </section>
    </div>

    @if($profile ?? null)
        @include('partials.driver-app-dock', [
            'activeKey' => $driverDefaultTab,
            'tabs' => $driverDockTabs,
        ])
    @endif
</div>

@if($driverMapPickerEnabled ?? false)
@include('partials.address-map-picker-modal')
@endif
@endsection

@push('scripts')
<script>
window.__driverLocationUrl = @json(route('driver.location.update'));
window.__driverAvailabilityUrl = @json(route('driver.availability.update'));
window.__geocodeReverseUrl = @json(route('geocode.reverse'));
window.__geocodeSearchUrl = @json(route('geocode.search'));
window.__provinceCenters = @json(\App\Support\ProvinceCenters::centersForCatalog());
window.__driverDashboardUrl = @json(route('driver.dashboard', ['tab' => 'trips']));
window.__driverMapPickerEnabled = @json($driverMapPickerEnabled ?? false);
</script>
<script src="{{ asset('js/geocode-search-ui.js') }}?v={{ filemtime(public_path('js/geocode-search-ui.js')) }}"></script>
@if($driverMapPickerEnabled)
<script src="{{ asset('js/geocode-address-autocomplete.js') }}?v={{ filemtime(public_path('js/geocode-address-autocomplete.js')) }}"></script>
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
@endif
<script src="{{ asset('js/driver-location-save.js') }}?v={{ filemtime(public_path('js/driver-location-save.js')) }}"></script>
<script src="{{ asset('js/driver-location-gps.js') }}?v={{ filemtime(public_path('js/driver-location-gps.js')) }}"></script>
<script src="{{ asset('js/driver-availability-toggle.js') }}?v={{ filemtime(public_path('js/driver-availability-toggle.js')) }}"></script>
<script src="{{ asset('js/driver-trip-request-actions.js') }}?v={{ filemtime(public_path('js/driver-trip-request-actions.js')) }}"></script>
<script src="{{ asset('js/driver-workflow-actions.js') }}?v={{ filemtime(public_path('js/driver-workflow-actions.js')) }}"></script>
@if($driverMapPickerEnabled)
<script>
(function () {
    if (window.GeocodeAddressAutocomplete) {
        window.GeocodeAddressAutocomplete.attach({
            detailInputId: 'driver-location-detail',
            latInputId: 'driver-location-lat',
            lngInputId: 'driver-location-lng',
            provinceInputId: 'driver-location-province',
            defaultProvince: @json($profile->last_province ?? 'TP.HCM'),
        });
    }
})();
</script>
@endif
<script src="{{ asset('js/wait-progress.js') }}?v={{ filemtime(public_path('js/wait-progress.js')) }}"></script>
<script src="{{ asset('js/driver-tabs.js') }}?v={{ filemtime(public_path('js/driver-tabs.js')) }}"></script>
<script src="{{ asset('js/driver-wallet-deposit.js') }}?v={{ filemtime(public_path('js/driver-wallet-deposit.js')) }}"></script>
<script src="{{ asset('js/driver-late-pickup.js') }}?v={{ filemtime(public_path('js/driver-late-pickup.js')) }}"></script>
@endpush
