@extends('layouts.console')

@section('console')
@php
$pending = $driver->isPendingApproval();
$rejected = $driver->isRejected();
$viewOnly = $pending || $rejected;
$hasRequest = (bool) $driver->pendingChangeRequest;

$tab = request('tab');
$driverEditDefault = match (true) {
    $tab === 'photos' => 'photos',
    $tab === 'wallet' => 'wallet',
    $tab === 'requests' && $hasRequest => 'requests',
    $hasRequest && ($tab === null || $tab === '') => 'requests',
    default => 'info',
};
$driverEditBackHref = route('admin.drivers');
$driverEditBackLabel = '← Danh sách';
$driverEditNavActive = 'drivers';
$driverEditNavDriversSubpage = true;

$driverEditTabs = [];
if ($hasRequest) {
    $driverEditTabs[] = ['key' => 'requests', 'label' => 'Yêu cầu', 'badge' => 1, 'hot' => true];
}
$driverEditTabs[] = ['key' => 'info', 'label' => 'Thông tin'];
$driverEditTabs[] = ['key' => 'photos', 'label' => 'Giấy tờ'];
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

        <a href="{{ $pending ? route('admin.drivers', ['filter' => 'pending']) : $driverEditBackHref }}"
           class="driver-edit-back">{{ $driverEditBackLabel }}</a>

        @if($pending)
            @include('partials.admin-pending-approval-panel', [
                'approveUrl' => route('admin.drivers.approve', $driver),
                'rejectUrl' => route('admin.drivers.reject', $driver),
                'prefix' => 'driver-approve-'.$driver->id,
                'user' => $driver->user,
                'phone' => $driver->user?->phone,
                'frontUrl' => $driver->photoUrl('photo_id_card'),
                'backUrl' => $driver->photoUrl('photo_id_card_back'),
                'extraPhotos' => [
                    [
                        'side' => 'portrait',
                        'field' => 'photo_portrait',
                        'label' => 'Chân dung',
                        'url' => $driver->photoUrl('photo_portrait'),
                    ],
                    [
                        'side' => 'license_front',
                        'field' => 'photo_license_front',
                        'label' => 'Bằng lái trước',
                        'url' => $driver->photoUrl('photo_license_front'),
                    ],
                    [
                        'side' => 'license_back',
                        'field' => 'photo_license_back',
                        'label' => 'Bằng lái sau',
                        'url' => $driver->photoUrl('photo_license_back'),
                    ],
                ],
            ])
        @else
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
        @endif
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}?v={{ filemtime(public_path('css/driver-mgmt.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin-cccd-scan.js') }}?v={{ filemtime(public_path('js/admin-cccd-scan.js')) }}"></script>
<script src="{{ asset('js/driver-approval-actions.js') }}?v={{ filemtime(public_path('js/driver-approval-actions.js')) }}"></script>
<script src="{{ asset('js/photo-upload-slots.js') }}?v={{ filemtime(public_path('js/photo-upload-slots.js')) }}"></script>
@endpush
