@php
/** @var \Illuminate\Support\Collection<int, \App\Models\DriverWalletTransaction> $depositsPending */
@endphp

<div class="console-panel-head px-0 pt-0">
    <div class="console-panel-head-accent">
        <h2>Nạp tiền ví chờ duyệt</h2>
        <p class="subtitle mb-0">Tài xế đã chuyển khoản — xác nhận để cộng tiền vào ví.</p>
    </div>
</div>

@if($depositsPending->isEmpty())
    <p class="text-muted mb-0">Không có yêu cầu nạp ví.</p>
@else
    @foreach($depositsPending as $tx)
    @php
        $driverProfile = $tx->wallet->driverProfile;
        $driverUser = $driverProfile->user;
    @endphp
    <div class="border rounded-3 p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <strong>{{ $driverUser->name }}</strong>
                @if($driverProfile->driver_code)
                    <span class="driver-meta-code ms-1">{{ $driverProfile->driver_code }}</span>
                @endif
                <br>
                <span class="small text-muted">
                    SĐT: <strong>{{ $driverUser->phone ?? '—' }}</strong>
                    Gửi lúc {{ $tx->created_at->format('d/m/Y H:i') }}
                </span>
            </div>
            <div class="text-md-end">
                <div class="fs-5 fw-bold text-primary">{{ number_format($tx->amount, 0, ',', '.') }} đ</div>
            </div>
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.walletTransactions.approve', $tx) }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-success">Xác nhận &amp; cộng ví</button>
            </form>
            <a href="{{ route('admin.drivers.edit', $driverProfile) }}" class="btn btn-sm btn-outline-primary">Hồ sơ tài xế</a>
        </div>
    </div>
    @endforeach
    @include('partials.pagination', ['paginator' => $depositsPending])
@endif
