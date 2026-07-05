@php
/** @var \Illuminate\Support\Collection<int, array{kind: string, amount: int, at: \Carbon\Carbon, label: string, meta: string|null, status: string|null, driver_name: string, driver_code: string|null}> $walletHistory */
$walletHistory = $walletHistory ?? collect();
@endphp

<div class="console-panel-head px-0 pt-4 mt-2">
    <div class="console-panel-head-accent">
        <h2>Lịch sử giao dịch</h2>
    </div>
</div>

@if($walletHistory->isEmpty())
    <p class="text-muted mb-0">Chưa có giao dịch nào.</p>
@else
    <div class="console-table-wrap">
        <table class="console-table">
            <thead>
                <tr>
                    <th>Tài xế</th>
                    <th>Loại</th>
                    <th>Số tiền</th>
                    <th>Ảnh CK</th>
                    <th>Thời gian</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @foreach($walletHistory as $item)
                <tr>
                    <td class="cell-primary">
                        {{ $item['driver_name'] }}
                        @if($item['driver_code'])
                            <div class="cell-muted small">{{ $item['driver_code'] }}</div>
                        @endif
                    </td>
                    <td>
                        @if(! empty($item['reference']))
                            <div class="cell-primary fw-semibold">{{ $item['reference'] }}</div>
                        @endif
                        @if($item['meta'])
                            <div class="cell-muted small">{{ $item['meta'] }}</div>
                        @endif
                    </td>
                    <td class="fw-semibold {{ $item['kind'] === 'deposit' ? 'text-success' : 'text-primary' }}">
                        @if($item['kind'] === 'deposit')
                            +{{ number_format($item['amount'], 0, ',', '.') }} đ
                        @else
                            −{{ number_format($item['amount'], 0, ',', '.') }} đ
                        @endif
                    </td>
                    <td>
                        @if(! empty($item['proof_image_url']))
                            <a href="{{ $item['proof_image_url'] }}"
                               class="wallet-deposit-proof-thumb"
                               target="_blank"
                               rel="noopener"
                               title="Xem ảnh chuyển khoản">
                                <img src="{{ $item['proof_image_url'] }}"
                                     alt="Ảnh CK"
                                     width="56"
                                     height="56"
                                     loading="lazy">
                            </a>
                        @else
                            <span class="cell-muted small">—</span>
                        @endif
                    </td>
                    <td class="cell-muted small">{{ $item['at']->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($item['kind'] === 'deposit')
                            @php
                                $statusVariant = \App\Support\StatusBadge::depositStatus($item['status']);
                                $statusLabel = \App\Models\DriverWalletTransaction::statusLabelFor($item['status']);
                            @endphp
                            <span class="status-pill status-pill--{{ $statusVariant }}">{{ $statusLabel }}</span>
                        @else
                            <span class="status-pill status-pill--success">Đã xác nhận</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $walletHistory])
@endif
