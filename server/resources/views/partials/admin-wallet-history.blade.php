@php
/** @var \Illuminate\Support\Collection<int, array<string, mixed>> $walletHistory */
$walletHistory = $walletHistory ?? collect();
@endphp

<div class="console-panel-head px-0 pt-4 mt-2">
    <div class="console-panel-head-accent">
        <h2>Lịch sử nạp ví</h2>
    </div>
</div>

@if($walletHistory->isEmpty())
    <p class="text-muted mb-0">Chưa có giao dịch nào.</p>
@else
    <div class="console-table-wrap">
        <table class="console-table">
            <thead>
                <tr>
                    <th>SĐT</th>
                    <th>Vai trò</th>
                    <th>Mã / ghi chú</th>
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
                        {{ $item['phone'] ?? '—' }}
                        @if(! empty($item['display_name']) && ($item['display_name'] ?? '') !== '—')
                            <div class="cell-muted small">{{ $item['display_name'] }}</div>
                        @endif
                    </td>
                    <td class="cell-muted">{{ $item['role_label'] ?? '—' }}</td>
                    <td>
                        @if(! empty($item['reference']))
                            <div class="cell-primary fw-semibold">{{ $item['reference'] }}</div>
                        @endif
                        @if(! empty($item['meta']))
                            <div class="cell-muted small">{{ $item['meta'] }}</div>
                        @endif
                    </td>
                    <td class="fw-semibold text-success">
                        +{{ number_format($item['amount'], 0, ',', '.') }} đ
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
                    <td class="cell-muted small">{{ $item['at']?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>
                        @php
                            $statusVariant = \App\Support\StatusBadge::depositStatus($item['status'] ?? null);
                            $statusLabel = \App\Models\DriverWalletTransaction::statusLabelFor($item['status'] ?? null);
                        @endphp
                        <span class="status-pill status-pill--{{ $statusVariant }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $walletHistory])
@endif
