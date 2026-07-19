@extends('layouts.app')

@section('content')
@php
    $activeTab = $activeTab ?? 'account';
    $phone = $profile['phone'] ?? $user->phone;
@endphp

<div class="customer-page customer-account-page">
    @if($activeTab === 'inbox')
        <header class="customer-account-hero mb-3">
            <div class="customer-account-hero__avatar" aria-hidden="true">
                {{ $user->avatarInitial() }}
            </div>
            <div class="customer-account-hero__copy">
                <p class="customer-account-hero__eyebrow">Hộp thư</p>
                <h1 class="customer-account-hero__title">Thông báo</h1>
                <p class="customer-account-hero__meta mb-0">{{ $phone }}</p>
            </div>
        </header>

        <div class="customer-account-card">
            @include('partials.customer-tab-inbox', [
                'inboxTab' => $inboxTab ?? 'notice',
                'inboxUnread' => $inboxUnread ?? ['info' => 0, 'notice' => 0, 'total' => 0],
                'inboxNoticeMessages' => $inboxNoticeMessages ?? collect(),
                'inboxInfoMessages' => $inboxInfoMessages ?? collect(),
            ])
        </div>
    @else
        @if($activeTab === 'account')
            <header class="customer-account-hero mb-3">
                <div class="customer-account-hero__avatar" aria-hidden="true">
                    {{ $user->avatarInitial() }}
                </div>
                <div class="customer-account-hero__copy">
                    <p class="customer-account-hero__eyebrow">Tài khoản khách</p>
                    <h1 class="customer-account-hero__title">{{ $phone }}</h1>
                    <p class="customer-account-hero__meta mb-0">Đăng nhập bằng SĐT và PIN</p>
                </div>
            </header>
        @endif

        @if($activeTab === 'account')
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
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@push('scripts')
@if($activeTab === 'inbox')
<script src="{{ asset('js/customer-inbox.js') }}?v={{ filemtime(public_path('js/customer-inbox.js')) }}"></script>
@endif
@if($activeTab === 'wallet')
<script src="{{ asset('js/driver-wallet-deposit.js') }}?v={{ filemtime(public_path('js/driver-wallet-deposit.js')) }}"></script>
@endif
@if($activeTab === 'update')
<script>
document.querySelectorAll('.customer-profile-update-form [data-file-trigger]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = btn.closest('.register-file-field');
        var input = wrap && wrap.querySelector('input[type="file"]');
        if (input) input.click();
    });
});
document.querySelectorAll('.customer-profile-update-form input[type="file"]').forEach(function (input) {
    input.addEventListener('change', function () {
        var wrap = input.closest('.register-file-field');
        var label = wrap && wrap.querySelector('[data-file-name]');
        var preview = wrap && wrap.querySelector('[data-doc-preview]');
        var file = input.files && input.files[0];
        if (label) label.textContent = file ? file.name : 'Chưa chọn';
        if (wrap) wrap.classList.toggle('has-file', !!file);
        if (preview) {
            if (preview.dataset.objectUrl) {
                URL.revokeObjectURL(preview.dataset.objectUrl);
                delete preview.dataset.objectUrl;
            }
            if (file && file.type.indexOf('image/') === 0) {
                var url = URL.createObjectURL(file);
                preview.dataset.objectUrl = url;
                preview.src = url;
                preview.classList.remove('d-none');
            } else {
                preview.removeAttribute('src');
                preview.classList.add('d-none');
            }
        }
    });
});
</script>
@endif
@endpush
