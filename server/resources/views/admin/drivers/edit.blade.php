@extends('layouts.console')

@section('console')
@php
$pending = $driver->isPendingApproval();
$rejected = $driver->isRejected();
$viewOnly = $pending || $rejected;
$hasRequest = (bool) $driver->pendingChangeRequest;

$tab = request('tab');
$fromReferrals = request('from') === 'referrals';
$driverEditDefault = match (true) {
    $tab === 'photos' => 'photos',
    $tab === 'wallet' => 'wallet',
    $tab === 'invite' => 'invite',
    $tab === 'requests' && $hasRequest => 'requests',
    $hasRequest && ($tab === null || $tab === '') => 'requests',
    default => 'info',
};
$driverEditBackHref = $fromReferrals ? route('admin.referrals') : route('admin.drivers');
$driverEditBackLabel = $fromReferrals ? '← Giới thiệu' : '← Danh sách';
$driverEditNavActive = 'drivers';
$driverEditNavDriversSubpage = true;

$driverEditTabs = [];
if ($hasRequest) {
    $driverEditTabs[] = ['key' => 'requests', 'label' => 'Yêu cầu', 'badge' => 1, 'hot' => true];
}
$driverEditTabs[] = ['key' => 'info', 'label' => 'Thông tin'];
$driverEditTabs[] = ['key' => 'photos', 'label' => 'Giấy tờ'];
if (! $viewOnly) {
    $driverEditTabs[] = ['key' => 'invite', 'label' => 'QR tài xế'];
}
if (! $viewOnly && $driver->isOperational()) {
    $driverEditTabs[] = ['key' => 'wallet', 'label' => 'Ví tài xế'];
}
@endphp

@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel driver-edit-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', [
            'active' => $driverEditNavActive,
            'driversSubpage' => $driverEditNavDriversSubpage,
        ])

        <a href="{{ $driverEditBackHref }}" class="driver-edit-back">{{ $driverEditBackLabel }}</a>

        @include('partials.driver-profile-edit-header', ['driver' => $driver])

        @if(session('driver_password_reset'))
            @include('partials.driver-password-reset-alert')
        @endif

        @if($driver->isRejected())
            @include('partials.driver-rejection-note', ['driver' => $driver])
        @endif

        @include('partials.screen-tabs-start', [
            'prefix' => 'driver-edit',
            'activeKey' => $driverEditDefault,
            'tabs' => $driverEditTabs,
        ])

        @if($hasRequest)
        @include('partials.screen-tab-pane', ['prefix' => 'driver-edit', 'key' => 'requests', 'active' => $driverEditDefault === 'requests'])
        @include('partials.admin-driver-pending-change', ['driver' => $driver])
        @include('partials.screen-tab-pane-end')
        @endif

        @include('partials.screen-tab-pane', ['prefix' => 'driver-edit', 'key' => 'info', 'active' => $driverEditDefault === 'info'])
        @if($viewOnly)
            <div class="console-form">
                @include('partials.driver-form-fields', [
                    'mode'     => 'edit',
                    'driver'   => $driver,
                    'readonly' => true,
                ])
            </div>
        @else
            <form method="POST" action="{{ route('admin.drivers.update', $driver) }}" class="console-form">
                @csrf @method('PATCH')
                @include('partials.driver-form-fields', [
                    'mode'   => 'edit',
                    'driver' => $driver,
                ])
                <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                    <button class="btn btn-primary px-4 fw-semibold">Lưu thông tin</button>
                </div>
            </form>
            @if($driver->isApproved())
                <div class="mt-4 pt-3 border-top">
                    <h6 class="text-muted mb-2">Trạng thái tài khoản</h6>
                    <p class="mb-2">
                        <span class="status-pill status-pill--{{ \App\Support\AdminAccountStatus::color($driver->status) }}">
                            {{ \App\Support\AdminAccountStatus::label($driver->status) }}
                        </span>
                    </p>
                    @include('partials.admin-account-status-actions', [
                        'entityLabel' => 'tài xế',
                        'isRunning' => $driver->isAccountRunning(),
                        'suspendAction' => route('admin.drivers.destroy', $driver),
                        'resumeAction' => route('admin.drivers.activate', $driver),
                        'suspendMethod' => 'DELETE',
                    ])
                </div>
            @endif

            @if($driver->user)
            <div class="mt-4 pt-3 border-top">
                <h6 class="text-muted mb-2">Hỗ trợ tài khoản</h6>
                <p class="small text-muted mb-2">Tài xế quên mật khẩu gọi tổng đài — đặt lại tại đây rồi đọc mật khẩu tạm cho tài xế. Tài xế sẽ phải đổi mật khẩu khi đăng nhập.</p>
                @include('partials.driver-password-reset-admin', [
                    'driver' => $driver,
                    'canReset' => true,
                ])
            </div>
            @endif
        @endif
        @include('partials.screen-tab-pane-end')

        @include('partials.screen-tab-pane', ['prefix' => 'driver-edit', 'key' => 'photos', 'active' => $driverEditDefault === 'photos'])
        @include('partials.driver-photo-manager', [
            'driver'             => $driver,
            'viewOnly'           => $viewOnly,
            'action'             => route('admin.drivers.photos', $driver),
            'submitLabel'        => 'Lưu ảnh',
            'allowVehicleDelete' => true,
            'lockIdentityPhotos' => $driver->identityPhotosLocked(),
        ])
        @include('partials.screen-tab-pane-end')

        @if(! $viewOnly)
        @include('partials.screen-tab-pane', ['prefix' => 'driver-edit', 'key' => 'invite', 'active' => $driverEditDefault === 'invite'])
        @include('partials.admin-driver-invite-panel', [
            'driver' => $driver,
            'inviteReferral' => $inviteReferral ?? null,
            'commissionReferral' => $commissionReferral ?? null,
            'inviteFrom' => $fromReferrals ? 'referrals' : null,
        ])
        @include('partials.screen-tab-pane-end')
        @endif

        @if(! $viewOnly && $driver->isOperational())
        @include('partials.screen-tab-pane', ['prefix' => 'driver-edit', 'key' => 'wallet', 'active' => $driverEditDefault === 'wallet'])
        @include('partials.driver-wallet-admin-panel', [
            'driver' => $driver,
            'driverWallet' => $driverWallet,
            'pendingDeposits' => $pendingDeposits ?? collect(),
            'walletHistory' => $walletHistory ?? collect(),
        ])
        @include('partials.screen-tab-pane-end')
        @endif

        @include('partials.screen-tabs-end')
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}?v={{ filemtime(public_path('css/driver-mgmt.css')) }}">
<style>
.driver-invite-admin__qr {
    padding: .55rem;
    border-radius: .65rem;
    background: #fff;
    display: inline-block;
    line-height: 0;
}
.driver-invite-admin__qr img {
    display: block;
    border-radius: .3rem;
}
.driver-qr-admin__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(16.5rem, 1fr));
    gap: 1rem;
}
.driver-qr-card {
    border: 1px solid rgba(255, 255, 255, .1);
    border-radius: .85rem;
    background: rgba(255, 255, 255, .03);
    padding: 1rem 1.05rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: .85rem;
    min-height: 100%;
}
.driver-qr-card--muted {
    opacity: .92;
}
.driver-qr-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
}
.driver-qr-card__title {
    margin: 0;
    font-size: .95rem;
    font-weight: 700;
    color: #fff;
}
.driver-qr-card__body {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-start;
}
.driver-qr-card__form .form-label {
    font-size: .78rem;
    margin-bottom: .35rem;
}
.admin-idscan {
    min-width: 16rem;
    max-width: 22rem;
    padding: .65rem .7rem;
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: .65rem;
    background: rgba(255, 255, 255, .03);
    text-align: left;
}
.admin-idscan__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: .35rem;
    margin-bottom: .35rem;
}
.admin-idscan__head strong { font-size: .82rem; }
.admin-idscan__actions { display: flex; flex-wrap: wrap; gap: .25rem; }
.driver-edit-actions { max-width: 22rem; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/admin-cccd-scan.js') }}?v={{ filemtime(public_path('js/admin-cccd-scan.js')) }}"></script>
@endpush
