@extends('layouts.console')

@section('console')
@php
$pending = $driver->isPendingApproval();
$rejected = $driver->isRejected();
$viewOnly = $pending || $rejected;

$tab = request('tab');
$driverEditDefault = match (true) {
    $tab === 'photos' => 'photos',
    $tab === 'wallet' => 'wallet',
    default => 'info',
};

$driverEditTabs = [
    ['key' => 'info', 'label' => 'Thông tin'],
    ['key' => 'photos', 'label' => 'Giấy tờ'],
];
if (! $viewOnly && $driver->isOperational()) {
    $driverEditTabs[] = ['key' => 'wallet', 'label' => 'Ví tài xế'];
}
@endphp

<div class="console-panel driver-edit-panel">
    <div class="console-panel-body">
        @include('partials.operator-nav-tabs', ['active' => 'drivers', 'driversSubpage' => true])

        <a href="{{ route('operator.drivers') }}" class="driver-edit-back">← Danh sách</a>

        @include('partials.driver-operator-profile-header', ['driver' => $driver])

        @include('partials.screen-tabs-start', [
            'prefix' => 'driver-edit',
            'activeKey' => $driverEditDefault,
            'tabs' => $driverEditTabs,
        ])

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
            <form method="POST" action="{{ route('operator.drivers.update', $driver) }}" class="console-form">
                @csrf @method('PATCH')
                @include('partials.driver-form-fields', [
                    'mode'   => 'edit',
                    'driver' => $driver,
                ])
                <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                    <button class="btn btn-primary px-4 fw-semibold">Lưu thông tin</button>
                    @if($driver->status !== 'inactive')
                    <button type="submit" form="driver-deactivate-form" class="btn btn-outline-danger ms-auto">Vô hiệu hoá</button>
                    @endif
                </div>
            </form>
            @if($driver->status !== 'inactive')
            <form method="POST" action="{{ route('operator.drivers.destroy', $driver) }}" id="driver-deactivate-form"
                  data-confirm="Vô hiệu hoá tài xế này? Tài xế sẽ không đăng nhập được."
                  data-confirm-title="Vô hiệu hoá tài xế"
                  data-confirm-variant="danger"
                  data-confirm-ok="Vô hiệu hoá">
                @csrf @method('DELETE')
            </form>
            @endif
        @endif
        @include('partials.screen-tab-pane-end')

        @include('partials.screen-tab-pane', ['prefix' => 'driver-edit', 'key' => 'photos', 'active' => $driverEditDefault === 'photos'])
        @include('partials.driver-photo-manager', [
            'driver'             => $driver,
            'viewOnly'           => $viewOnly,
            'action'             => route('operator.drivers.photos', $driver),
            'submitLabel'        => 'Lưu ảnh',
            'allowVehicleDelete' => true,
            'lockIdentityPhotos' => $driver->identityPhotosLocked(),
        ])
        @include('partials.screen-tab-pane-end')

        @if(! $viewOnly && $driver->isOperational())
        @include('partials.screen-tab-pane', ['prefix' => 'driver-edit', 'key' => 'wallet', 'active' => $driverEditDefault === 'wallet'])
        @include('partials.driver-wallet-operator-panel', [
            'driver' => $driver,
            'driverWallet' => $driverWallet,
            'pendingDeposits' => $pendingDeposits ?? collect(),
        ])
        @include('partials.screen-tab-pane-end')
        @endif

        @include('partials.screen-tabs-end')
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}">
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}">
@endpush
