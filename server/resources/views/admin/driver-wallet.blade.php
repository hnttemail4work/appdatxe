@extends('layouts.console')

@section('console')
@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'deposits'])

        @if(session('success'))
        <div class="console-alert success mb-3 mt-3" role="status">
            {{ session('success') }}
        </div>
        @endif

        @if(($counts['deposits'] ?? 0) > 0)
        <div class="console-alert info mb-3 {{ session('success') ? '' : 'mt-3' }}">
            <strong>{{ $counts['deposits'] }}</strong> yêu cầu nạp ví chờ duyệt.
        </div>
        @endif

        <div class="pt-3">
            @include('partials.admin-wallet-deposit-list', ['depositsPending' => $depositsPending])
            @include('partials.admin-wallet-history', ['walletHistory' => $walletHistory ?? collect()])
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin-wallet-deposits.js') }}?v={{ filemtime(public_path('js/admin-wallet-deposits.js')) }}" defer></script>
@endpush
