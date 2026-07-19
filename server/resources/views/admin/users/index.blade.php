@extends('layouts.console')

@section('console')
@php
    $status = $status ?? 'all';
    $q = $q ?? '';
    $statusTabs = [
        ['key' => 'all', 'label' => 'Danh sách'],
        ['key' => 'pending', 'label' => 'Chờ duyệt'],
        ['key' => 'rejected', 'label' => 'Đã từ chối'],
    ];
@endphp

@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'users'])

        <div class="screen-tabs-wrap mb-3 mt-3">
            <ul class="nav nav-tabs screen-tabs">
                @foreach($statusTabs as $tab)
                <li class="nav-item">
                    <a href="{{ route('admin.users', array_filter(['status' => $tab['key'] === 'all' ? null : $tab['key'], 'q' => $q !== '' ? $q : null])) }}"
                       class="nav-link {{ $status === $tab['key'] ? 'active' : '' }}">
                        {{ $tab['label'] }}
                    </a>
                </li>
                @endforeach
            </ul>
        </div>

        <form method="GET" action="{{ route('admin.users') }}" class="mb-3 d-flex flex-wrap gap-2">
            @if($status !== 'all')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm"
                   style="max-width:16rem" placeholder="Tìm tên / SĐT / email" aria-label="Tìm khách hàng">
            <button class="btn btn-sm btn-outline-primary">Tìm</button>
        </form>

        <div class="console-table-wrap">
            <table class="console-table">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Số điện thoại</th>
                        <th>Yêu cầu</th>
                        <th>Trạng thái</th>
                        <th class="text-end" style="width:8rem"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    @php
                        $pendingChange = $user->customerProfileChangeRequests->first();
                        $requestCount = $pendingChange ? 1 : 0;
                        $frontUrl = $user->idCardPhotoUrl('photo_id_card');
                    @endphp
                    <tr>
                        <td>
                            <div class="driver-mgmt-name">
                                @if($frontUrl)
                                    <img src="{{ $frontUrl }}" alt=""
                                         class="driver-mgmt-avatar rounded-circle object-fit-cover border">
                                @else
                                    <div class="driver-mgmt-avatar driver-mgmt-avatar-fallback">
                                        {{ mb_substr($user->preferredDisplayName(), 0, 1) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="cell-primary">{{ $user->preferredDisplayName() }}</div>
                                    <div class="text-muted small">#{{ $user->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="cell-muted">{{ $user->phone ?: '—' }}</td>
                        <td class="fw-semibold {{ $requestCount > 0 ? 'text-warning' : 'text-muted' }}">
                            {{ $requestCount }}
                        </td>
                        <td>
                            <span class="status-pill status-pill--{{ $user->customerDisplayStatusColor() }}">
                                {{ $user->customerDisplayStatusLabel() }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a href="{{ route('admin.users.edit', $requestCount > 0
                                        ? ['user' => $user, 'tab' => 'requests']
                                        : $user) }}"
                                   class="btn btn-sm {{ ($user->isCustomerApprovalPending() || $requestCount > 0) ? 'btn-primary' : 'btn-outline-primary' }}">
                                    Xem
                                </a>
                                @if($user->approval_status === \App\Models\User::APPROVAL_APPROVED)
                                    @include('partials.admin-account-status-actions', [
                                        'layout' => 'inline',
                                        'entityLabel' => 'khách',
                                        'isRunning' => $user->isAccountRunning(),
                                        'suspendAction' => route('admin.users.deactivate', $user),
                                        'resumeAction' => route('admin.users.activate', $user),
                                    ])
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Không có khách hàng phù hợp.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}?v={{ filemtime(public_path('css/driver-mgmt.css')) }}">
@endpush
