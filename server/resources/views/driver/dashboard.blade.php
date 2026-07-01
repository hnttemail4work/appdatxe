@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver.css') }}?v={{ filemtime(public_path('css/driver.css')) }}">
<link rel="stylesheet" href="{{ asset('css/address-map-picker.css') }}?v={{ filemtime(public_path('css/address-map-picker.css')) }}">
@endpush

@section('content')
@php
    $tripCards = $tripCards ?? collect();
    $pendingPassengerCount = $pendingPassengerCount ?? $tripCards->sum(fn (array $card): int => (int) ($card['passenger_count'] ?? 0));
    $pendingCount = $pendingPassengerCount;
    $wallet = $driverWallet;
    $walletHistory = $walletHistory ?? collect();
    $tripSchedules = $tripSchedules ?? collect();
    $tripActionCount = $tripActionCount ?? 0;
    $revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];

    $driverDefaultTab = request('tab');
    if (! in_array($driverDefaultTab, ['requests', 'trips', 'history', 'deposit'], true)) {
        $driverDefaultTab = 'requests';
    }

    $tripHistory = $tripHistory ?? collect();

    $driverLocationAddress = $profile?->last_address;
    $driverLocationUpdated = ($profile?->last_location_at ?? null)?->format('H:i, d/m/Y');
    $driverLocationReady = $profile && $profile->hasFreshLocation();
@endphp

<div class="driver-page" data-driver-tabs data-driver-tabs-active="{{ $driverDefaultTab }}" data-driver-tabs-base="{{ route('driver.dashboard') }}">
    @if($profile)
    @php
        $walletBalanceLabel = $driverWallet
            ? number_format($driverWallet->balance, 0, ',', '.') . ' đ'
            : '—';
        $driverInitial = mb_strtoupper(mb_substr($user->name, 0, 1));
        $driverDockTabs = [
            ['key' => 'requests', 'label' => 'Tìm chuyến', 'short' => 'Tìm', 'badge' => $pendingCount, 'hot' => $pendingCount > 0],
            ['key' => 'trips', 'label' => 'Xem chuyến', 'short' => 'Chuyến', 'badge' => $tripActionCount, 'hot' => $tripActionCount > 0],
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
                    <span>{{ $driverLocationReady ? 'Sẵn sàng nhận cuốc' : 'Cập nhật vị trí để nhận cuốc' }}</span>
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
                    Chưa đủ điều kiện nhận cuốc.
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
                    {{ $driverLocationAddress ?: 'Chọn vị trí để nhận cuốc gần bạn' }}
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
                       value="{{ $driverLocationAddress ?? '' }}"
                       placeholder="Nhập địa chỉ hoặc ghim bản đồ" autocomplete="off">
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
        <input type="hidden" id="driver-location-lat" value="{{ $profile->last_lat ?? '' }}">
        <input type="hidden" id="driver-location-lng" value="{{ $profile->last_lng ?? '' }}">
    </section>
    @endif

    <div class="driver-main-panel">
    <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'requests' ? 'is-active' : '' }}"
             id="driver-section-requests" data-driver-tab="requests" @if($driverDefaultTab !== 'requests') hidden @endif>
        <div class="driver-panel-toolbar">
            <h2 class="driver-panel-title">Cuốc gần bạn</h2>
            <button type="button" class="driver-refresh-btn" id="driver-refresh-btn">
                <span aria-hidden="true">↻</span> Dò cuốc
            </button>
        </div>
        @if($tripCards->isEmpty())
            <div id="no-pending-msg">
                @include('partials.driver-empty-state', [
                    'icon' => 'search',
                    'title' => 'Chưa có cuốc gần bạn',
                    'hint' => 'Bật Sẵn sàng, cập nhật vị trí trên bản đồ rồi bấm Dò cuốc.',
                ])
            </div>
            <div class="d-none flex-column gap-3 driver-trip-stack" id="pending-requests-list"></div>
        @else
            <div class="d-flex flex-column gap-3 driver-trip-stack" id="pending-requests-list">
                @foreach($tripCards as $card)
                    @include('partials.driver-trip-card', [
                        'card' => $card,
                        'walletBlockReason' => $walletBlockReason ?? null,
                    ])
                @endforeach
            </div>
            @include('partials.pagination', ['paginator' => $tripCards])
        @endif
    </section>

    <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'trips' ? 'is-active' : '' }}"
             id="driver-section-trips" data-driver-tab="trips" @if($driverDefaultTab !== 'trips') hidden @endif>
        @if($tripSchedules->isEmpty())
            @include('partials.driver-empty-state', [
                'icon' => 'route',
                'title' => 'Chưa có chuyến sắp chạy',
                'hint' => 'Các chuyến đã nhận sẽ hiện tại đây.',
            ])
        @else
            <div class="driver-trips-list">
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
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
<script src="{{ asset('js/driver-location-save.js') }}?v={{ filemtime(public_path('js/driver-location-save.js')) }}"></script>
<script src="{{ asset('js/driver-tabs.js') }}?v={{ filemtime(public_path('js/driver-tabs.js')) }}"></script>
<script src="{{ asset('js/driver-transfer-form.js') }}?v={{ filemtime(public_path('js/driver-transfer-form.js')) }}"></script>
<script src="{{ asset('js/driver-wallet-deposit.js') }}?v={{ filemtime(public_path('js/driver-wallet-deposit.js')) }}"></script>
<script>
(function () {
    var syncUrl = @json(route('driver.liveSync'));
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function renderPassenger(passenger, isLast) {
        var modeBadge = passenger.booking_mode_key === 'whole_car' ? 'gold' : 'info';
        var splitClass = isLast ? '' : ' driver-passenger-item--split';
        var html = '<div class="driver-passenger-item' + splitClass + '">';
        html += '<div class="driver-passenger-head">';
        html += '<strong>' + escapeHtml(passenger.passenger_name || 'Hành khách') + '</strong>';
        if (passenger.booking_mode) {
            html += '<span class="status-pill status-pill--' + modeBadge + '">' + escapeHtml(passenger.booking_mode) + '</span>';
        }
        html += '</div>';
        if (passenger.passenger_profile) {
            html += '<div class="driver-info-line">' + escapeHtml(passenger.passenger_profile) + '</div>';
        }
        if (passenger.pickup || passenger.pickup_time) {
            html += '<div class="driver-info-line"><span class="driver-info-k">Đón</span> ' +
                escapeHtml(passenger.pickup_time || '—') + ' · ' + escapeHtml(passenger.pickup || '—') + '</div>';
        }
        if (passenger.dropoff) {
            html += '<div class="driver-info-line"><span class="driver-info-k">Trả</span> ' + escapeHtml(passenger.dropoff) + '</div>';
        }
        if (passenger.seats_label) {
            html += '<div class="driver-info-line">' + escapeHtml(passenger.seats_label) + '</div>';
        }
        if (passenger.notes) {
            html += '<div class="driver-info-line driver-info-line--note">' + escapeHtml(passenger.notes) + '</div>';
        }
        html += '</div>';
        return html;
    }

    function renderPending(req) {
        var passengers = Array.isArray(req.passengers) ? req.passengers : [];
        var details = '';
        if (passengers.length) {
            details += '<div class="driver-passenger-list">';
            passengers.forEach(function (passenger, index) {
                details += renderPassenger(passenger, index === passengers.length - 1);
            });
            details += '</div>';
        } else {
            details = '<p class="text-muted small mb-0">Chưa có chi tiết hành khách.</p>';
        }
        var metaChips = '';
        var metaLine = req.meta_label || req.departure_time || '';
        if (metaLine) {
            metaChips += '<span class="driver-meta-chip">' + escapeHtml(metaLine) + '</span>';
        }
        if (req.passenger_count > 1) {
            metaChips += '<span class="driver-meta-chip">' + escapeHtml(String(req.passenger_count)) + ' khách</span>';
        }
        if (req.distance_label) {
            metaChips += '<span class="driver-meta-chip driver-meta-chip--distance">📍 ' + escapeHtml(req.distance_label) + '</span>';
        }
        if (req.expires_in_label) {
            metaChips += '<span class="driver-meta-chip driver-meta-chip--warn">⏱ ' + escapeHtml(req.expires_in_label) + '</span>';
        }
        var acceptUrl = escapeHtml(req.accept_url || req.claim_url || '#');
        var isOpenTrip = !!req.is_open_trip;
        var pillLabel = isOpenTrip ? 'Cuốc gần bạn' : 'Cuốc mới';
        var routeParts = (req.route || '').split(' → ');
        var routeFrom = req.route_from || routeParts[0] || '';
        var routeTo = req.route_to || routeParts[1] || req.route || '';
        var tripCodeLine = req.trip_code
            ? '<div class="meta driver-schedule-trip-code">Mã <code class="driver-trip-code">' + escapeHtml(req.trip_code) + '</code></div>'
            : '';
        var fareBadge = req.trip_total
            ? '<div class="driver-fare-badge"><span class="driver-fare-label">Tổng</span><span class="driver-fare-amount">' +
                escapeHtml(req.trip_total) + ' đ</span></div>'
            : '';
        return '<div class="driver-request-card driver-action-card" data-request-id="' + escapeHtml(String(req.id)) + '">' +
            '<div class="driver-card-top"><div class="driver-card-top-main">' +
            '<div class="driver-route-head driver-route-head--compact">' +
            '<div class="driver-route-rail" aria-hidden="true"><span class="driver-route-dot"></span>' +
            '<span class="driver-route-line"></span><span class="driver-route-square"></span></div>' +
            '<div class="driver-route-stops"><div class="driver-route-from">' + escapeHtml(routeFrom) + '</div>' +
            '<div class="driver-route-to">' + escapeHtml(routeTo) + '</div></div></div>' +
            '<div class="driver-card-meta-row">' + metaChips + '</div>' + tripCodeLine + '</div>' +
            '<div class="driver-card-top-aside">' + fareBadge +
            '<span class="status-pill status-pill--accent">' + escapeHtml(pillLabel) + '</span></div></div>' +
            '<div class="driver-card-body">' + details + '</div>' +
            '<div class="driver-card-actions driver-card-actions--job">' +
            '<form method="POST" action="' + acceptUrl + '" class="driver-accept-form">' +
            '<input type="hidden" name="_token" value="' + escapeHtml(csrf) + '">' +
            '<button type="submit" class="btn btn-success driver-btn-accept">Nhận cuốc</button></form>' +
            (isOpenTrip || !req.reject_url ? '' :
            '<form method="POST" action="' + escapeHtml(req.reject_url) + '" class="driver-reject-form">' +
            '<input type="hidden" name="_token" value="' + escapeHtml(csrf) + '">' +
            '<button type="submit" class="btn btn-driver-reject-ghost">Từ chối</button></form>') +
            '</div></div>';
    }

    var refreshBtn = document.getElementById('driver-refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            poll();
        });
    }

    function poll() {
        fetch(syncUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var list = document.getElementById('pending-requests-list');
                var empty = document.getElementById('no-pending-msg');
                if (!data.pending_requests.length) {
                    if (list) {
                        list.innerHTML = '';
                        list.classList.add('d-none');
                        list.classList.remove('d-flex');
                    }
                    if (empty) empty.style.display = '';
                    return;
                }
                if (empty) empty.style.display = 'none';
                if (!list) return;
                list.classList.remove('d-none');
                list.classList.add('d-flex');
                list.innerHTML = data.pending_requests.map(renderPending).join('');
            }).catch(function () {});
    }
    poll();
    setInterval(poll, 10000);
})();
</script>
@endpush
