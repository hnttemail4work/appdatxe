@extends('layouts.console')

@section('console')
@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'customer-deposits'])

        @if(session('success'))
        <div class="console-alert success mb-3 mt-3" role="status">
            {{ session('success') }}
        </div>
        @endif

        @if(($counts['deposits'] ?? 0) > 0)
        <div class="console-alert info mb-3 {{ session('success') ? '' : 'mt-3' }}">
            <strong>{{ $counts['deposits'] }}</strong> yêu cầu nạp ví khách chờ duyệt.
        </div>
        @endif

        <div class="pt-3">
            <h3 class="h6 mb-3">Chờ duyệt</h3>
            @if(($depositsPending ?? null) && $depositsPending->count())
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Khách</th>
                                <th>Số tiền</th>
                                <th>Ảnh</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($depositsPending as $tx)
                                <tr>
                                    <td>{{ $tx->depositReference() }}</td>
                                    <td>
                                        {{ $tx->wallet?->user?->preferredDisplayName() ?: '—' }}
                                        <div class="small text-muted">{{ $tx->wallet?->user?->phone }}</div>
                                    </td>
                                    <td>{{ number_format($tx->amount, 0, ',', '.') }} đ</td>
                                    <td>
                                        @if($tx->proofImageUrl())
                                            <a href="{{ $tx->proofImageUrl() }}" target="_blank" rel="noopener">Xem ảnh</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.customerWallet.approve', $tx) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary">Duyệt</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.customerWallet.reject', $tx) }}" class="d-inline"
                                              data-confirm="Từ chối yêu cầu {{ $tx->depositReference() }}?"
                                              data-confirm-title="Từ chối nạp ví"
                                              data-confirm-variant="danger"
                                              data-confirm-ok="Từ chối">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">Từ chối</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $depositsPending->withQueryString()->links() }}
            @else
                <p class="text-muted small">Không có yêu cầu chờ duyệt.</p>
            @endif

            <h3 class="h6 mb-3 mt-4">Lịch sử gần đây</h3>
            @if(($walletHistory ?? null) && $walletHistory->count())
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Khách</th>
                                <th>Số tiền</th>
                                <th>Trạng thái</th>
                                <th>Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($walletHistory as $tx)
                                <tr>
                                    <td>{{ $tx->depositReference() }}</td>
                                    <td>{{ $tx->wallet?->user?->phone ?: '—' }}</td>
                                    <td>{{ number_format($tx->amount, 0, ',', '.') }} đ</td>
                                    <td>{{ $tx->statusLabel() }}</td>
                                    <td class="small text-muted">{{ $tx->created_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $walletHistory->withQueryString()->links() }}
            @else
                <p class="text-muted small mb-0">Chưa có lịch sử.</p>
            @endif
        </div>
    </div>
</div>
@endsection
