@extends('layouts.console')

@section('console')
@php
$adminDefaultTab = request('tab');
if (! in_array($adminDefaultTab, ['create', 'list', 'referrals', 'fees', 'bank', 'revenue', 'routes'], true)) {
    $adminDefaultTab = ($errors->has('name') || $errors->has('phone')) && ! $errors->has('email')
        ? 'referrals'
        : (($errors->has('bank_name') || $errors->has('bank_bin')) ? 'bank' : 'create');
}
@endphp
@include('partials.console-hero', [
    'title' => 'Quản trị hệ thống',
])

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="console-panel">
            <div class="console-panel-body">
                @include('partials.screen-tabs-start', [
                    'prefix' => 'admin-main',
                    'activeKey' => $adminDefaultTab,
                    'tabs' => [
                        ['key' => 'create', 'label' => 'Tạo quản lý'],
                        ['key' => 'list', 'label' => 'Danh sách', 'badge' => $operators->total()],
                        ['key' => 'referrals', 'label' => 'Mã giới thiệu', 'badge' => $referralCodes->total()],
                        ['key' => 'fees', 'label' => 'Phí & giá'],
                        ['key' => 'bank', 'label' => 'Ngân hàng'],
                        ['key' => 'revenue', 'label' => 'Doanh thu'],
                        ['key' => 'routes', 'label' => 'Điểm đến'],
                    ],
                ])

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'create', 'active' => $adminDefaultTab === 'create'])
                <form method="POST" action="{{ route('admin.operators.store') }}" class="console-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="operator-name">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="operator-name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="operator-email">Email</label>
                            <input type="email" name="email" id="operator-email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" autocomplete="email">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="operator-phone">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" id="operator-phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" required autocomplete="tel">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="operator-password">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="operator-password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   minlength="8" required autocomplete="new-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="operator-password-confirmation">Nhập lại mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" id="operator-password-confirmation"
                                   class="form-control @error('password_confirmation') is-invalid @enderror"
                                   minlength="8" required autocomplete="new-password">
                            @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Tạo quản lý</button>
                </form>
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'list', 'active' => $adminDefaultTab === 'list'])
                @if($operators->isEmpty())
                    <div class="console-empty py-4"><p class="mb-0">Chưa có quản lý nào.</p></div>
                @else
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Họ tên</th>
                                    <th>Thư điện tử</th>
                                    <th>SĐT</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($operators as $op)
                                <tr>
                                    <td class="cell-primary">{{ $op->name }}</td>
                                    <td class="cell-muted">{{ $op->email }}</td>
                                    <td class="cell-muted">{{ $op->phone ?? '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.users.status', $op) }}" class="d-flex gap-1 align-items-center">
                                            @csrf @method('PATCH')
                                            <select name="status" class="form-select form-select-sm" style="width:130px">
                                                @foreach(['active','inactive','suspended'] as $st)
                                                    <option value="{{ $st }}" {{ $op->status === $st ? 'selected' : '' }}>
                                                        {{ match($st){ 'active'=>'Hoạt động','inactive'=>'Vô hiệu','suspended'=>'Tạm ngưng' } }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('partials.pagination', ['paginator' => $operators])
                @endif
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'referrals', 'active' => $adminDefaultTab === 'referrals'])
                <p class="text-muted small mb-3">
                    Mã <strong>người giới thiệu</strong> (admin tạo): hoa hồng {{ number_format(\App\Support\PlatformFees::referralCommissionFirstPercent(), 1) }}% vào doanh thu — <strong>không giảm giá vé</strong>.
                    Mã <strong>từ đặt vé</strong>: khách quét QR được giảm {{ number_format(\App\Support\PlatformFees::referralCommissionRepeatPercent(), 1) }}% (mỗi SĐT một lần); tự tạo khi đặt chuyến,
                    đang chờ đến khi chuyến hoàn tất mới dùng được — hủy/không đi thì mã bị xóa.
                </p>
                <form method="POST" action="{{ route('admin.referrers.store') }}" class="console-form mb-4">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" for="referrer-name">Tên người giới thiệu <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="referrer-name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="referrer-phone">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" id="referrer-phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100 fw-semibold">Tạo mã</button>
                        </div>
                    </div>
                </form>
                @if($referralCodes->isEmpty())
                    <div class="console-empty py-4"><p class="mb-0">Chưa có mã giới thiệu.</p></div>
                @else
                    @foreach($referralCodes as $ref)
                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            @if($ref->status !== \App\Models\ReferralCode::STATUS_SUSPENDED)
                                <form method="POST" action="{{ route('admin.referrers.hide', $ref) }}" id="hide-referrer-{{ $ref->id }}"
                                      data-confirm="Tạm ngưng mã {{ $ref->code }} ({{ $ref->name }})?"
                                      data-confirm-title="Tạm ngưng mã giới thiệu"
                                      data-confirm-variant="danger"
                                      data-confirm-ok="Tạm ngưng">
                                    @csrf
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.referrers.show', $ref) }}" id="show-referrer-{{ $ref->id }}">
                                    @csrf
                                </form>
                            @endif
                        @endif
                    @endforeach
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Mã</th>
                                    <th>QR</th>
                                    <th>Loại</th>
                                    <th>Tên</th>
                                    <th>SĐT</th>
                                    <th>Trạng thái</th>
                                    <th>Hoa hồng</th>
                                    <th>Ngày tạo</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($referralCodes as $ref)
                                <tr class="@if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER && $ref->isSuspended()) destination-row-hidden @endif">
                                    <td class="cell-primary">
                                        <span class="driver-meta-code">{{ $ref->code }}</span>
                                    </td>
                                    <td>
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER && $ref->isUsable())
                                            <button type="button" class="referral-qr-thumb" data-referral-qr-open
                                                    data-url="{{ $ref->landingUrl() }}" data-code="{{ $ref->code }}"
                                                    title="Xem QR — {{ $ref->code }}">
                                                <span data-referral-qr data-url="{{ $ref->landingUrl() }}"></span>
                                            </button>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td class="cell-muted">{{ $ref->typeLabel() }}</td>
                                    <td>{{ $ref->name }}</td>
                                    <td class="cell-muted">{{ $ref->phone }}</td>
                                    <td>
                                        <span class="status-pill status-pill--{{ $ref->statusColor() }}">{{ $ref->statusLabel() }}</span>
                                    </td>
                                    <td class="cell-muted">
                                        {{ number_format($ref->commissionPercent(), 1) }}%
                                        <span class="d-block small">{{ $ref->commissionTierLabel() }}</span>
                                    </td>
                                    <td class="cell-muted small">{{ $ref->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            @if($ref->isSuspended())
                                                <button type="submit" class="btn btn-outline-primary btn-sm" form="show-referrer-{{ $ref->id }}">Sử dụng</button>
                                            @else
                                                <button type="submit" class="btn btn-outline-danger btn-sm" form="hide-referrer-{{ $ref->id }}">Tạm ngưng</button>
                                            @endif
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('partials.pagination', ['paginator' => $referralCodes])
                @endif
                @include('partials.referral-qr-modal')
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'bank', 'active' => $adminDefaultTab === 'bank'])
                <p class="text-muted small mb-3">
                    QR chuyển khoản <strong>tự sinh qua VietQR</strong> từ thông tin bên dưới — dùng chung cho nạp ví tài xế và phí nền tảng.
                    Số tiền và nội dung CK cập nhật theo từng thao tác trên trang tài xế.
                </p>
                <form method="POST" action="{{ route('admin.bankSettings.update') }}" class="console-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="bank-name">Tên ngân hàng</label>
                            <input type="text" name="bank_name" id="bank-name" class="form-control @error('bank_name') is-invalid @enderror"
                                   value="{{ old('bank_name', $bankSettings['bank_name']) }}" required>
                            @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bank-bin">Mã BIN ngân hàng</label>
                            <input type="text" name="bank_bin" id="bank-bin" class="form-control @error('bank_bin') is-invalid @enderror"
                                   value="{{ old('bank_bin', $bankSettings['bank_bin']) }}" required maxlength="20"
                                   inputmode="numeric" pattern="[0-9]*">
                            @error('bank_bin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Mã định danh ngân hàng trong hệ VietQR (6 chữ số). Ví dụ:
                                VietinBank <code>970415</code>,
                                Vietcombank <code>970436</code>,
                                Techcombank <code>970407</code>,
                                MB Bank <code>970422</code>.
                                BIN phải khớp với ngân hàng của số TK thì quét mới đúng.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bank-account">Số tài khoản</label>
                            <input type="text" name="account" id="bank-account" class="form-control @error('account') is-invalid @enderror"
                                   value="{{ old('account', $bankSettings['account']) }}" required>
                            @error('account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bank-account-name">Tên chủ tài khoản</label>
                            <input type="text" name="account_name" id="bank-account-name" class="form-control @error('account_name') is-invalid @enderror"
                                   value="{{ old('account_name', $bankSettings['account_name']) }}" required>
                            @error('account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu tài khoản</button>
                </form>
                @if($bankQrPreview)
                <div class="mt-4 pt-3 border-top">
                    <label class="form-label d-block">Xem thử QR (chưa có số tiền)</label>
                    <img src="{{ $bankQrPreview }}" alt="QR VietQR xem thử" class="rounded border" width="160" height="160">
                    <p class="form-text mb-0">Quét bằng app ngân hàng để kiểm tra STK và tên chủ TK.</p>
                </div>
                @endif
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'revenue', 'active' => $adminDefaultTab === 'revenue'])
                <p class="text-muted small mb-3">
                    Tháng {{ \Carbon\Carbon::parse($revenueSummary['from'])->format('m/Y') }} —
                    tổng {{ number_format($revenueSummary['total_trips']) }} chuyến ghi nhận,
                    tỷ lệ hoàn thành <strong>{{ number_format($revenueSummary['completion_rate'], 1) }}%</strong>.
                </p>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '✓', 'value' => number_format($revenueSummary['trip_count']), 'label' => 'Chạy thành công', 'tone' => 'success'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '✕', 'value' => number_format($revenueSummary['cancelled_customer']), 'label' => 'Khách hủy', 'tone' => 'warning'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => 'TX', 'value' => number_format($revenueSummary['cancelled_driver']), 'label' => 'Tài xế hủy', 'tone' => 'danger'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '₫', 'value' => number_format($revenueSummary['gross_revenue'], 0, ',', '.') . ' đ', 'label' => 'Doanh thu gộp', 'tone' => 'primary'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '%', 'value' => number_format($revenueSummary['platform_fee'], 0, ',', '.') . ' đ', 'label' => 'Phí nền tảng', 'tone' => 'info'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => 'GT', 'value' => number_format($revenueSummary['referral_commission'], 0, ',', '.') . ' đ', 'label' => 'Hoa hồng GT', 'tone' => 'warning'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '≈', 'value' => number_format($revenueSummary['avg_revenue_per_trip'], 0, ',', '.') . ' đ', 'label' => 'TB / chuyến HT', 'tone' => 'primary'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '∑', 'value' => number_format($revenueSummary['net_estimate'], 0, ',', '.') . ' đ', 'label' => 'Còn lại (ước tính)', 'tone' => 'primary'])
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-4">
                        <h6 class="fw-semibold mb-2">Tài xế chạy thành công</h6>
                        @if($revenueByDriver->isEmpty())
                            <p class="text-muted small mb-0">Chưa có dữ liệu.</p>
                        @else
                            <div class="console-table-wrap">
                                <table class="console-table console-table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tài xế</th>
                                            <th class="text-end">Chuyến</th>
                                            <th class="text-end">Doanh thu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($revenueByDriver as $row)
                                        <tr>
                                            <td class="small">
                                                @if($row->actor_code)<code>{{ $row->actor_code }}</code><br>@endif
                                                {{ $row->actor_label ?? '—' }}
                                            </td>
                                            <td class="text-end">{{ $row->trips }}</td>
                                            <td class="text-end cell-muted small">{{ number_format($row->revenue, 0, ',', '.') }} đ</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-semibold mb-2">Khách hủy chuyến</h6>
                        @if($revenueByCustomerCancel->isEmpty())
                            <p class="text-muted small mb-0">Chưa có dữ liệu.</p>
                        @else
                            <div class="console-table-wrap">
                                <table class="console-table console-table-sm">
                                    <thead>
                                        <tr>
                                            <th>Khách</th>
                                            <th class="text-end">Lần hủy</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($revenueByCustomerCancel as $row)
                                        <tr>
                                            <td class="small">
                                                {{ $row->actor_label ?? 'Khách' }}
                                                @if($row->actor_code)<br><code>{{ $row->actor_code }}</code>@endif
                                            </td>
                                            <td class="text-end">{{ $row->trips }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-semibold mb-2">Tài xế từ chối / hủy</h6>
                        @if($revenueByDriverCancel->isEmpty())
                            <p class="text-muted small mb-0">Chưa có dữ liệu.</p>
                        @else
                            <div class="console-table-wrap">
                                <table class="console-table console-table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tài xế</th>
                                            <th class="text-end">Lần</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($revenueByDriverCancel as $row)
                                        <tr>
                                            <td class="small">
                                                @if($row->actor_code)<code>{{ $row->actor_code }}</code><br>@endif
                                                {{ $row->actor_label ?? '—' }}
                                            </td>
                                            <td class="text-end">{{ $row->trips }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                @if($revenueByRoute->isNotEmpty())
                <div class="mb-4">
                    <h6 class="fw-semibold mb-2">Tuyến doanh thu cao (chuyến thành công)</h6>
                    <div class="console-table-wrap">
                        <table class="console-table console-table-sm">
                            <thead>
                                <tr>
                                    <th>Tuyến</th>
                                    <th class="text-end">Chuyến</th>
                                    <th class="text-end">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($revenueByRoute as $row)
                                <tr>
                                    <td>{{ $row->route_label }}</td>
                                    <td class="text-end">{{ $row->trips }}</td>
                                    <td class="text-end cell-muted">{{ number_format($row->revenue, 0, ',', '.') }} đ</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <h6 class="fw-semibold mb-2">Sổ chuyến chi tiết</h6>
                @if($tripLedger->isEmpty())
                    <div class="console-empty py-4"><p class="mb-0">Chưa có chuyến nào được ghi nhận.</p></div>
                @else
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Mã chuyến</th>
                                    <th>Tuyến</th>
                                    <th>Trạng thái</th>
                                    <th>Người liên quan</th>
                                    <th class="text-end">Doanh thu</th>
                                    <th>Ngày ghi nhận</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tripLedger as $trip)
                                <tr>
                                    <td class="cell-primary"><code>{{ $trip->trip_code }}</code></td>
                                    <td class="cell-muted small">{{ $trip->route_label ?? '—' }}</td>
                                    <td>
                                        <span class="status-pill status-pill--{{ $trip->outcomeColor() }}">{{ $trip->outcomeLabel() }}</span>
                                    </td>
                                    <td class="small">{{ $trip->actorSummary() }}</td>
                                    <td class="text-end cell-muted small">
                                        @if($trip->outcome === \App\Models\TripLedger::OUTCOME_COMPLETED && $trip->amount)
                                            {{ number_format($trip->amount, 0, ',', '.') }} đ
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="cell-muted small">{{ $trip->recorded_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('partials.pagination', ['paginator' => $tripLedger])
                @endif
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'fees', 'active' => $adminDefaultTab === 'fees'])
                <form method="POST" action="{{ route('admin.feeSettings.update') }}" class="console-form">
                    @csrf
                    <p class="text-muted small">Giá tính theo km: ≤100 km dùng đơn giá thấp hơn, &gt;100 km dùng đơn giá cao hơn. Khứ hồi giảm theo % bên dưới (mặc định 15%).</p>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Giá / km (≤ 100 km)</label>
                            <div class="input-group">
                                <input type="number" name="km_rate_under_100" class="form-control" min="0" step="500"
                                       value="{{ old('km_rate_under_100', $feeSettings['km_rate_under_100']) }}" required>
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Giá / km (&gt; 100 km)</label>
                            <div class="input-group">
                                <input type="number" name="km_rate_over_100" class="form-control" min="0" step="500"
                                       value="{{ old('km_rate_over_100', $feeSettings['km_rate_over_100']) }}" required>
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Giảm giá khứ hồi (%)</label>
                            <div class="input-group">
                                <input type="number" name="round_trip_discount" class="form-control" min="0" max="100" step="0.5"
                                       value="{{ old('round_trip_discount', $feeSettings['round_trip_discount']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Hoa hồng app (%)</label>
                            <div class="input-group">
                                <input type="number" name="app_commission" class="form-control" min="0" max="100" step="0.1"
                                       value="{{ old('app_commission', $feeSettings['app_commission']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Hoa hồng giới thiệu — lần đầu (%)</label>
                            <div class="input-group">
                                <input type="number" name="referral_commission_first" class="form-control" min="0" max="100" step="0.1"
                                       value="{{ old('referral_commission_first', $feeSettings['referral_commission_first']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Hoa hồng giới thiệu — từ lần 2 (%)</label>
                            <div class="input-group">
                                <input type="number" name="referral_commission_repeat" class="form-control" min="0" max="100" step="0.1"
                                       value="{{ old('referral_commission_repeat', $feeSettings['referral_commission_repeat']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu cài đặt</button>
                </form>
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'routes', 'active' => $adminDefaultTab === 'routes'])
                <p class="text-muted small mb-3">Danh sách điểm đến từ TP.HCM — dùng cho form đặt vé và tự điền km/giá khi quản lý tạo chuyến.</p>

                <form method="POST" action="{{ route('admin.destinations.store') }}" class="console-form mb-4">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" for="admin-new-destination">Thêm điểm đến</label>
                            <input type="text" name="destination" id="admin-new-destination" class="form-control"
                                   value="{{ old('destination') }}" placeholder="Ví dụ: Nha Trang" required maxlength="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="admin-new-distance">Km từ TP.HCM</label>
                            <input type="number" name="distance_km" id="admin-new-distance" class="form-control"
                                   min="1" max="2000" value="{{ old('distance_km') }}" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary fw-semibold">Thêm điểm đến</button>
                        </div>
                    </div>
                </form>

                @foreach($hubRoutes as $hubRoute)
                    @if($hubRoute->is_active)
                        <form method="POST" action="{{ route('admin.destinations.destroy', $hubRoute) }}" id="hide-dest-{{ $hubRoute->id }}"
                              data-confirm="Ẩn {{ $hubRoute->destination }} khỏi danh sách đặt vé?"
                              data-confirm-title="Ẩn điểm đến"
                              data-confirm-variant="danger"
                              data-confirm-ok="Ẩn">
                            @csrf
                            @method('DELETE')
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.destinations.show', $hubRoute) }}" id="show-dest-{{ $hubRoute->id }}">
                            @csrf
                        </form>
                    @endif
                @endforeach

                <form method="POST" action="{{ route('admin.routeDistances.update') }}" class="console-form" id="hub-routes-form">
                    @csrf
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Điểm đến</th>
                                    <th style="width:160px">Km</th>
                                    <th style="width:100px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $activeRouteIndex = 0; @endphp
                                @foreach($hubRoutes as $hubRoute)
                                <tr class="@if(! $hubRoute->is_active) destination-row-hidden @endif">
                                    <td class="cell-primary">
                                        {{ $hubRoute->destination }}
                                        @if(! $hubRoute->is_active)
                                            <span class="status-pill status-pill--neutral ms-1">Đã ẩn</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($hubRoute->is_active)
                                            <input type="hidden" name="routes[{{ $activeRouteIndex }}][id]" value="{{ $hubRoute->id }}">
                                            <input type="number" name="routes[{{ $activeRouteIndex }}][distance_km]" class="form-control form-control-sm"
                                                   min="1" max="2000" required value="{{ old('routes.'.$activeRouteIndex.'.distance_km', $hubRoute->distance_km) }}">
                                            @php $activeRouteIndex++; @endphp
                                        @else
                                            <input type="number" class="form-control form-control-sm" value="{{ $hubRoute->distance_km }}" disabled readonly>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($hubRoute->is_active)
                                            <button type="submit" class="btn btn-outline-danger btn-sm" form="hide-dest-{{ $hubRoute->id }}">Ẩn</button>
                                        @else
                                            <button type="submit" class="btn btn-outline-primary btn-sm" form="show-dest-{{ $hubRoute->id }}">Hiện</button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu quãng đường</button>
                </form>
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tabs-end')
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/referral-qr.js') }}"></script>
@endpush
