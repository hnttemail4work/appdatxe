@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver.css') }}?v={{ filemtime(public_path('css/driver.css')) }}">
<link rel="stylesheet" href="{{ asset('css/address-map-picker.css') }}?v={{ filemtime(public_path('css/address-map-picker.css')) }}">
<link rel="stylesheet" href="{{ asset('css/trip-chat.css') }}?v={{ filemtime(public_path('css/trip-chat.css')) }}">
@endpush

@section('content')
@php
    $wallet = $driverWallet;
    $walletHistory = $walletHistory ?? collect();
    $tripSchedules = $tripSchedules ?? collect();
    $tripActionCount = $tripActionCount ?? 0;
    $revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];
    $mustChangePassword = $mustChangePassword ?? false;

    $driverDefaultTab = request('tab');
    if ($driverDefaultTab === 'deposit') {
        $driverDefaultTab = 'earnings';
    }
    $accountTabs = ['account', 'account-profile', 'account-update', 'account-password'];
    if (! in_array($driverDefaultTab, array_merge(['trips', 'history', 'earnings', 'wallet', 'invite', 'customers', 'inbox', 'settings'], $accountTabs), true)) {
        if ($driverDefaultTab === 'settings-docs') {
            $driverDefaultTab = 'account-update';
        } else {
            $driverDefaultTab = ($mustChangePassword ?? false) ? 'account-password' : 'trips';
        }
    }
    if (($mustChangePassword ?? false) && $driverDefaultTab === 'account') {
        $driverDefaultTab = 'account-password';
    }

    $tripHistory = $tripHistory ?? collect();
    $pendingTripRequestGroups = $pendingTripRequestGroups ?? collect();

    $availabilityStatus = $profile?->availability_status ?? 'off_duty';
    $driverTripActive = $driverTripActive ?? false;
    $driverTripUpcoming = $driverTripUpcoming ?? false;
    $driverOnTrip = $driverOnTrip ?? $driverTripActive;
    $driverPaused = $availabilityStatus === 'off_duty';
    $driverLocationReady = ! $driverPaused && $profile && $profile->hasFreshLocation();
    $driverNeedsLocationShare = $profile && ! $driverPaused && ! $driverLocationReady;

    $heroStatus = $profile
        ? $profile->heroStatusMeta($driverTripActive, $driverTripUpcoming)
        : ['key' => 'offline', 'label' => ''];

    $driverMapTripPins = $driverMapTripPins ?? [];
    $driverActiveMapNav = $driverActiveMapNav ?? null;
    $driverMapLat = $driverLocationReady ? ($profile->last_lat ?? null) : null;
    $driverMapLng = $driverLocationReady ? ($profile->last_lng ?? null) : null;
    $pendingChangeRequest = $profile
        ? app(\App\Services\DriverProfileChangeService::class)->pendingFor($profile)
        : null;
@endphp

<div class="driver-page driver-page--map {{ $driverPaused ? 'is-duty-off' : 'is-duty-on' }}"
     data-driver-tabs
     data-driver-tabs-active="{{ $driverDefaultTab }}"
     data-driver-tabs-base="{{ route('driver.dashboard') }}"
     data-must-change-password="{{ $mustChangePassword ? '1' : '0' }}"
     data-wait-progress-root>
@if($profile)
    <section class="driver-map-hero" id="driver-map-hero"
             data-driver-map-lat="{{ $driverMapLat }}"
             data-driver-map-lng="{{ $driverMapLng }}">
        <div class="driver-map-hero__canvas" id="driver-map-canvas" aria-label="Vị trí của bạn trên bản đồ"></div>
        <div class="driver-map-hero__scrim" aria-hidden="true"></div>

        <div class="driver-map-hero__fabs" aria-label="Điều khiển bản đồ">
            <button type="button" class="driver-map-hero__locate-btn" id="driver-map-locate-btn" aria-label="Về vị trí của tôi">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
                </svg>
            </button>

            @if(! empty($driverActiveMapNav['google_url']) || ! empty($driverActiveMapNav['url']))
                <a href="{{ $driverActiveMapNav['google_url'] ?? $driverActiveMapNav['url'] }}"
                   class="driver-map-hero__nav-fab"
                   data-driver-map-nav
                   data-map-nav-provider="google"
                   @if(! empty($driverActiveMapNav['use_current_origin'])) data-map-nav-use-current-origin="1" @endif
                   @if(! empty($driverActiveMapNav['dest_lat']) && ! empty($driverActiveMapNav['dest_lng']))
                       data-dest-lat="{{ $driverActiveMapNav['dest_lat'] }}"
                       data-dest-lng="{{ $driverActiveMapNav['dest_lng'] }}"
                   @endif
                   @if(! empty($driverActiveMapNav['origin_lat']) && ! empty($driverActiveMapNav['origin_lng']))
                       data-origin-lat="{{ $driverActiveMapNav['origin_lat'] }}"
                       data-origin-lng="{{ $driverActiveMapNav['origin_lng'] }}"
                   @endif
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="Điều hướng Google Maps"
                   title="Điều hướng Google Maps">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                    </svg>
                </a>
            @endif
        </div>
    </section>

    @include('partials.driver-map-chrome')

    <div id="driver-location-bar" class="d-none" aria-hidden="true"
         data-driver-paused="{{ $driverPaused ? '1' : '0' }}"
         data-driver-on-trip="{{ $driverOnTrip ? '1' : '0' }}"
         data-driver-trip-active="{{ $driverTripActive ? '1' : '0' }}"
         data-driver-trip-upcoming="{{ $driverTripUpcoming ? '1' : '0' }}"
         data-needs-location="{{ $driverNeedsLocationShare ? '1' : '0' }}">
        <input type="hidden" id="driver-location-province" value="{{ $profile->last_province ?? 'TP.HCM' }}">
        <input type="hidden" id="driver-location-lat" value="{{ $driverLocationReady ? ($profile->last_lat ?? '') : '' }}">
        <input type="hidden" id="driver-location-lng" value="{{ $driverLocationReady ? ($profile->last_lng ?? '') : '' }}">
        <input type="hidden" id="driver-location-detail" value="{{ $driverLocationReady ? ($profile->last_address ?? '') : '' }}">
        <p id="driver-location-address" class="d-none"></p>
        <p id="driver-location-meta" class="d-none"></p>
    </div>

    <section class="driver-location-fallback d-none" id="driver-location-fallback" hidden aria-label="Chọn vị trí trên bản đồ">
        <p class="driver-location-fallback__hint mb-2" id="driver-location-fallback-hint">
            Không lấy được GPS. Bấm «Lấy GPS» hoặc chọn vị trí trên bản đồ.
        </p>
        <div class="driver-location-fallback__actions mb-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="driver-location-gps-btn">
                Lấy GPS
            </button>
        </div>
        <div class="driver-location-input-wrap">
            <input type="text" id="driver-location-fallback-detail" class="form-control driver-location-input"
                   autocomplete="off">
            <button type="button" class="driver-location-map-btn address-map-trigger"
                    data-address-map-for="driver-location-fallback-detail"
                    data-address-map-lat="driver-location-lat"
                    data-address-map-lng="driver-location-lng"
                    data-address-map-province="driver-location-province"
                    data-address-map-mode="driver"
                    data-address-map-label="Chọn vị trí hiện tại"
                    aria-label="Chọn vị trí trên bản đồ" title="Bản đồ">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
                <span>Bản đồ</span>
            </button>
        </div>
    </section>

    <section class="driver-pickup-proximity-sheet @if(empty($driverPickupProximityLine ?? null)) d-none @endif"
             id="driver-pickup-proximity-sheet"
             aria-label="Khoảng cách đến khách"
             @if(empty($driverPickupProximityLine ?? null)) hidden @endif>
        <p class="driver-pickup-proximity-sheet__text mb-0"
           id="driver-pickup-proximity-line"
           role="status">{{ $driverPickupProximityLine ?? '' }}</p>
    </section>

    @include('partials.driver-bottom-panel', [
        'sheetInitiallyOpen' => $driverDefaultTab === 'trips'
            && (($pendingTripRequestGroups ?? collect())->isNotEmpty() || ($tripSchedules ?? collect())->isNotEmpty()),
    ])

    @php $inboxUnreadTotal = (int) (($inboxUnread['total'] ?? 0)); @endphp
    @include('partials.driver-app-dock', [
        'activeKey' => in_array($driverDefaultTab, ['invite', 'customers', 'wallet', 'settings'], true) ? '' : (
            str_starts_with((string) $driverDefaultTab, 'account') ? 'account' : $driverDefaultTab
        ),
        'tabs' => [
            ['key' => 'trips', 'label' => 'Trang chủ', 'short' => 'Trang chủ', 'badge' => $tripActionCount, 'hot' => $tripActionCount > 0],
            ['key' => 'earnings', 'label' => 'Thu nhập', 'short' => 'Thu nhập'],
            ['key' => 'history', 'label' => 'Lịch sử chạy', 'short' => 'Lịch sử'],
            ['key' => 'inbox', 'label' => 'Hộp thư', 'short' => 'Hộp thư', 'badge' => $inboxUnreadTotal, 'hot' => $inboxUnreadTotal > 0],
            ['key' => 'account', 'label' => 'Thông tin cá nhân', 'short' => 'Cá nhân'],
        ],
    ])

    @include('partials.driver-drawer')
@endif

    <div class="driver-overlay-panels {{ $driverDefaultTab !== 'trips' ? 'is-open' : '' }}" id="driver-overlay-panels" @if($driverDefaultTab === 'trips') hidden @endif>
        <div class="driver-overlay-panels__bar">
            <button type="button" class="driver-overlay-panels__back" data-driver-tab="trips" aria-label="Quay lại">←</button>
            <strong class="driver-overlay-panels__title" id="driver-overlay-title" data-driver-tab="trips" role="button" tabindex="0">Quay lại</strong>
            <span class="driver-overlay-panels__spacer" aria-hidden="true"></span>
        </div>
        <div class="driver-overlay-panels__body">
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

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'earnings' ? 'is-active' : '' }}"
                     id="driver-section-earnings" data-driver-tab="earnings" @if($driverDefaultTab !== 'earnings') hidden @endif>
                @include('partials.driver-tab-earnings', [
                    'revenueStats' => $revenueStats,
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'wallet' ? 'is-active' : '' }}"
                     id="driver-section-wallet" data-driver-tab="wallet" @if($driverDefaultTab !== 'wallet') hidden @endif>
                @if($wallet)
                    @include('partials.driver-tab-wallet', [
                        'wallet' => $wallet,
                        'walletHistory' => $walletHistory ?? collect(),
                    ])
                @else
                    @include('partials.driver-empty-state', [
                        'title' => 'Chưa có hồ sơ tài xế',
                    ])
                @endif
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'invite' ? 'is-active' : '' }}"
                     id="driver-section-invite" data-driver-tab="invite" @if($driverDefaultTab !== 'invite') hidden @endif>
                @include('partials.driver-invite-panel', [
                    'inviteUrl' => $driverInviteUrl ?? null,
                    'inviteDiscountPercent' => $driverInviteDiscountPercent ?? null,
                    'commissionReferral' => $driverCommissionReferral ?? null,
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'customers' ? 'is-active' : '' }}"
                     id="driver-section-customers" data-driver-tab="customers" @if($driverDefaultTab !== 'customers') hidden @endif>
                @include('partials.driver-tab-customers', [
                    'referredCustomers' => $referredCustomers ?? collect(),
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'inbox' ? 'is-active' : '' }}"
                     id="driver-section-inbox" data-driver-tab="inbox" @if($driverDefaultTab !== 'inbox') hidden @endif>
                @include('partials.driver-tab-inbox', [
                    'profile' => $profile,
                    'walletBlockReason' => $walletBlockReason ?? null,
                    'walletNotice' => $walletNotice ?? null,
                    'inboxInfoMessages' => $inboxInfoMessages ?? collect(),
                    'inboxNoticeMessages' => $inboxNoticeMessages ?? collect(),
                    'inboxChatThreads' => $inboxChatThreads ?? collect(),
                    'inboxUnread' => $inboxUnread ?? ['info' => 0, 'notice' => 0, 'chat' => 0, 'total' => 0],
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'account' ? 'is-active' : '' }}"
                     id="driver-section-account" data-driver-tab="account" @if($driverDefaultTab !== 'account') hidden @endif>
                @include('partials.driver-tab-account', [
                    'mustChangePassword' => $mustChangePassword ?? false,
                    'pendingChangeRequest' => $pendingChangeRequest,
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'account-profile' ? 'is-active' : '' }}"
                     id="driver-section-account-profile" data-driver-tab="account-profile" @if($driverDefaultTab !== 'account-profile') hidden @endif>
                @include('partials.driver-tab-account-profile', [
                    'user' => $user ?? auth()->user(),
                    'profile' => $profile,
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'account-update' ? 'is-active' : '' }}"
                     id="driver-section-account-update" data-driver-tab="account-update" @if($driverDefaultTab !== 'account-update') hidden @endif>
                @include('partials.driver-tab-account-update', [
                    'profile' => $profile,
                    'pendingChangeRequest' => $pendingChangeRequest,
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'account-password' ? 'is-active' : '' }}"
                     id="driver-section-account-password" data-driver-tab="account-password" @if($driverDefaultTab !== 'account-password') hidden @endif>
                @include('partials.driver-tab-account-password', [
                    'mustChangePassword' => $mustChangePassword ?? false,
                ])
            </section>

            <section class="driver-section driver-tab-pane {{ $driverDefaultTab === 'settings' ? 'is-active' : '' }}"
                     id="driver-section-settings" data-driver-tab="settings" @if($driverDefaultTab !== 'settings') hidden @endif>
                @include('partials.driver-tab-settings', [
                    'profile' => $profile,
                ])
            </section>
        </div>
    </div>
</div>

@include('partials.address-map-picker-modal')
@endsection

@push('scripts')
@php
    $driverAppSettings = [
        'locale' => ($profile->locale ?? null) ?: 'vi',
        'sound_enabled' => (bool) ($profile->sound_enabled ?? true),
        'sound_preset' => \App\Support\DriverSoundPresets::normalize($profile->sound_preset ?? null),
    ];
@endphp
<script>
window.__driverLocationUrl = @json(route('driver.location.update'));
window.__driverAvailabilityUrl = @json(route('driver.availability.update'));
@include('partials.geocode-client-config')
window.__driverDashboardUrl = @json(route('driver.dashboard', ['tab' => 'trips']));
window.__driverDashboardPollUrl = @json(route('driver.dashboard.poll'));
window.__driverMapTripPins = @json($driverMapTripPins ?? []);
window.__driverAppSettings = @json($driverAppSettings);
</script>
<script src="{{ asset('js/geocode-search-ui.js') }}?v={{ filemtime(public_path('js/geocode-search-ui.js')) }}"></script>
<script src="{{ asset('js/geocode-resolve.js') }}?v={{ filemtime(public_path('js/geocode-resolve.js')) }}"></script>
<script src="{{ asset('js/geocode-address-autocomplete.js') }}?v={{ filemtime(public_path('js/geocode-address-autocomplete.js')) }}"></script>
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
<script src="{{ asset('js/driver-location-save.js') }}?v={{ filemtime(public_path('js/driver-location-save.js')) }}"></script>
<script src="{{ asset('js/driver-location-gps.js') }}?v={{ filemtime(public_path('js/driver-location-gps.js')) }}"></script>
<script src="{{ asset('js/driver-map-nav.js') }}?v={{ filemtime(public_path('js/driver-map-nav.js')) }}"></script>
<script src="{{ asset('js/driver-call-reveal.js') }}?v={{ filemtime(public_path('js/driver-call-reveal.js')) }}"></script>
<script src="{{ asset('js/driver-live-map.js') }}?v={{ filemtime(public_path('js/driver-live-map.js')) }}"></script>
<script src="{{ asset('js/driver-availability-toggle.js') }}?v={{ filemtime(public_path('js/driver-availability-toggle.js')) }}"></script>
<script src="{{ asset('js/trip-chat.js') }}?v={{ filemtime(public_path('js/trip-chat.js')) }}"></script>
<script>
(function () {
    if (window.GeocodeAddressAutocomplete) {
        window.GeocodeAddressAutocomplete.attach({
            detailInputId: 'driver-location-fallback-detail',
            latInputId: 'driver-location-lat',
            lngInputId: 'driver-location-lng',
            provinceInputId: 'driver-location-province',
            defaultProvince: @json($profile->last_province ?? 'TP.HCM'),
        });
    }
})();
</script>
<script src="{{ asset('js/driver-trip-request-actions.js') }}?v={{ filemtime(public_path('js/driver-trip-request-actions.js')) }}"></script>
<script src="{{ asset('js/swipe-to-action.js') }}?v={{ filemtime(public_path('js/swipe-to-action.js')) }}"></script>
<script src="{{ asset('js/driver-workflow-actions.js') }}?v={{ filemtime(public_path('js/driver-workflow-actions.js')) }}"></script>
<script src="{{ asset('js/wait-progress.js') }}?v={{ filemtime(public_path('js/wait-progress.js')) }}"></script>
<script src="{{ asset('js/driver-shell.js') }}?v={{ filemtime(public_path('js/driver-shell.js')) }}"></script>
<script src="{{ asset('js/driver-tabs.js') }}?v={{ filemtime(public_path('js/driver-tabs.js')) }}"></script>
<script src="{{ asset('js/driver-inbox.js') }}?v={{ filemtime(public_path('js/driver-inbox.js')) }}"></script>
<script src="{{ asset('js/driver-bottom-panel.js') }}?v={{ filemtime(public_path('js/driver-bottom-panel.js')) }}"></script>
<script src="{{ asset('js/driver-i18n.js') }}?v={{ filemtime(public_path('js/driver-i18n.js')) }}"></script>
<script src="{{ asset('js/driver-sounds.js') }}?v={{ filemtime(public_path('js/driver-sounds.js')) }}"></script>
<script src="{{ asset('js/driver-invite.js') }}?v={{ filemtime(public_path('js/driver-invite.js')) }}"></script>
<script src="{{ asset('js/driver-wallet-deposit.js') }}?v={{ filemtime(public_path('js/driver-wallet-deposit.js')) }}"></script>
<script src="{{ asset('js/driver-late-pickup.js') }}?v={{ filemtime(public_path('js/driver-late-pickup.js')) }}"></script>
<script src="{{ asset('js/idle-poll.js') }}?v={{ filemtime(public_path('js/idle-poll.js')) }}"></script>
<script src="{{ asset('js/driver-dashboard-poll.js') }}?v={{ filemtime(public_path('js/driver-dashboard-poll.js')) }}"></script>
@endpush
