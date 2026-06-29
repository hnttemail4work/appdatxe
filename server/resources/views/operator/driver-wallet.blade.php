@extends('layouts.console')

@section('console')
@php
    $walletTabs = [
        [
            'key' => 'deposits',
            'label' => 'Nạp ví',
            'badge' => $depositsPending->total(),
            'hot' => $depositsPending->total() > 0,
        ],
        [
            'key' => 'settlements',
            'label' => 'Kết chuyến',
            'badge' => $awaitingCode->total(),
            'hot' => $awaitingCode->total() > 0,
        ],
    ];

    if ($codesIssued->total() > 0) {
        $walletTabs[] = [
            'key' => 'issued',
            'label' => 'Mã đã cấp',
            'badge' => $codesIssued->total(),
        ];
    }

    $walletDefaultTab = $defaultTab ?? 'deposits';
@endphp

@include('partials.console-hero', [
    'title' => 'Xử lý yêu cầu',
    'subtitle' => 'Duyệt nạp ví và cấp mã kết chuyến cho tài xế.',
    'backHref' => route('operator.dashboard'),
    'backLabel' => 'Trang quản lý',
])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.operator-nav-tabs', ['active' => 'wallet'])

        @if(($counts['total'] ?? 0) > 0)
        <div class="console-alert info mb-3">
            @if(($counts['deposits'] ?? 0) > 0)
                <strong>{{ $counts['deposits'] }}</strong> yêu cầu nạp ví chờ duyệt.
            @endif
            @if(($counts['deposits'] ?? 0) > 0 && ($counts['settlements'] ?? 0) > 0)
                
            @endif
            @if(($counts['settlements'] ?? 0) > 0)
                <strong>{{ $counts['settlements'] }}</strong> chuyến chờ cấp mã kết.
            @endif
        </div>
        @endif

        @include('partials.screen-tabs-start', [
            'prefix' => 'op-wallet',
            'activeKey' => $walletDefaultTab,
            'tabs' => $walletTabs,
        ])

        @include('partials.screen-tab-pane', ['prefix' => 'op-wallet', 'key' => 'deposits', 'active' => $walletDefaultTab === 'deposits'])
        @include('partials.operator-wallet-deposit-list', ['depositsPending' => $depositsPending])
        @include('partials.operator-wallet-history', ['walletHistory' => $walletHistory ?? collect()])
        @include('partials.screen-tab-pane-end')

        @include('partials.screen-tab-pane', ['prefix' => 'op-wallet', 'key' => 'settlements', 'active' => $walletDefaultTab === 'settlements'])
        <div class="console-panel-head px-0 pt-0">
            <div class="console-panel-head-accent">
                <h2>Chờ cấp mã kết chuyến</h2>
                <p class="subtitle mb-0">Tài xế đã chuyển phí nền tảng — xác nhận và cấp mã kết chuyến.</p>
            </div>
        </div>
        @if($awaitingCode->isEmpty())
            <p class="text-muted mb-0">Không có chuyến chờ cấp mã.</p>
        @else
            @foreach($awaitingCode as $s)
            @php
                $driver = $s->wallet->driverProfile->user;
                $schedule = $s->schedule ?? $s->booking?->schedule;
                $passengers = $s->scheduleBookings();
            @endphp
            <div class="border rounded-3 p-3 mb-3">
                <strong>{{ $driver->name }}</strong>
                <span class="status-pill status-pill--neutral ms-1">{{ $s->categoryLabel() }}</span>
                @if($passengers->count() > 1)
                    <span class="status-pill status-pill--info ms-1">{{ $passengers->count() }} vé</span>
                @endif
                <br>
                <span class="text-muted small">SĐT tài xế: <strong>{{ $driver->phone ?? '—' }}</strong>
                    @if($schedule)
                        <span class="ms-1">{{ $schedule->route->departure }} → {{ $schedule->route->destination }}</span>
                        <span class="ms-1">{{ $schedule->departure_time->format('d/m/Y H:i') }}</span>
                    @endif
                </span>
                <br>
                <span class="small">Phí nền tảng: <strong class="text-primary">{{ number_format($s->platform_fee_amount, 0, ',', '.') }} đ</strong>
                    <span class="ms-1">Doanh thu chuyến: {{ number_format($s->revenue_amount, 0, ',', '.') }} đ</span></span>
                @if($s->driverConfirmedTransfer())
                <br><span class="small text-success">
                    @if($s->isUnderThreshold())
                        Tài xế đã xác nhận chuyển phí
                    @else
                        Đã xác nhận CK: <code>{{ $s->transfer_ref }}</code>
                    @endif
                </span>
                @endif
                @if($passengers->isNotEmpty())
                <ul class="small mb-0 mt-2 ps-3">
                    @foreach($passengers as $b)
                    <li>{{ $b->passenger_name ?: 'HK' }}, {{ $b->passengerProfileDetail() }}, {{ $b->contact_phone }}, {{ number_format($b->total_price, 0, ',', '.') }} đ</li>
                    @endforeach
                </ul>
                @endif
                @if($s->isUnderThreshold() && $s->driverConfirmedTransfer())
                <form method="POST" action="{{ route('operator.settlements.approve', $s) }}" class="mt-2">
                    @csrf
                    <button class="btn btn-sm btn-primary">Xác nhận</button>
                </form>
                @elseif($s->transfer_ref && ! $s->isUnderThreshold())
                <form method="POST" action="{{ route('operator.settlements.issueCode', $s) }}" class="mt-2">
                    @csrf
                    <button class="btn btn-sm btn-primary">Cấp mã kết chuyến (1 ngày)</button>
                </form>
                @elseif(! $s->transfer_ref)
                <p class="small text-muted mb-0 mt-2">Chờ tài xế xác nhận chuyển phí.</p>
                @endif
            </div>
            @endforeach
            @include('partials.pagination', ['paginator' => $awaitingCode])
        @endif
        @include('partials.screen-tab-pane-end')

        @if($codesIssued->total() > 0)
        @include('partials.screen-tab-pane', ['prefix' => 'op-wallet', 'key' => 'issued', 'active' => $walletDefaultTab === 'issued'])
        <div class="console-panel-head px-0 pt-0">
            <div class="console-panel-head-accent">
                <h2>Mã đã cấp — chờ tài xế nhập</h2>
            </div>
        </div>
        @foreach($codesIssued as $s)
        @php
            $schedule = $s->schedule ?? $s->booking?->schedule;
            $passengers = $s->scheduleBookings();
        @endphp
        <div class="border rounded-3 p-3 mb-3 bg-light">
            <strong>{{ $s->wallet->driverProfile->user->name }}</strong>
            Mã: <code class="fs-6">{{ $s->settlement_code }}</code>
            @if($s->settlement_code_expires_at)
                <span class="small text-muted ms-1">Hết hạn {{ $s->settlement_code_expires_at->format('d/m/Y H:i') }}</span>
            @endif
            <br>
            @if($schedule)
            <span class="small text-muted">{{ $schedule->route->departure }} → {{ $schedule->route->destination }}</span>
            @endif
            @if($passengers->isNotEmpty())
            <ul class="small mb-0 mt-1 ps-3">
                @foreach($passengers as $b)
                <li>{{ $b->passenger_name ?: 'HK' }}, {{ $b->passengerProfileDetail() }}, {{ $b->contact_phone }}</li>
                @endforeach
            </ul>
            @endif
        </div>
        @endforeach
        @include('partials.pagination', ['paginator' => $codesIssued])
        @include('partials.screen-tab-pane-end')
        @endif

        @include('partials.screen-tabs-end')
    </div>
</div>
@endsection
