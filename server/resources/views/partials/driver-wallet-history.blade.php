@php
/** @var \Illuminate\Support\Collection<int, array{kind: string, amount: int, at: \Carbon\Carbon, label: string, meta: string|null, status: string|null}> $walletHistory */
$walletHistory = $walletHistory ?? collect();
$historyTitle = $historyTitle ?? 'Lịch sử giao dịch';
$historyEmpty = $historyEmpty ?? 'Chưa có giao dịch ví.';
@endphp

<div class="driver-wallet-history">
    <div class="driver-section-head mb-2">
        <h2 class="mb-0">{{ $historyTitle }}</h2>
    </div>
    @if($walletHistory->isEmpty())
        <div class="driver-empty py-4">
            <p class="text-muted small mb-0">{{ $historyEmpty }}</p>
        </div>
    @else
        <div class="driver-wallet-history-list">
            @foreach($walletHistory as $item)
            @php
                $historyItemClass = 'driver-wallet-history-item driver-wallet-history-item--' . $item['kind'];
                if ($item['kind'] === 'deposit' && ($item['status'] ?? null) === 'rejected') {
                    $historyItemClass .= ' is-rejected';
                }
            @endphp
            <div class="{{ $historyItemClass }}">
                <div class="driver-wallet-history-main">
                    @if($item['kind'] !== 'deposit' && filled($item['label'] ?? null))
                    <div class="driver-wallet-history-label">{{ $item['label'] }}</div>
                    @endif
                    <div class="driver-wallet-history-amount">
                        @if($item['kind'] === 'deposit')
                            @php
                                $depositStatus = $item['status'] ?? null;
                                $depositAmount = \App\Support\Money::vnd($item['amount']);
                            @endphp
                            @if($depositStatus === 'approved')
                                +{{ $depositAmount }}
                            @elseif($depositStatus === 'rejected')
                                −{{ $depositAmount }}
                            @else
                                {{ $depositAmount }}
                            @endif
                        @else
                            −{{ \App\Support\Money::vnd($item['amount']) }}
                        @endif
                    </div>
                    <div class="driver-wallet-history-meta">
                        {{ $item['at']->format('d/m/Y H:i') }}
                    </div>
                </div>
                @if($item['kind'] === 'deposit')
                    @php
                        $statusVariant = \App\Support\StatusBadge::depositStatus($item['status']);
                        $statusLabel = \App\Models\DriverWalletTransaction::statusLabelFor($item['status']);
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
