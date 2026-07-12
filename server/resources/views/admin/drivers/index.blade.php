@extends('layouts.console')

@section('console')
@php
$drivers = $drivers ?? collect();
$filter = $filter ?? 'all';
$pendingCount = $pendingCount ?? 0;
$statsMonth = $statsMonth ?? now()->startOfMonth();
$driverMonthlyStats = $driverMonthlyStats ?? [];
$driverTabs = [
    ['key' => 'all', 'label' => 'Danh sách', 'href' => route('admin.drivers')],
    ['key' => 'pending', 'label' => 'Chờ duyệt', 'href' => route('admin.drivers', ['filter' => 'pending']), 'badge' => $pendingCount, 'hot' => $pendingCount > 0],
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
        <div class="driver-mgmt-panel pt-3">
    <div class="console-panel-head driver-mgmt-head px-0 pt-0">
        <div class="console-panel-head-accent">
            <h2>Danh sách</h2>
        </div>
    </div>
    <div class="console-panel-body flush">
        <div class="console-table-wrap">
            <table class="console-table driver-mgmt-table">
                <thead>
                    <tr>
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
                        <th>Trạng thái</th>
                        <th class="text-end" style="width:11rem"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($drivers as $d)
                    @php
                        $monthStats = $driverMonthlyStats[(int) $d->user_id] ?? ['trips' => 0, 'revenue' => 0, 'cancel_rate' => 0.0];
                        $monthCancelRate = (float) ($monthStats['cancel_rate'] ?? 0.0);
                    @endphp
                    <tr class="{{ $d->isPendingApproval() ? 'driver-row-pending' : '' }}">
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
                        <td>
                            <span class="status-pill status-pill--{{ $d->displayStatusColor() }}">{{ $d->displayStatusLabel() }}</span>
                            @if($d->missedTripStrikeLabel() && ! $d->isMissedTripLocked() && ! $d->isPendingApproval() && ! $d->isRejected())
                                <div class="mt-1"><span class="status-pill status-pill--pending">{{ $d->missedTripStrikeLabel() }}</span></div>
                            @endif
                            @if($d->hasRejectionNote())
                                <div class="cell-muted small mt-1 text-danger">{{ \Illuminate\Support\Str::limit($d->rejection_reason, 80) }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a href="{{ route('admin.drivers.edit', $d) }}" class="btn btn-sm {{ $d->isPendingApproval() ? 'btn-primary' : 'btn-outline-primary' }}">
                                    Xem hồ sơ
                                </a>
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
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/driver-approval-actions.js') }}"></script>
@endpush
