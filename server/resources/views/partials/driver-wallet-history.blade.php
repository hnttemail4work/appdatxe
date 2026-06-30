@php
/** @var \Illuminate\Support\Collection<int, array{kind: string, amount: int, at: \Carbon\Carbon, label: string, meta: string|null, status: string|null}> $walletHistory */
$walletHistory = $walletHistory ?? collect();
@endphp

<div class="driver-wallet-history mt-4">
    <div class="driver-section-head mb-2">
        <h2 class="mb-0">Lịch sử giao dịch</h2>
    </div>
    @if($walletHistory->isEmpty())
        <div class="driver-empty py-4">
            <p class="text-muted small mb-0">Chưa có giao dịch ví.</p>
        </div>
    @else
        <div class="driver-wallet-history-list">
            @foreach($walletHistory as $item)
            <div class="driver-wallet-history-item driver-wallet-history-item--{{ $item['kind'] }}">
                <div class="driver-wallet-history-main">
                    <div class="driver-wallet-history-label">{{ $item['label'] }}</div>
                    <div class="driver-wallet-history-amount">
                        @if($item['kind'] === 'deposit')
                            +{{ number_format($item['amount'], 0, ',', '.') }} đ
                        @else
                            −{{ number_format($item['amount'], 0, ',', '.') }} đ
                        @endif
                    </div>
                    <div class="driver-wallet-history-meta">
                            {{ $item['at']->format('d/m/Y H:i') }}
                        @if($item['meta'])
                            <span class="ms-1">{{ $item['meta'] }}</span>
                        @endif
                    </div>
                </div>
                @if($item['kind'] === 'deposit')
                    @php
                        $statusVariant = \App\Support\StatusBadge::depositStatus($item['status']);
                        $statusLabel = match ($item['status']) {
                            'approved' => 'Đã cộng ví',
                            'rejected' => 'Từ chối',
                            default => 'Chờ duyệt',
                        };
                    @endphp
                    <span class="status-pill status-pill--{{ $statusVariant }}">{{ $statusLabel }}</span>
                @else
                    <span class="status-pill status-pill--success">Đã xác nhận</span>
                @endif
            </div>
            @endforeach
        </div>
        @include('partials.pagination', ['paginator' => $walletHistory])
    @endif
</div>
