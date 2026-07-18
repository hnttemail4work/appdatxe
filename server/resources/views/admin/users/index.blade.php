@extends('layouts.console')

@section('console')
@php
    $status = $status ?? 'all';
    $q = $q ?? '';
    $statusTabs = [
        ['key' => 'all', 'label' => 'Tất cả'],
        ['key' => 'pending', 'label' => 'Chờ duyệt'],
        ['key' => 'active', 'label' => 'Đang hoạt động'],
        ['key' => 'inactive', 'label' => 'Đã vô hiệu hóa'],
        ['key' => 'suspended', 'label' => 'Tạm ngưng'],
    ];
@endphp

@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'users'])

        @if(session('customer_password_reset'))
            @php $reset = session('customer_password_reset'); @endphp
            <div class="alert alert-warning mt-3 mb-0">
                <div class="fw-semibold">PIN mới cho khách — gửi ngay</div>
                <div>{{ $reset['name'] ?? '' }} · {{ $reset['phone'] ?? '' }}</div>
                <div class="fs-4"><code>{{ $reset['password'] ?? '' }}</code></div>
            </div>
        @endif

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
                        <th>CCCD</th>
                        <th>Trạng thái</th>
                        <th>Cập nhật chờ</th>
                        <th class="text-end" style="width:14rem"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    @php
                        $pendingChange = $user->customerProfileChangeRequests->first();
                        $frontUrl = $user->idCardPhotoUrl('photo_id_card');
                        $backUrl = $user->idCardPhotoUrl('photo_id_card_back');
                    @endphp
                    <tr>
                        <td>
                            <div class="cell-primary">{{ $user->preferredDisplayName() }}</div>
                            <div class="text-muted small">#{{ $user->id }}</div>
                        </td>
                        <td class="cell-muted">{{ $user->phone ?: '—' }}</td>
                        <td class="cell-muted">
                            <div class="d-flex flex-column gap-1">
                                @if($frontUrl)
                                    <a href="{{ $frontUrl }}" target="_blank" rel="noopener">Trước</a>
                                @else
                                    <span>—</span>
                                @endif
                                @if($backUrl)
                                    <a href="{{ $backUrl }}" target="_blank" rel="noopener">Sau</a>
                                @endif
                            </div>
                        </td>
                        <td>
                            @php
                                $pill = match (true) {
                                    $user->approval_status === \App\Models\User::APPROVAL_PENDING => ['warning', 'Chờ duyệt'],
                                    $user->approval_status === \App\Models\User::APPROVAL_REJECTED => ['danger', 'Từ chối'],
                                    $user->status === 'active' => ['accent', 'Hoạt động'],
                                    $user->status === 'suspended' => ['warning', 'Tạm ngưng'],
                                    default => ['neutral', 'Vô hiệu hóa'],
                                };
                            @endphp
                            <span class="status-pill status-pill--{{ $pill[0] }}">{{ $pill[1] }}</span>
                        </td>
                        <td class="cell-muted">
                            @if($pendingChange)
                                <div class="small mb-1">#{{ $pendingChange->id }}</div>
                                <div class="d-inline-flex flex-wrap gap-1">
                                    <form method="POST" action="{{ route('admin.users.changes.approve', $pendingChange) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary">Duyệt</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.changes.reject', $pendingChange) }}"
                                          data-confirm="Từ chối yêu cầu cập nhật #{{ $pendingChange->id }}?"
                                          data-confirm-title="Từ chối cập nhật"
                                          data-confirm-variant="danger"
                                          data-confirm-ok="Từ chối">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Từ chối</button>
                                    </form>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <form method="POST" action="{{ route('admin.users.resetPassword', $user) }}"
                                      data-confirm="Đặt lại PIN 6 số cho {{ $user->preferredDisplayName() }}?"
                                      data-confirm-title="Đặt lại PIN"
                                      data-confirm-variant="warning"
                                      data-confirm-ok="Đặt lại">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning">Reset PIN</button>
                                </form>
                                @if($user->approval_status === \App\Models\User::APPROVAL_PENDING)
                                    <form method="POST" action="{{ route('admin.users.activate', $user) }}"
                                          data-confirm="Duyệt đăng ký {{ $user->preferredDisplayName() }}?"
                                          data-confirm-title="Duyệt khách hàng"
                                          data-confirm-ok="Duyệt">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary">Duyệt</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.reject', $user) }}"
                                          data-confirm="Từ chối đăng ký {{ $user->preferredDisplayName() }}?"
                                          data-confirm-title="Từ chối đăng ký"
                                          data-confirm-variant="danger"
                                          data-confirm-ok="Từ chối">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Từ chối</button>
                                    </form>
                                @elseif($user->status === 'active')
                                    <form method="POST" action="{{ route('admin.users.deactivate', $user) }}"
                                          data-confirm="Vô hiệu hóa {{ $user->preferredDisplayName() }}? Khách sẽ không đăng nhập được."
                                          data-confirm-title="Vô hiệu hóa tài khoản"
                                          data-confirm-variant="danger"
                                          data-confirm-ok="Vô hiệu hóa">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Vô hiệu hóa</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.activate', $user) }}"
                                          data-confirm="Kích hoạt lại {{ $user->preferredDisplayName() }}?"
                                          data-confirm-title="Kích hoạt tài khoản"
                                          data-confirm-ok="Kích hoạt">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary">Kích hoạt</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Không có khách hàng phù hợp.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
@endsection
