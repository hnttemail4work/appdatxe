@php
use App\Support\DriverWalletConfig;

/** @var \App\Models\DriverProfile $driver */
/** @var \App\Models\DriverWallet $driverWallet */
$pendingDeposits = $pendingDeposits ?? collect();
@endphp

<div class="console-panel mb-4">
    <div class="console-panel-head">
        <div class="console-panel-head-accent">
            <h2>Ví tài xế</h2>
        </div>
    </div>
    <div class="console-panel-body">
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="text-muted small">Số dư ví</div>
                <div class="fs-4 fw-bold text-primary">{{ number_format($driverWallet->balance, 0, ',', '.') }} đ</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted small">Doanh thu đã kết</div>
                <div class="fw-semibold">{{ number_format($driverWallet->cumulative_revenue, 0, ',', '.') }} đ</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted small">Trạng thái ví</div>
                @if($driverWallet->wallet_gate_enabled)
                    <span class="badge bg-success">Đã kích hoạt</span>
                    <div class="small text-muted mt-1">Giữ trên {{ number_format(DriverWalletConfig::MIN_BALANCE, 0, ',', '.') }} đ</div>
                @else
                    <span class="badge bg-secondary">Chưa kích hoạt</span>
                    <div class="small text-muted mt-1">Chưa có chuyến kết ≥ 500k</div>
                @endif
            </div>
        </div>

        @if($driverWallet->wallet_gate_enabled && $driverWallet->balance <= DriverWalletConfig::MIN_BALANCE)
            <div class="console-alert warning mb-3">
                Tài xế cần nạp ví trên {{ number_format(DriverWalletConfig::MIN_BALANCE, 0, ',', '.') }} đ để nhận cuốc mới.
            </div>
        @endif

        @if($pendingDeposits->isNotEmpty())
            <h6 class="fw-semibold mb-2">Yêu cầu nạp chờ duyệt</h6>
            @foreach($pendingDeposits as $tx)
            <div class="border rounded-3 p-3 mb-3 bg-light">
                <div class="small mb-2">
                    Số tiền: <strong>{{ number_format($tx->amount, 0, ',', '.') }} đ</strong>
                    · Mã CK: <code>{{ $tx->transfer_ref }}</code>
                    · Gửi lúc {{ $tx->created_at->format('d/m/Y H:i') }}
                </div>
                <form method="POST" action="{{ route('operator.walletTransactions.approve', $tx) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-success">Xác nhận &amp; cộng vào ví</button>
                </form>
            </div>
            @endforeach
        @else
            <p class="text-muted small mb-0">Chưa có yêu cầu nạp tiền chờ duyệt.</p>
        @endif
    </div>
</div>
