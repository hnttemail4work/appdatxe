@extends('layouts.console')

@section('console')
@php
$drivers = $drivers ?? collect();
$filter = $filter ?? 'all';
$pendingCount = $pendingCount ?? 0;
$statsMonth = $statsMonth ?? now()->startOfMonth();
$driverMonthlyStats = $driverMonthlyStats ?? [];
$showBulkDelete = $filter === 'rejected' && auth()->user()?->role === 'admin';
$driverTabs = [
    ['key' => 'all', 'label' => 'Danh sách', 'href' => route('admin.drivers')],
    ['key' => 'pending', 'label' => 'Chờ duyệt', 'href' => route('admin.drivers', ['filter' => 'pending'])],
    ['key' => 'rejected', 'label' => 'Đã từ chối', 'href' => route('admin.drivers', ['filter' => 'rejected'])],
];
@endphp

@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'drivers', 'driversSubpage' => true])

        <div class="screen-tabs-wrap mb-3">
            <ul class="nav nav-tabs screen-tabs">
                @foreach($driverTabs as $tab)
                <li class="nav-item">
                    <a href="{{ $tab['href'] }}" class="nav-link {{ $filter === $tab['key'] ? 'active' : '' }}">
                        {{ $tab['label'] }}
                        @if(! empty($tab['badge']))
                            <span class="status-pill status-pill--{{ ! empty($tab['hot']) ? 'accent' : 'neutral' }} ms-1">{{ $tab['badge'] }}</span>
                        @endif
                    </a>
                </li>
                @endforeach
            </ul>
        </div>

@if($drivers->isEmpty())
        <div class="console-empty py-5">
            <p class="mb-0">
                @if($filter === 'pending')
                    Không có hồ sơ chờ duyệt.
                @elseif($filter === 'rejected')
                    Chưa có hồ sơ bị từ chối.
                @else
                    Chưa có tài xế.
                @endif
            </p>
        </div>
@else
        @if($filter === 'all')
        <form method="GET" action="{{ route('admin.drivers') }}" class="driver-mgmt-month-filter mb-3">
            <input type="month"
                   id="driver-stats-month"
                   name="month"
                   class="form-control form-control-sm"
                   style="max-width: 11rem;"
                   value="{{ $statsMonth->format('Y-m') }}"
                   onchange="this.form.submit()"
                   aria-label="Chọn tháng">
        </form>
        @endif
        <div class="driver-mgmt-panel pt-3" @if($showBulkDelete) data-bulk-root data-bulk-form="driver-bulk-delete" data-bulk-btn="driver-bulk-delete-btn" data-bulk-item=".driver-bulk-select" @endif>
    <div class="console-panel-head driver-mgmt-head px-0 pt-0">
        <div class="console-panel-head-accent">
            <h2>Danh sách</h2>
        </div>
        @if($showBulkDelete)
        <div class="d-flex justify-content-end mb-2">
            <button type="submit"
                    form="driver-bulk-delete"
                    id="driver-bulk-delete-btn"
                    class="btn btn-outline-danger btn-sm"
                    disabled>
                Xóa hồ sơ đã chọn
            </button>
        </div>
        @error('ids')
            <div class="alert alert-danger py-2 small">{{ $message }}</div>
        @enderror
        <form id="driver-bulk-delete"
              method="POST"
              action="{{ route('admin.drivers.bulkDestroy') }}"
              data-confirm="Xóa các hồ sơ tài xế đã từ chối đã chọn? Không hoàn tác."
              data-confirm-title="Xóa hồ sơ từ chối"
              data-confirm-variant="danger"
              data-confirm-ok="Xóa">
            @csrf
            @method('DELETE')
        </form>
        @endif
    </div>
    <div class="console-panel-body flush">
        <div class="console-table-wrap">
            <table class="console-table driver-mgmt-table">
                <thead>
                    <tr>
                        @if($showBulkDelete)
                        <th class="col-check" scope="col">
                            <input type="checkbox"
                                   class="form-check-input"
                                   data-bulk-select-all
                                   aria-label="Chọn tất cả trên trang này">
                        </th>
                        @endif
                        @if($filter === 'all')
                            <th>Mã TX</th>
                        @else
                            <th>Tài xế</th>
                        @endif
                        <th>Số điện thoại</th>
                        @if($filter !== 'all')
                            <th>Xe</th>
                        @endif
                        @if($filter === 'all')
                            <th>Số chuyến</th>
                            <th>Doanh thu</th>
                        @endif
                        <th>Thích</th>
                        <th>Không thích</th>
                        <th>% Hủy cuốc</th>
                        <th>Ví</th>
                        <th>Yêu cầu</th>
                        <th>Trạng thái</th>
                        <th>App</th>
                        <th class="text-end" style="width:11rem">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($drivers as $d)
                    @php
                        $monthStats = $driverMonthlyStats[(int) $d->user_id] ?? ['trips' => 0, 'revenue' => 0, 'cancel_rate' => 0.0];
                        $monthCancelRate = (float) ($monthStats['cancel_rate'] ?? 0.0);
                        $pendingChange = $d->pendingChangeRequest;
                        $requestCount = $pendingChange ? 1 : 0;
                        $pendingChangeId = $pendingChange?->id;
                    @endphp
                    <tr class="{{ $d->isPendingApproval() ? 'driver-row-pending' : '' }}{{ $requestCount > 0 ? ' driver-row-has-request driver-row-request-unread' : '' }}"
                        @if($pendingChangeId) data-pending-change-id="{{ $pendingChangeId }}" @endif>
                        @if($showBulkDelete)
                        <td class="col-check">
                            <input type="checkbox"
                                   class="form-check-input driver-bulk-select"
                                   name="ids[]"
                                   value="{{ $d->id }}"
                                   form="driver-bulk-delete"
                                   aria-label="Chọn tài xế #{{ $d->id }}">
                        </td>
                        @endif
                        <td>
                            @if($filter === 'all')
                                @if($d->driver_code)
                                    <span class="driver-meta-code">{{ $d->driver_code }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            @else
                            <div class="driver-mgmt-name">
                                @php $portraitUrl = $d->photoUrl('photo_portrait'); @endphp
                                @if($portraitUrl)
                                    <img src="{{ $portraitUrl }}" alt=""
                                         class="driver-mgmt-avatar rounded-circle object-fit-cover border">
                                @else
                                    <div class="driver-mgmt-avatar driver-mgmt-avatar-fallback">
                                        {{ mb_substr($d->user->name, 0, 1) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="cell-primary">{{ $d->user->name }}</div>
                                    @if($d->driver_code)
                                        <div class="driver-meta-code">{{ $d->driver_code }}</div>
                                    @endif
                                    @if($filter === 'rejected' && $d->rejection_reason)
                                        <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($d->rejection_reason, 80) }}</div>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </td>
                        <td class="cell-muted">{{ $d->user->phone ?? '—' }}</td>
                        @if($filter !== 'all')
                        <td class="cell-muted">
                            @if($d->vehicle_license_plate)
                                <strong class="text-white">{{ $d->vehicle_license_plate }}</strong>
                                @if($d->vehicle_type)
                                    <span class="driver-meta-sub ms-1">{{ ucfirst($d->vehicle_type) }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        @endif
                        @if($filter === 'all')
                        <td class="fw-semibold">{{ number_format($monthStats['trips']) }}</td>
                        <td class="fw-semibold">{{ number_format($monthStats['revenue'], 0, ',', '.') }} đ</td>
                        @endif
                        <td class="fw-semibold text-success">{{ number_format($d->preference_likes) }}</td>
                        <td class="fw-semibold text-danger">{{ number_format($d->preference_dislikes) }}</td>
                        <td>
                            <div class="driver-mgmt-cancel-rate">
                                @if($filter === 'all')
                                    <span class="fw-semibold {{ $monthCancelRate > 0 ? 'text-warning' : 'text-muted' }}">
                                        {{ number_format($monthCancelRate, 1, ',', '.') }}%
                                    </span>
                                @else
                                    <span class="fw-semibold {{ $d->hasCancelRate() ? 'text-warning' : 'text-muted' }}">{{ $d->cancelRateLabel() }}</span>
                                    @if($d->hasCancelRate() && ! $d->isPendingApproval())
                                        <form method="POST"
                                              action="{{ route('admin.drivers.resetCancelRate', $d) }}"
                                              class="driver-mgmt-cancel-rate-reset"
                                              data-confirm="Đặt lại tỷ lệ hủy cuốc của {{ $d->user->name }} về 0%?"
                                              data-confirm-title="Reset tỷ lệ hủy cuốc"
                                              data-confirm-ok="Đặt về 0%"
                                              data-confirm-variant="warning">
                                            @csrf
                                            <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Về 0%</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($d->walletListLabel() === '—')
                                <span class="text-muted">—</span>
                            @else
                                <span class="status-pill status-pill--{{ $d->walletListColor() }}">{{ $d->walletListLabel() }}</span>
                            @endif
                        </td>
                        <td class="fw-semibold {{ $requestCount > 0 ? 'text-warning driver-mgmt-request-count' : 'text-muted' }}">
                            {{ $requestCount > 0 ? $requestCount : '—' }}
                        </td>
                        <td>
                            <span class="status-pill status-pill--{{ $d->listStatusColor() }}">{{ $d->listStatusLabel() }}</span>
                        </td>
                        <td>
                            @if($d->isApproved() && $d->isAccountRunning())
                                <span class="status-pill status-pill--{{ $d->appOnColor() }}">{{ $d->appOnLabel() }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a href="{{ route('admin.drivers.edit', $requestCount > 0
                                        ? ['driverProfile' => $d, 'tab' => 'requests']
                                        : $d) }}"
                                   class="btn btn-sm {{ ($d->isPendingApproval() || $requestCount > 0) ? 'btn-primary' : 'btn-outline-primary' }}">
                                    Xem
                                </a>
                                @if($d->isApproved())
                                    @include('partials.admin-account-status-actions', [
                                        'layout' => 'inline',
                                        'entityLabel' => 'tài xế',
                                        'isRunning' => $d->isAccountRunning(),
                                        'suspendAction' => route('admin.drivers.destroy', $d),
                                        'resumeAction' => route('admin.drivers.activate', $d),
                                        'suspendMethod' => 'DELETE',
                                    ])
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $drivers])
    </div>
        </div>
@endif
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}?v={{ filemtime(public_path('css/driver-mgmt.css')) }}">
@endpush

@push('scripts')
@if($showBulkDelete)
<script src="{{ asset('js/admin-bulk-select.js') }}?v={{ filemtime(public_path('js/admin-bulk-select.js')) }}"></script>
@endif
<script src="{{ asset('js/admin-driver-request-seen.js') }}?v={{ filemtime(public_path('js/admin-driver-request-seen.js')) }}"></script>
@endpush
