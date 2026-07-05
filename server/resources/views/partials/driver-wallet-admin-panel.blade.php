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
                <div class="text-muted small">Kích hoạt tài khoản</div>
                @if($driverWallet->wallet_activated_at)
                    <span class="status-pill status-pill--success">Đã kích hoạt</span>
                    @if($driverWallet->wallet_gate_enabled)
                        <div class="small text-muted mt-1">Giữ số dư trên {{ DriverWalletConfig::minBalanceFormatted() }} để nhận cuốc</div>
                    @else
                        <div class="small text-muted mt-1">Chưa đạt ngưỡng doanh thu {{ DriverWalletConfig::revenueThresholdShortLabel() }}</div>
                    @endif
                @else
                    <span class="status-pill status-pill--warning">Chờ nạp ví</span>
                    <div class="small text-muted mt-1">Tối thiểu {{ DriverWalletConfig::minDepositFormatted() }} để kích hoạt</div>
                @endif
            </div>
        </div>

        @if(! $driverWallet->wallet_activated_at)
            <div class="console-alert warning mb-3">
                Tài xế cần nạp ví tối thiểu {{ DriverWalletConfig::minDepositFormatted() }} để kích hoạt và nhận cuốc.
            </div>
        @elseif($driverWallet->wallet_gate_enabled && ! $driverWallet->hasMinBalance())
            <div class="console-alert warning mb-3">
                Tài xế cần nạp ví trên {{ DriverWalletConfig::minBalanceFormatted() }} để nhận cuốc mới.
            </div>
        @endif

        @if($pendingDeposits->isNotEmpty())
            <div class="console-alert info mb-3">
                Có {{ $pendingDeposits->total() }} yêu cầu nạp tiền chờ duyệt.
                <a href="{{ route('admin.driverWallet') }}" class="fw-semibold ms-1">Mở tab Nạp ví →</a>
            </div>
        @else
            <p class="text-muted small mb-3">
                Duyệt nạp ví tại <a href="{{ route('admin.driverWallet') }}">Nạp ví</a>.
            </p>
        @endif

        @include('partials.driver-wallet-history', ['walletHistory' => $walletHistory ?? collect()])
    </div>
</div>
