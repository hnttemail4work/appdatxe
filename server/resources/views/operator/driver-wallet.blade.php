@extends('layouts.console')



@section('console')

@php

    $walletTabs = [

        ['key' => 'awaiting', 'label' => 'Chờ cấp mã', 'badge' => $awaitingCode->count(), 'hot' => $awaitingCode->isNotEmpty()],

    ];

    if (($codesIssued ?? collect())->isNotEmpty()) {

        $walletTabs[] = ['key' => 'issued', 'label' => 'Mã đã cấp', 'badge' => $codesIssued->count()];

    }

    $walletTabs[] = ['key' => 'deposits', 'label' => 'Nạp ví', 'badge' => $depositsPending->count(), 'hot' => $depositsPending->isNotEmpty()];

    $walletDefaultTab = $awaitingCode->isNotEmpty() ? 'awaiting'

        : ($depositsPending->isNotEmpty() ? 'deposits' : 'awaiting');

@endphp



<div class="console-panel">

    <div class="console-panel-body">

        @include('partials.operator-nav-tabs', ['active' => 'wallet'])

        @include('partials.screen-tabs-start', [

            'prefix' => 'op-wallet',

            'activeKey' => $walletDefaultTab,

            'tabs' => $walletTabs,

        ])



        @include('partials.screen-tab-pane', ['prefix' => 'op-wallet', 'key' => 'awaiting', 'active' => $walletDefaultTab === 'awaiting'])

        <div class="console-panel-head px-0 pt-0">

            <div class="console-panel-head-accent">

                <h2>Chờ cấp mã kết chuyến</h2>

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

                <span class="badge bg-secondary ms-1">{{ $s->categoryLabel() }}</span>

                @if($passengers->count() > 1)

                    <span class="badge bg-light text-dark ms-1">{{ $passengers->count() }} vé</span>

                @endif

                <br>

                <span class="text-muted small">SĐT tài xế: <strong>{{ $driver->phone ?? '—' }}</strong>

                    @if($schedule)

                        · {{ $schedule->route->departure }} → {{ $schedule->route->destination }}

                        · {{ $schedule->departure_time->format('d/m/Y H:i') }}

                    @endif

                </span>

                <br>

                <span class="small">Phí nền tảng: <strong class="text-primary">{{ number_format($s->platform_fee_amount, 0, ',', '.') }} đ</strong>

                    · Doanh thu chuyến: {{ number_format($s->revenue_amount, 0, ',', '.') }} đ</span>

                @if($s->transfer_ref)

                <br><span class="small text-success">Đã xác nhận CK: <code>{{ $s->transfer_ref }}</code></span>

                @endif

                @if($passengers->isNotEmpty())

                <ul class="small mb-0 mt-2 ps-3">

                    @foreach($passengers as $b)

                    <li>{{ $b->passenger_name ?: 'HK' }} · {{ $b->contact_phone }} · {{ number_format($b->total_price, 0, ',', '.') }} đ</li>

                    @endforeach

                </ul>

                @endif

                @if($s->transfer_ref)
                <form method="POST" action="{{ route('operator.settlements.issueCode', $s) }}" class="mt-2">
                    @csrf
                    <button class="btn btn-sm btn-primary">Cấp mã kết chuyến (1 ngày)</button>
                </form>
                @else
                <p class="small text-muted mb-0 mt-2">Chờ tài xế xác nhận chuyển phí.</p>
                @endif

            </div>

            @endforeach

        @endif

        @include('partials.screen-tab-pane-end')



        @if(($codesIssued ?? collect())->isNotEmpty())

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

            · Mã: <code class="fs-6">{{ $s->settlement_code }}</code>

            @if($s->settlement_code_expires_at)

                <span class="small text-muted">· Hết hạn {{ $s->settlement_code_expires_at->format('d/m/Y H:i') }}</span>

            @endif

            <br>

            @if($schedule)

            <span class="small text-muted">{{ $schedule->route->departure }} → {{ $schedule->route->destination }}</span>

            @endif

            @if($passengers->isNotEmpty())

            <ul class="small mb-0 mt-1 ps-3">

                @foreach($passengers as $b)

                <li>{{ $b->passenger_name ?: 'HK' }} · {{ $b->contact_phone }}</li>

                @endforeach

            </ul>

            @endif

        </div>

        @endforeach

        @include('partials.screen-tab-pane-end')

        @endif



        @include('partials.screen-tab-pane', ['prefix' => 'op-wallet', 'key' => 'deposits', 'active' => $walletDefaultTab === 'deposits'])

        <div class="console-panel-head px-0 pt-0">

            <div class="console-panel-head-accent">

                <h2>Nạp tiền ví chờ duyệt</h2>

            </div>

        </div>

        @if($depositsPending->isEmpty())

            <p class="text-muted mb-0">Không có yêu cầu nạp ví.</p>

        @else

            @foreach($depositsPending as $tx)

            <div class="border rounded-3 p-3 mb-3">

                <strong>{{ $tx->wallet->driverProfile->user->name }}</strong>

                <br>

                <span class="small">{{ number_format($tx->amount, 0, ',', '.') }} đ · CK: <code>{{ $tx->transfer_ref }}</code></span>

                <div class="mt-2 d-flex flex-wrap gap-2">

                    <form method="POST" action="{{ route('operator.walletTransactions.approve', $tx) }}" class="d-inline">

                        @csrf

                        <button class="btn btn-sm btn-success">Xác nhận &amp; cộng ví</button>

                    </form>

                    <a href="{{ route('operator.drivers.edit', $tx->wallet->driverProfile) }}" class="btn btn-sm btn-outline-primary">Hồ sơ tài xế</a>

                </div>

            </div>

            @endforeach

        @endif

        @include('partials.screen-tab-pane-end')



        @include('partials.screen-tabs-end')

    </div>

</div>

@endsection

