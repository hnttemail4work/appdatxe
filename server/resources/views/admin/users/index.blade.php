@extends('layouts.console')

@section('console')
@php
    $status = $status ?? 'all';
    $q = $q ?? '';
    $statusTabs = [
        ['key' => 'all', 'label' => 'Tất cả'],
        ['key' => 'active', 'label' => 'Đang hoạt động'],
        ['key' => 'inactive', 'label' => 'Đã vô hiệu hóa'],
        ['key' => 'suspended', 'label' => 'Tạm ngưng'],
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
                        <th>Email</th>
                        <th>Trạng thái</th>
                        <th class="text-end" style="width:12rem"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="cell-primary">{{ $user->name }}</div>
                            <div class="text-muted small">#{{ $user->id }}</div>
                        </td>
                        <td class="cell-muted">{{ $user->phone ?: '—' }}</td>
                        <td class="cell-muted">{{ $user->emailForForm() ?: '—' }}</td>
                        <td>
                            @php
                                $pill = match ($user->status) {
                                    'active' => ['accent', 'Hoạt động'],
                                    'suspended' => ['warning', 'Tạm ngưng'],
                                    default => ['neutral', 'Vô hiệu hóa'],
                                };
                            @endphp
                            <span class="status-pill status-pill--{{ $pill[0] }}">{{ $pill[1] }}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                @if($user->status === 'active')
                                    <form method="POST" action="{{ route('admin.users.deactivate', $user) }}"
                                          data-confirm="Vô hiệu hóa {{ $user->name }}? Khách sẽ không đăng nhập được."
                                          data-confirm-title="Vô hiệu hóa tài khoản"
                                          data-confirm-variant="danger"
                                          data-confirm-ok="Vô hiệu hóa">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Vô hiệu hóa</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.activate', $user) }}"
                                          data-confirm="Kích hoạt lại {{ $user->name }}?"
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
                        <td colspan="5" class="text-center text-muted py-4">Không có khách hàng phù hợp.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="mt-3">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
