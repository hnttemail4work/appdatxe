@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver.css') }}?v={{ filemtime(public_path('css/driver.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@section('content')
<div class="driver-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-0 card-title-bar">Hồ sơ tài xế</h3>
        </div>
    </div>

    @if(!$profile)
    <div class="alert alert-warning">Chưa có hồ sơ — liên hệ quản lý.</div>
    @else
    @php
        $profileDefaultTab = request('tab') === 'photos' ? 'photos' : 'info';
    @endphp

    <div class="d-flex gap-3 align-items-center mb-3 flex-wrap">
        @if($profile->photo_portrait)
            <img src="{{ $profile->photoUrl('photo_portrait') }}" alt="Chân dung"
                 class="rounded-circle object-fit-cover border" style="width:64px;height:64px;">
        @else
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                 style="width:64px;height:64px;font-size:1.4rem;font-weight:700;">
                {{ mb_substr($user->name, 0, 1) }}
            </div>
        @endif
        <div>
            <h5 class="mb-0">{{ $user->name }}</h5>
            <span class="text-muted small">{{ $user->phone }}</span>
            <span class="status-pill status-pill--{{ $profile->displayStatusColor() }} ms-1">{{ $profile->displayStatusLabel() }}</span>
            @if($profile->driver_code)
                <span class="driver-meta-code ms-1">{{ $profile->driver_code }}</span>
            @endif
        </div>
    </div>

    @include('partials.screen-tabs-start', [
        'prefix' => 'driver-profile',
        'activeKey' => $profileDefaultTab,
        'tabs' => [
            ['key' => 'photos', 'label' => 'Giấy tờ & ảnh'],
            ['key' => 'info', 'label' => 'Thông tin'],
        ],
    ])

    @include('partials.screen-tab-pane', ['prefix' => 'driver-profile', 'key' => 'photos', 'active' => $profileDefaultTab === 'photos'])
    <div class="card shadow-sm p-4 border-0" id="photos-section">
        @include('partials.driver-photo-manager', [
            'driver'              => $profile,
            'action'              => route('driver.photos.upload'),
            'submitLabel'         => $profile->identityPhotosLocked() ? 'Lưu ảnh xe' : 'Lưu thay đổi ảnh',
            'allowVehicleDelete'  => false,
            'lockIdentityPhotos'  => $profile->identityPhotosLocked(),
        ])
    </div>
    @include('partials.screen-tab-pane-end')

    @include('partials.screen-tab-pane', ['prefix' => 'driver-profile', 'key' => 'info', 'active' => $profileDefaultTab === 'info'])
    <div class="card shadow-sm p-4 border-0">
        <form method="POST" action="{{ route('driver.profile.update') }}" id="driver-profile-form">
            @csrf @method('PATCH')
            @include('partials.driver-core-fields', [
                'context'  => 'profile',
                'user'     => $user,
                'profile'  => $profile,
                'sections' => ['contact', 'vehicle', 'bank'],
            ])
            <button class="btn btn-primary mt-4">Lưu thông tin</button>
        </form>
    </div>
    @include('partials.driver-completeness-card', ['profile' => $profile])
    @include('partials.screen-tab-pane-end')

    @include('partials.screen-tabs-end')
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/driver-profile-form.js') }}"></script>
@endpush
