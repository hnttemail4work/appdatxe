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
    $pendingMergeRequests = $pendingMergeRequests ?? collect();
    $pendingTripRequestGroups = $pendingTripRequestGroups ?? collect();

    $driverLocationAddress = $profile?->last_address;
    $driverLocationUpdated = ($profile?->last_location_at ?? null)?->format('H:i, d/m/Y');
    $driverLocationReady = $profile && $profile->hasFreshLocation();
@endphp

<div class="driver-page" data-driver-tabs data-driver-tabs-active="{{ $driverDefaultTab }}" data-driver-tabs-base="{{ route('driver.dashboard') }}" data-wait-progress-root>
    @if($profile)
    @php
        $walletBalanceLabel = $driverWallet
            ? number_format($driverWallet->balance, 0, ',', '.') . ' đ'
            : '—';
        $driverInitial = mb_strtoupper(mb_substr($user->name, 0, 1));
        $driverDockTabs = [
            ['key' => 'trips', 'label' => 'Chuyến đang chạy', 'short' => 'Chuyến', 'badge' => $tripActionCount, 'hot' => $tripActionCount > 0],
            ['key' => 'history', 'label' => 'Lịch sử chạy', 'short' => 'Lịch sử'],
            ['key' => 'deposit', 'label' => 'Ví', 'short' => 'Ví'],
        ];
    @endphp

    <header class="driver-hero mb-3">
        <div class="driver-hero-profile">
            <div class="driver-avatar" aria-hidden="true">{{ $driverInitial }}</div>
            <div class="driver-hero-copy">
                <p class="driver-hero-eyebrow">Tài xế</p>
                <div class="driver-hero-title-row">
                    <h1 class="driver-hero-name">{{ $user->name }}</h1>
                    @if($profile->driver_code)
                        <span class="driver-meta-code driver-hero-code">{{ $profile->driver_code }}</span>
                    @endif
                </div>
                <div class="driver-status-pill driver-status-pill--{{ $driverLocationReady ? 'online' : 'offline' }}">
                    <span class="driver-status-dot" aria-hidden="true"></span>
                    <span>{{ $driverLocationReady ? 'Sẵn sàng nhận chuyến' : 'Cập nhật vị trí để nhận chuyến' }}</span>
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

    @if($profile->isMissedTripLocked() || ($showTopUpBanner ?? false) || ($walletBlockReason ?? null))
    <div class="driver-notice-stack mb-3">
        @if($profile->isMissedTripLocked())
            <div class="driver-notice driver-notice-danger">
                <strong>Tài khoản tạm khóa</strong> — không nhận chuyến được. Liên hệ quản lý để mở khóa.
            </div>
        @endif
        @if($showTopUpBanner ?? false)
            <div class="driver-notice driver-notice-warning driver-notice--topup">
                <span class="driver-notice-topup-text">
                @if($walletBlockReason)
                    {{ $walletBlockReason }}
                @elseif(! $profile->isWalletActivated())
                    Cần nạp ví tối thiểu {{ \App\Support\DriverWalletConfig::minDepositFormatted() }} để kích hoạt tài khoản.
                @else
                    Chưa đủ điều kiện nhận chuyến.
                @endif
                </span>
                <a href="{{ route('driver.dashboard', ['tab' => 'deposit']) }}" class="driver-notice-topup-link" data-driver-tab="deposit">nạp ví ngay →</a>
            </div>
        @elseif($walletBlockReason)
            <div class="driver-notice driver-notice-warning">
                {{ $walletBlockReason }}
            </div>
        @endif
    </div>
    @endif

    <section class="driver-location-sheet mb-3" id="driver-location-bar" aria-label="Vị trí hiện tại">
        <div class="driver-location-sheet-head">
            <div class="driver-location-sheet-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
            </div>
            <div class="driver-location-sheet-body">
                <div class="driver-location-sheet-row">
                    <span class="driver-location-sheet-label">Vị trí hiện tại</span>
                    <span class="driver-location-status driver-location-status--{{ $driverLocationReady ? 'ok' : 'idle' }}"
                          id="driver-location-status">{{ $driverLocationReady ? 'Sẵn sàng' : 'Chưa có' }}</span>
                </div>
                <p class="driver-location-address {{ $driverLocationAddress ? '' : 'is-empty' }}" id="driver-location-address">
                    {{ $driverLocationAddress ?: 'Chọn vị trí để hệ thống gán chuyến gần bạn' }}
                </p>
                <p class="driver-location-meta" id="driver-location-meta">
                    @if($driverLocationUpdated)
                        Cập nhật {{ $driverLocationUpdated }}
                    @endif
                </p>
            </div>
        </div>
        <div class="driver-location-sheet-actions">
            <div class="driver-location-input-wrap">
                <input type="text" id="driver-location-detail" class="form-control driver-location-input"
                       value="{{ $driverLocationReady ? ($driverLocationAddress ?? '') : '' }}"
                       placeholder="Gõ địa chỉ — chọn gợi ý hoặc bản đồ" autocomplete="off">
                <button type="button" class="driver-location-map-btn address-map-trigger"
                        data-address-map-for="driver-location-detail"
                        data-address-map-lat="driver-location-lat"
                        data-address-map-lng="driver-location-lng"
                        data-address-map-default-province="TP.HCM"
                        data-address-map-label="Chọn vị trí trên bản đồ"
                        aria-label="Chọn vị trí trên bản đồ" title="Ghim trên bản đồ">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <span>Bản đồ</span>
                </button>
            </div>
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
            @if($pendingMergeRequests->isNotEmpty())
                <div class="driver-trips-list mb-3" id="driver-merge-requests-list">
                    @foreach($pendingMergeRequests as $mergeRequest)
                        @include('partials.driver-merge-request-card', ['mergeRequest' => $mergeRequest])
                    @endforeach
                </div>
            @endif
            @include('partials.driver-empty-state', [
                'icon' => 'route',
                'title' => ($pendingTripRequestGroups->isNotEmpty() || $pendingMergeRequests->isNotEmpty()) ? 'Chưa có chuyến khác' : 'Chưa có chuyến',
                'hint' => $pendingTripRequestGroups->isNotEmpty()
                    ? 'Xử lý cuốc chờ nhận phía trên trước.'
                    : ($pendingMergeRequests->isNotEmpty()
                    ? 'Xử lý yêu cầu gom chuyến phía trên trước.'
                    : 'Hệ thống sẽ tự đẩy chuyến khi có khách gần bạn. Giữ trạng thái Sẵn sàng và cập nhật vị trí.'),
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
            @if($pendingMergeRequests->isNotEmpty())
                <div class="driver-panel-toolbar">
                    <h2 class="driver-panel-title">Yêu cầu gom chuyến</h2>
                </div>
                <div class="driver-trips-list mb-3" id="driver-merge-requests-list">
                    @foreach($pendingMergeRequests as $mergeRequest)
                        @include('partials.driver-merge-request-card', ['mergeRequest' => $mergeRequest])
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
            <div class="driver-trips-list">
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

@include('partials.address-map-picker-modal')
@endsection

@push('scripts')
<script>
window.__driverLocationUrl = @json(route('driver.location.update'));
window.__geocodeReverseUrl = @json(route('geocode.reverse'));
window.__geocodeSearchUrl = @json(route('geocode.search'));
</script>
<script src="{{ asset('js/geocode-address-autocomplete.js') }}?v={{ filemtime(public_path('js/geocode-address-autocomplete.js')) }}"></script>
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
<script src="{{ asset('js/driver-location-save.js') }}?v={{ filemtime(public_path('js/driver-location-save.js')) }}"></script>
<script>
(function () {
    if (window.GeocodeAddressAutocomplete) {
        window.GeocodeAddressAutocomplete.attach({
            detailInputId: 'driver-location-detail',
            latInputId: 'driver-location-lat',
            lngInputId: 'driver-location-lng',
            defaultProvince: 'TP.HCM',
        });
    }
})();
</script>
<script src="{{ asset('js/wait-progress.js') }}?v={{ filemtime(public_path('js/wait-progress.js')) }}"></script>
<script src="{{ asset('js/driver-tabs.js') }}?v={{ filemtime(public_path('js/driver-tabs.js')) }}"></script>
<script src="{{ asset('js/driver-wallet-deposit.js') }}?v={{ filemtime(public_path('js/driver-wallet-deposit.js')) }}"></script>
<script>
(function () {
    var syncUrl = @json(route('driver.liveSync'));
    var tripsTab = document.getElementById('driver-section-trips');
    if (!tripsTab || tripsTab.hidden) {
        return;
    }

    var knownIds = Array.prototype.map.call(
        document.querySelectorAll('[data-schedule-id], [data-trip-request-id]'),
        function (el) {
            return el.hasAttribute('data-schedule-id')
                ? 's-' + el.getAttribute('data-schedule-id')
                : 'r-' + el.getAttribute('data-trip-request-id');
        }
    );

    function poll() {
        fetch(syncUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var remoteIds = [];
                if (Array.isArray(data.schedules)) {
                    data.schedules.forEach(function (s) { remoteIds.push('s-' + String(s.id)); });
                }
                if (Array.isArray(data.pending_trip_requests)) {
                    data.pending_trip_requests.forEach(function (r) { remoteIds.push('r-' + String(r.id)); });
                }
                var hasNew = remoteIds.some(function (id) { return knownIds.indexOf(id) === -1; });
                if (hasNew) {
                    window.location.reload();
                }
            }).catch(function () {});
    }

    setInterval(poll, 15000);
})();
</script>
@endpush
