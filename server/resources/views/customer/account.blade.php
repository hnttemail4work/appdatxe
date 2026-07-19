@extends('layouts.app')

@php
    $activeTab = $activeTab ?? 'account';
    $tabTitles = [
        'inbox' => 'Hộp thư',
        'account' => 'Tài khoản',
        'profile' => 'Hồ sơ',
        'info' => 'Cập nhật thông tin',
        'update' => 'Cập nhật CCCD',
        'password' => 'Đổi PIN',
        'wallet' => 'Ví',
    ];
    $pageTitle = $tabTitles[$activeTab] ?? 'Tài khoản';
    $backUrl = in_array($activeTab, ['profile', 'info', 'update', 'password', 'wallet'], true)
        ? route('customer.account', ['tab' => 'account'])
        : route('home');
@endphp

@section('navTitle', $pageTitle)
@section('navBack', $backUrl)

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
        @include('partials.customer-tab-account', ['pendingChange' => $pendingChange ?? null])
    @elseif($activeTab === 'profile')
        @include('partials.customer-tab-account-profile', [
            'user' => $user,
            'profile' => $profile,
        ])
    @elseif($activeTab === 'info')
        @include('partials.customer-tab-account-info', [
            'user' => $user,
            'profile' => $profile,
        ])
    @elseif($activeTab === 'update')
        @include('partials.customer-profile-update-form', [
            'user' => $user,
            'pendingChange' => $pendingChange ?? null,
        ])
    @elseif($activeTab === 'password')
        @include('partials.customer-tab-account-password')
    @elseif($activeTab === 'wallet')
        @include('partials.customer-tab-wallet', [
            'wallet' => $wallet ?? null,
            'pendingDeposits' => $pendingDeposits ?? collect(),
            'walletHistory' => $walletHistory ?? collect(),
        ])
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@if($activeTab === 'inbox')
<link rel="stylesheet" href="{{ asset('css/trip-chat.css') }}?v={{ filemtime(public_path('css/trip-chat.css')) }}">
@endif
@if($activeTab === 'wallet')
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
@endif
@if($activeTab === 'update')
<script src="{{ asset('js/photo-upload-slots.js') }}?v={{ filemtime(public_path('js/photo-upload-slots.js')) }}"></script>
@endif
@endpush
