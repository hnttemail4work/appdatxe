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
                    <span class="status-pill status-pill--success">Đã kích hoạt</span>
                    <div class="small text-muted mt-1">Giữ trên {{ number_format(DriverWalletConfig::MIN_BALANCE, 0, ',', '.') }} đ</div>
                @else
                    <span class="status-pill status-pill--neutral">Chưa kích hoạt</span>
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
            <div class="console-alert info mb-3">
                Có {{ $pendingDeposits->total() }} yêu cầu nạp tiền chờ duyệt.
                <a href="{{ route('operator.driverWallet', ['tab' => 'deposits']) }}" class="fw-semibold ms-1">Xử lý tại mục Xử lý yêu cầu →</a>
            </div>
        @else
            <p class="text-muted small mb-3">Duyệt nạp ví và cấp mã kết chuyến tại <a href="{{ route('operator.driverWallet') }}">Xử lý yêu cầu</a>.</p>
        @endif

        @include('partials.driver-wallet-history', ['walletHistory' => $walletHistory ?? collect()])
    </div>
</div>
