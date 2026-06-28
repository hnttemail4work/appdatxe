@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver.css') }}?v={{ filemtime(public_path('css/driver.css')) }}">
@endpush

@section('content')
@php
    $pendingCount = $pendingRequests->count();
    $wallet = $driverWallet;
    $tripSchedules = $tripSchedules ?? collect();
    $tripActionCount = $tripActionCount ?? 0;
    $revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];

    $driverDefaultTab = request('tab');
    if (! in_array($driverDefaultTab, ['requests', 'trips', 'deposit'], true)) {
        $driverDefaultTab = 'requests';
    }
@endphp

<div class="driver-page">
    @if($profile)
    <div class="driver-greeting mb-2">
        Xin chào <strong>{{ $user->name }}</strong>
        @if($profile->driver_code)
            · <strong>{{ $profile->driver_code }}</strong>
        @endif
    </div>
    @endif

    @if(($profile && $profile->isMissedTripLocked()) || ($showTopUpBanner ?? false) || ($settlementBlockReason ?? null))
    <div class="driver-notice-stack">
        @if($profile && $profile->isMissedTripLocked())
            <div class="driver-notice driver-notice-danger">
                <strong>Tài khoản tạm khóa</strong> — không nhận chuyến được. Liên hệ quản lý để mở khóa.
            </div>
        @endif
        @if($showTopUpBanner ?? false)
            <div class="driver-notice driver-notice-warning">
                ⚠️ Chưa đủ điều kiện nhận cuốc,
                <a href="{{ route('driver.dashboard', ['tab' => 'deposit']) }}" class="ms-1 fw-semibold">nạp ví ngay →</a>
            </div>
        @endif
        @if($settlementBlockReason ?? null)
            <div class="driver-notice driver-notice-warning">
                {{ $settlementBlockReason }}
            </div>
        @endif
    </div>
    @endif

    @include('partials.screen-tabs-start', [
        'prefix' => 'driver-main',
        'activeKey' => $driverDefaultTab,
        'tabs' => [
            ['key' => 'requests', 'label' => 'Yêu cầu', 'badge' => $pendingCount, 'hot' => $pendingCount > 0],
            ['key' => 'trips', 'label' => 'Xem chuyến', 'badge' => $tripActionCount, 'hot' => $tripActionCount > 0],
            ['key' => 'deposit', 'label' => 'Ví'],
        ],
    ])

    @include('partials.screen-tab-pane', ['prefix' => 'driver-main', 'key' => 'requests', 'active' => $driverDefaultTab === 'requests'])
    <section class="driver-section" id="driver-section-requests">
        <div class="driver-section-head driver-section-head--tools">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="driver-refresh-btn">↻ Dò chuyến</button>
        </div>
        @if($pendingRequests->isEmpty())
            <div class="driver-empty" id="no-pending-msg">
                Không có yêu cầu.
                <span class="driver-empty-hint d-block mt-1">Chia sẻ QR đặt vé để nhận đơn.</span>
            </div>
            <div class="d-none flex-column gap-3" id="pending-requests-list"></div>
        @else
            <div class="d-flex flex-column gap-3" id="pending-requests-list">
                @foreach($pendingRequests as $req)
                    @include('partials.driver-trip-request-card', ['req' => $req, 'walletBlockReason' => $walletBlockReason ?? null])
                @endforeach
            </div>
        @endif
    </section>
    @include('partials.screen-tab-pane-end')

    @include('partials.screen-tab-pane', ['prefix' => 'driver-main', 'key' => 'trips', 'active' => $driverDefaultTab === 'trips'])
    <section class="driver-section" id="driver-section-trips">
        @if($tripSchedules->isEmpty())
            <div class="driver-empty">Chưa có chuyến trong tuần này.</div>
        @else
            <div class="d-flex flex-column gap-3">
                @foreach($tripSchedules as $schedule)
                    @include('partials.driver-schedule-card', ['schedule' => $schedule, 'showActions' => true])
                @endforeach
            </div>
        @endif
    </section>
    @include('partials.screen-tab-pane-end')

    @include('partials.screen-tab-pane', ['prefix' => 'driver-main', 'key' => 'deposit', 'active' => $driverDefaultTab === 'deposit'])
    <section class="driver-section" id="driver-section-deposit">
        @include('partials.driver-tab-deposit', ['wallet' => $wallet, 'revenueStats' => $revenueStats])
    </section>
    @include('partials.screen-tab-pane-end')

    @include('partials.screen-tabs-end')
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/driver-transfer-form.js') }}?v={{ filemtime(public_path('js/driver-transfer-form.js')) }}"></script>
<script>
(function () {
    var syncUrl = @json(route('driver.liveSync'));
    var walletBlocked = @json((bool) ($walletBlockReason ?? null));
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function renderPending(req) {
        var modeBadge = req.booking_mode_key === 'whole_car' ? 'primary' : 'info text-dark';
        var details = '';
        if (req.passenger_name || req.booking_mode) {
            details += '<div class="mb-2">';
            if (req.passenger_name) {
                details += '<strong>' + escapeHtml(req.passenger_name) + '</strong> ';
            }
            if (req.booking_mode) {
                details += '<span class="badge bg-' + modeBadge + ' ms-1">' + escapeHtml(req.booking_mode) + '</span>';
            }
            details += '</div>';
        }
        if (req.pickup) {
            details += '<div class="small">📍 Điểm đón cụ thể: <strong>' + escapeHtml(req.pickup) + '</strong></div>';
        }
        if (req.dropoff) {
            details += '<div class="small">🏁 Điểm trả cụ thể: <strong>' + escapeHtml(req.dropoff) + '</strong></div>';
        }
        if (req.seats_label) {
            details += '<div class="text-muted small mt-1">' + escapeHtml(req.seats_label) + '</div>';
        }
        if (req.notes) {
            details += '<div class="text-muted small mt-1">📝 ' + escapeHtml(req.notes) + '</div>';
        }
        if (req.trip_total) {
            details += '<div class="driver-trip-total mt-2">Tổng chuyến: <strong>' + escapeHtml(req.trip_total) + ' đ</strong></div>';
        }
        if (!details) {
            details = '<p class="text-muted small mb-0">Chưa có chi tiết hành khách.</p>';
        }
        var expireHint = req.expires_in_label
            ? '<div class="meta text-warning">Còn ' + escapeHtml(req.expires_in_label) + ' để nhận</div>'
            : '';
        var buttonsDisabled = walletBlocked ? ' disabled' : '';
        var metaLine = req.meta_label || req.departure_time || '';
        var tripCodeLine = req.trip_code
            ? '<div class="meta driver-schedule-trip-code">Mã chuyến · <code class="driver-trip-code">' + escapeHtml(req.trip_code) + '</code></div>'
            : '';
        return '<div class="driver-request-card" data-request-id="' + escapeHtml(String(req.id)) + '">' +
            '<div class="driver-card-top"><div>' +
            '<div class="route">' + escapeHtml(req.route) + '</div>' +
            '<div class="meta">' + escapeHtml(metaLine) + '</div>' + tripCodeLine + expireHint + '</div>' +
            '<div class="driver-card-top-aside text-end"><span class="badge bg-warning text-dark">Cuốc mới</span></div></div>' +
            '<div class="driver-card-body">' + details + '</div>' +
            '<div class="driver-card-actions d-flex gap-2 flex-wrap justify-content-end">' +
            '<form method="POST" action="' + escapeHtml(req.accept_url) + '">' +
            '<input type="hidden" name="_token" value="' + escapeHtml(csrf) + '">' +
            '<button class="btn btn-success btn-sm px-4"' + buttonsDisabled + '>Nhận cuốc</button></form>' +
            '<form method="POST" action="' + escapeHtml(req.reject_url) + '">' +
            '<input type="hidden" name="_token" value="' + escapeHtml(csrf) + '">' +
            '<button class="btn btn-driver-reject btn-sm px-4"' + buttonsDisabled + '>Từ chối</button></form>' +
            '</div></div>';
    }

    var refreshBtn = document.getElementById('driver-refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            window.location.reload();
        });
    }

    function poll() {
        fetch(syncUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var list = document.getElementById('pending-requests-list');
                var empty = document.getElementById('no-pending-msg');
                if (!data.pending_requests.length) {
                    if (list) {
                        list.innerHTML = '';
                        list.classList.add('d-none');
                        list.classList.remove('d-flex');
                    }
                    if (empty) empty.style.display = '';
                    return;
                }
                if (empty) empty.style.display = 'none';
                if (!list) return;
                list.classList.remove('d-none');
                list.classList.add('d-flex');
                list.innerHTML = data.pending_requests.map(renderPending).join('');
            }).catch(function () {});
    }
    poll();
    setInterval(poll, 10000);
})();
</script>
@endpush
