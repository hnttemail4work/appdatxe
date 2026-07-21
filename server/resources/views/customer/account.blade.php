@extends('layouts.app')

@php
    $activeTab = $activeTab ?? 'account';
    $tabTitles = [
        'inbox' => 'Hộp thư',
        'account' => 'Tài khoản',
        'profile' => 'Hồ sơ khách',
        'wallet' => 'Ví',
        'trips' => 'Lịch sử chuyến',
        'settings' => 'Cài đặt',
    ];
    $pageTitle = $tabTitles[$activeTab] ?? 'Tài khoản';
    $backUrl = in_array($activeTab, ['profile', 'wallet', 'trips', 'settings'], true)
        ? route('customer.account', ['tab' => 'account'])
        : route('home');
@endphp

@section('navTitle', $pageTitle)
@section('navBack', $backUrl)
@section('navActions')
    @if(($activeTab ?? '') === 'settings')
        @include('partials.settings-header-actions', ['installId' => 'customer-settings-pwa-install'])
    @endif
@endsection

@section('content')
<div class="customer-page customer-account-page">
    @if($activeTab === 'inbox')
        <div class="customer-account-card">
            @include('partials.customer-tab-inbox', [
                'inboxTab' => $inboxTab ?? 'info',
                'inboxUnread' => $inboxUnread ?? ['info' => 0, 'notice' => 0, 'chat' => 0, 'total' => 0],
                'inboxNoticeMessages' => $inboxNoticeMessages ?? collect(),
                'inboxInfoMessages' => $inboxInfoMessages ?? collect(),
                'inboxChatThreads' => $inboxChatThreads ?? collect(),
            ])
        </div>
    @elseif($activeTab === 'account')
        @include('partials.customer-tab-account', [
            'pendingChange' => $pendingChange ?? null,
            'user' => $user,
            'profile' => $profile ?? [],
            'wallet' => $wallet ?? null,
        ])
    @elseif($activeTab === 'profile')
        @include('partials.customer-tab-account-hub', [
            'user' => $user,
            'profile' => $profile,
            'pendingChange' => $pendingChange ?? null,
        ])
    @elseif($activeTab === 'wallet')
        @include('partials.customer-tab-wallet', [
            'wallet' => $wallet ?? null,
            'pendingDeposits' => $pendingDeposits ?? collect(),
            'walletHistory' => $walletHistory ?? collect(),
        ])
    @elseif($activeTab === 'trips')
        @include('partials.customer-tab-trips', [
            'completedTrips' => $completedTrips ?? null,
            'completedTripRows' => $completedTripRows ?? [],
        ])
    @elseif($activeTab === 'settings')
        @include('partials.customer-tab-settings', ['user' => $user])
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@if($activeTab === 'inbox')
<link rel="stylesheet" href="{{ asset('css/trip-chat.css') }}?v={{ filemtime(public_path('css/trip-chat.css')) }}">
@endif
@if(in_array($activeTab, ['wallet', 'profile', 'account', 'settings'], true))
<link rel="stylesheet" href="{{ asset('css/driver.css') }}?v={{ filemtime(public_path('css/driver.css')) }}">
@endif
@endpush

@push('scripts')
@if($activeTab === 'inbox')
<script src="{{ asset('js/trip-chat.js') }}?v={{ filemtime(public_path('js/trip-chat.js')) }}"></script>
<script src="{{ asset('js/trip-action-fabs.js') }}?v={{ filemtime(public_path('js/trip-action-fabs.js')) }}"></script>
<script src="{{ asset('js/customer-inbox.js') }}?v={{ filemtime(public_path('js/customer-inbox.js')) }}"></script>
@endif
@if($activeTab === 'wallet')
<script src="{{ asset('js/driver-wallet-deposit.js') }}?v={{ filemtime(public_path('js/driver-wallet-deposit.js')) }}"></script>
<script src="{{ asset('js/wallet-pull-refresh.js') }}?v={{ filemtime(public_path('js/wallet-pull-refresh.js')) }}"></script>
@endif
@if($activeTab === 'settings')
<script src="{{ asset('js/customer-settings.js') }}?v={{ filemtime(public_path('js/customer-settings.js')) }}"></script>
@endif
@if($activeTab === 'profile')
<script src="{{ asset('js/photo-upload-slots.js') }}?v={{ filemtime(public_path('js/photo-upload-slots.js')) }}"></script>
<script>
(function () {
    var hub = document.querySelector('[data-customer-profile-hub]');
    if (!hub) return;
    function showTab(key) {
        hub.querySelectorAll('[data-customer-profile-tab]').forEach(function (btn) {
            var on = btn.getAttribute('data-customer-profile-tab') === key;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        hub.querySelectorAll('[data-customer-profile-pane]').forEach(function (pane) {
            var on = pane.getAttribute('data-customer-profile-pane') === key;
            pane.classList.toggle('is-active', on);
            pane.hidden = !on;
        });
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', 'profile');
            url.searchParams.set('profile_tab', key);
            window.history.replaceState({}, '', url.toString());
        } catch (e) {}
    }
    hub.querySelectorAll('[data-customer-profile-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showTab(btn.getAttribute('data-customer-profile-tab') || 'info');
        });
    });
})();
</script>
@endif
@endpush
