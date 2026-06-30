@extends('layouts.console')

@section('console')
@include('partials.operator-console-hero')

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.operator-nav-tabs', ['active' => 'deposits'])

        @if(($counts['deposits'] ?? 0) > 0)
        <div class="console-alert info mb-3 mt-3">
            <strong>{{ $counts['deposits'] }}</strong> yêu cầu nạp ví chờ duyệt.
        </div>
        @endif

        <div class="pt-3">
            @include('partials.operator-wallet-deposit-list', ['depositsPending' => $depositsPending])
            @include('partials.operator-wallet-history', ['walletHistory' => $walletHistory ?? collect()])
        </div>
    </div>
</div>
@endsection
