@php
/** @var \Illuminate\Support\Collection<int, \App\Models\DriverWalletTransaction> $depositsPending */
$depositsPending = $depositsPending ?? collect();
$pendingTotal = (int) $depositsPending->sum('amount');
@endphp

<div class="console-panel-head px-0 pt-0">
    <div class="console-panel-head-accent">
        <h2>Nạp tiền ví chờ duyệt</h2>
    </div>
</div>

@if($depositsPending->isEmpty())
    <p class="text-muted mb-0">Không có yêu cầu nạp ví.</p>
@else
    <form id="wallet-deposit-bulk-approve"
          method="POST"
          action="{{ route('admin.walletTransactions.approveBulk') }}">
        @csrf
    </form>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="small text-muted">
            <strong>{{ $depositsPending->count() }}</strong> đơn
            · tổng <strong class="text-primary">{{ number_format($pendingTotal, 0, ',', '.') }} đ</strong>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="small text-muted wallet-deposit-bulk-summary" id="wallet-deposit-bulk-summary" hidden>
                Đã chọn <strong id="wallet-deposit-bulk-count">0</strong> đơn
                · <strong id="wallet-deposit-bulk-amount" class="text-primary">0 đ</strong>
            </span>
            <button type="submit"
                    form="wallet-deposit-bulk-approve"
                    id="wallet-deposit-bulk-btn"
                    class="btn btn-sm btn-success"
                    disabled>
                Duyệt đã chọn
            </button>
        </div>
    </div>

    <div class="console-table-wrap">
        <table class="console-table wallet-deposit-pending-table">
            <thead>
                <tr>
                    <th class="wallet-deposit-col-check">
                        <input type="checkbox"
                               class="form-check-input"
                               id="wallet-deposit-select-all"
                               aria-label="Chọn tất cả">
                    </th>
                    <th>Mã đơn</th>
                    <th>Tài xế</th>
                    <th>Số tiền</th>
                    <th>Ảnh CK</th>
                    <th>Gửi lúc</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach($depositsPending as $tx)
                @php
                    $driverProfile = $tx->wallet->driverProfile;
                    $driverUser = $driverProfile->user;
                @endphp
                <tr>
                    <td class="wallet-deposit-col-check">
                        <input type="checkbox"
                               class="form-check-input wallet-deposit-row-check"
                               name="transaction_ids[]"
                               form="wallet-deposit-bulk-approve"
                               value="{{ $tx->id }}"
                               data-amount="{{ (int) $tx->amount }}"
                               aria-label="Chọn đơn {{ $tx->depositReference() }}">
                    </td>
                    <td class="cell-primary fw-semibold">{{ $tx->depositReference() }}</td>
                    <td>
                        {{ $driverUser->name }}
                        @if($driverProfile->driver_code)
                            <div class="cell-muted small">{{ $driverProfile->driver_code }}</div>
                        @endif
                        <div class="cell-muted small">SĐT {{ $driverUser->phone ?? '—' }}</div>
                    </td>
                    <td class="fw-semibold text-success">
                        +{{ number_format($tx->amount, 0, ',', '.') }} đ
                    </td>
                    <td>
                        @if($tx->proofImageUrl())
                            <a href="{{ $tx->proofImageUrl() }}"
                               class="wallet-deposit-proof-thumb"
                               target="_blank"
                               rel="noopener"
                               title="Xem ảnh chuyển khoản {{ $tx->depositReference() }}">
                                <img src="{{ $tx->proofImageUrl() }}"
                                     alt="Ảnh CK {{ $tx->depositReference() }}"
                                     width="56"
                                     height="56"
                                     loading="lazy">
                            </a>
                        @else
                            <span class="cell-muted small">—</span>
                        @endif
                    </td>
                    <td class="cell-muted small">{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                            <form method="POST"
                                  action="{{ route('admin.walletTransactions.approve', $tx) }}"
                                  class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">Duyệt</button>
                            </form>
                            <form method="POST"
                                  action="{{ route('admin.walletTransactions.reject', $tx) }}"
                                  class="d-inline"
                                  data-confirm="Từ chối yêu cầu {{ $tx->depositReference() }}?"
                                  data-confirm-title="Từ chối nạp ví"
                                  data-confirm-ok="Từ chối"
                                  data-confirm-variant="danger">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Từ chối</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('partials.pagination', ['paginator' => $depositsPending])
@endif
