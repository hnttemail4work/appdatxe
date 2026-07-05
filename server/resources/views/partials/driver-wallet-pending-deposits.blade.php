@php
/** @var \Illuminate\Support\Collection<int, \App\Models\DriverWalletTransaction> $pendingDeposits */
$pendingDeposits = $pendingDeposits->sortByDesc('created_at')->values();
@endphp

@if($pendingDeposits->isNotEmpty())
<div class="driver-wallet-pending-section mb-3">
    <div class="driver-wallet-section-head">
        <h3 class="driver-wallet-section-title">Yêu cầu chờ duyệt</h3>
        <span class="driver-wallet-pending-badge">{{ $pendingDeposits->count() }}</span>
    </div>
    <ul class="driver-wallet-pending-list">
        @foreach($pendingDeposits as $tx)
        <li class="driver-wallet-pending-item">
            <div class="driver-wallet-pending-item-main">
                <span class="driver-wallet-pending-ref">{{ $tx->depositReference() }}</span>
                <span class="driver-wallet-pending-amount">{{ number_format($tx->amount, 0, ',', '.') }} đ</span>
            </div>
            <div class="driver-wallet-pending-item-meta">
                <span>Gửi {{ $tx->created_at->format('d/m/Y H:i') }}</span>
                <span class="status-pill status-pill--pending">Chờ duyệt</span>
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif
