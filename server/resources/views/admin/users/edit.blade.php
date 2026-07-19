@extends('layouts.console')

@section('console')
@php
$pending = $user->isCustomerApprovalPending();
$rejected = $user->approval_status === \App\Models\User::APPROVAL_REJECTED;
$viewOnly = $pending || $rejected;
$pendingChange = $pendingChange ?? null;
$hasRequest = (bool) $pendingChange;

$tab = request('tab');
$customerEditDefault = match (true) {
    $tab === 'photos' => 'photos',
    $tab === 'requests' && $hasRequest => 'requests',
    $hasRequest && ($tab === null || $tab === '') => 'requests',
    default => 'info',
};

$customerEditTabs = [];
if ($hasRequest) {
    $customerEditTabs[] = ['key' => 'requests', 'label' => 'Yêu cầu', 'badge' => 1, 'hot' => true];
}
$customerEditTabs[] = ['key' => 'info', 'label' => 'Thông tin'];
$customerEditTabs[] = ['key' => 'photos', 'label' => 'Giấy tờ'];
@endphp

@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel driver-edit-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'users'])

        <a href="{{ $pending ? route('admin.users', ['status' => 'pending']) : route('admin.users') }}"
           class="driver-edit-back">← Danh sách</a>

        @if($pending)
            @include('partials.admin-pending-approval-panel', [
                'approveUrl' => route('admin.users.activate', $user),
                'rejectUrl' => route('admin.users.reject', $user),
                'prefix' => 'customer-approve-'.$user->id,
                'user' => $user,
                'phone' => $user->phone,
                'frontUrl' => $user->idCardPhotoUrl('photo_id_card'),
                'backUrl' => $user->idCardPhotoUrl('photo_id_card_back'),
            ])
        @else
            @include('partials.customer-profile-edit-header', ['user' => $user])

            @if(session('customer_password_reset'))
                @php $reset = session('customer_password_reset'); @endphp
                <div class="alert alert-warning mt-3 mb-0">
                    <div class="fw-semibold">PIN mới cho khách — gửi ngay</div>
                    <div>{{ $reset['name'] ?? '' }} · {{ $reset['phone'] ?? '' }}</div>
                    <div class="fs-4"><code>{{ $reset['password'] ?? '' }}</code></div>
                </div>
            @endif

            @if($rejected)
                @include('partials.customer-rejection-note', ['user' => $user])
            @endif

            @include('partials.screen-tabs-start', [
                'prefix' => 'customer-edit',
                'activeKey' => $customerEditDefault,
                'tabs' => $customerEditTabs,
            ])

            @if($hasRequest)
            @include('partials.screen-tab-pane', ['prefix' => 'customer-edit', 'key' => 'requests', 'active' => $customerEditDefault === 'requests'])
            @include('partials.admin-customer-pending-change', ['pendingChange' => $pendingChange])
            @include('partials.screen-tab-pane-end')
            @endif

            @include('partials.screen-tab-pane', ['prefix' => 'customer-edit', 'key' => 'info', 'active' => $customerEditDefault === 'info'])
            @if($viewOnly)
                <div class="console-form">
                    @include('partials.admin-customer-form-fields', [
                        'user' => $user,
                        'readonly' => true,
                    ])
                </div>
            @else
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="console-form">
                    @csrf @method('PATCH')
                    @include('partials.admin-customer-form-fields', ['user' => $user])
                    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                        <button class="btn btn-primary px-4 fw-semibold">Lưu thông tin</button>
                    </div>
                </form>

                @if($user->approval_status === \App\Models\User::APPROVAL_APPROVED)
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-muted mb-2">Trạng thái tài khoản</h6>
                        <p class="mb-2">
                            <span class="status-pill status-pill--{{ \App\Support\AdminAccountStatus::color($user->status) }}">
                                {{ \App\Support\AdminAccountStatus::label($user->status, 'customer') }}
                            </span>
                        </p>
                        @include('partials.admin-account-status-actions', [
                            'entityLabel' => 'khách',
                            'isRunning' => $user->isAccountRunning(),
                            'suspendAction' => route('admin.users.deactivate', $user),
                            'resumeAction' => route('admin.users.activate', $user),
                        ])
                    </div>
                @endif

                <div class="mt-4 pt-3 border-top">
                    <h6 class="text-muted mb-2">Hỗ trợ tài khoản</h6>
                    <p class="small text-muted mb-2">Khách quên PIN — đặt lại tại đây rồi đọc PIN tạm cho khách.</p>
                    <div class="driver-password-reset-actions border rounded p-3 bg-light-subtle">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="fw-semibold">Đặt lại PIN</div>
                            <form method="POST"
                                  action="{{ route('admin.users.resetPassword', $user) }}"
                                  data-confirm="Đặt lại PIN 6 số cho khách này? PIN cũ sẽ không dùng được nữa."
                                  data-confirm-title="Đặt lại PIN"
                                  data-confirm-variant="warning"
                                  data-confirm-ok="Đặt lại">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning btn-sm fw-semibold">Đặt lại PIN</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
            @include('partials.screen-tab-pane-end')

            @include('partials.screen-tab-pane', ['prefix' => 'customer-edit', 'key' => 'photos', 'active' => $customerEditDefault === 'photos'])
            @include('partials.admin-customer-photos', [
                'user' => $user,
                'viewOnly' => $viewOnly,
            ])
            @include('partials.screen-tab-pane-end')

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
