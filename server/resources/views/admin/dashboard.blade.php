@extends('layouts.console')

@section('console')
@php
$allowedAdminTabs = ['operators', 'referrals', 'referral-costs', 'fees', 'settings', 'revenue', 'routes', 'cancel-reasons'];
$tabFromRequest = request('tab');
if ($tabFromRequest === 'bank') {
    $tabFromRequest = 'settings';
}
$tabFromCookie = request()->cookie('admin-main_tab');
if ($tabFromCookie === 'bank') {
    $tabFromCookie = 'settings';
}
$adminDefaultTab = in_array($tabFromRequest, $allowedAdminTabs, true)
    ? $tabFromRequest
    : (in_array($tabFromCookie, $allowedAdminTabs, true) ? $tabFromCookie : null);
if ($adminDefaultTab === null) {
    $adminDefaultTab = ($errors->has('name') || $errors->has('phone')) && ! $errors->has('email')
        ? (request()->has('commission_percent') ? 'referrals' : 'operators')
        : (($errors->has('bank_name') || $errors->has('bank_bin') || $errors->has('banner_image')) ? 'settings'
        : ($errors->has('label') && $errors->has('audience') ? 'cancel-reasons' : 'operators'));
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
                        ['key' => 'operators', 'label' => 'Quản lý', 'badge' => $operators->total()],
                        ['key' => 'referrals', 'label' => 'Mã giới thiệu', 'badge' => $referralCodes->total()],
                        ['key' => 'referral-costs', 'label' => 'Chi phí người GT', 'badge' => $referralCostTrips->total() ?: null],
                        ['key' => 'fees', 'label' => 'Phí & giá'],
                        ['key' => 'settings', 'label' => 'Cài đặt'],
                        ['key' => 'revenue', 'label' => 'Doanh thu'],
                        ['key' => 'routes', 'label' => 'Điểm đến'],
                        ['key' => 'cancel-reasons', 'label' => 'Lý do hủy', 'badge' => ($cancellationReasonList ?? collect())->count()],
                    ],
                ])

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'operators', 'active' => $adminDefaultTab === 'operators'])
                <form method="POST" action="{{ route('admin.operators.store') }}" class="console-form mb-4 pb-3 border-bottom">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="operator-name">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="operator-name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="operator-email">Email <span class="text-muted fw-normal">(tuỳ chọn)</span></label>
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
                <h6 class="fw-semibold mb-3">Danh sách quản lý</h6>
                @if($operators->isEmpty())
                    <div class="console-empty py-4"><p class="mb-0">Chưa có quản lý nào.</p></div>
                @else
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Họ tên</th>
                                    <th>Email</th>
                                    <th>SĐT</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($operators as $op)
                                <tr>
                                    <td class="cell-primary">{{ $op->name }}</td>
                                    <td class="cell-muted">
                                        @if(filled($op->email) && ! str_ends_with($op->email, '@noemail.local'))
                                            {{ $op->email }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="cell-muted">{{ $op->phone ?? '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.users.status', $op) }}" class="d-flex gap-1 align-items-center">
                                            @csrf @method('PATCH')
                                            <select name="status" class="form-select form-select-sm" style="width:130px">
                                                @foreach(['active', 'suspended'] as $st)
                                                    <option value="{{ $st }}" {{ ($op->status === $st || ($st === 'suspended' && $op->status === 'inactive')) ? 'selected' : '' }}>
                                                        {{ $st === 'active' ? 'Hoạt động' : 'Tạm ngưng' }}
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
                        @if($ref->type === \App\Models\ReferralCode::TYPE_BOOKING_TEMP)
                            <form method="POST" action="{{ route('admin.referralCodes.destroy', $ref) }}" id="delete-referral-{{ $ref->id }}"
                                  data-confirm="Xóa mã {{ $ref->code }} ({{ $ref->name }})?"
                                  data-confirm-title="Xóa mã giới thiệu"
                                  data-confirm-variant="danger"
                                  data-confirm-ok="Xóa">
                                @csrf
                                @method('DELETE')
                            </form>
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
                                    <th>Giảm giá KH</th>
                                    <th>Ngày hết hạn</th>
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
                                    <td class="cell-muted">
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            {{ number_format($ref->customerDiscountPercent(), 1) }}%
                                        @else
                                            {{ number_format($ref->customerDiscountPercent(), 1) }}%
                                            <span class="d-block small">Từ vé</span>
                                        @endif
                                    </td>
                                    <td class="cell-muted small">
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            <span class="text-muted">—</span>
                                        @else
                                            <span class="status-pill status-pill--{{ $ref->expiryColor() }}">{{ $ref->expiryLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="cell-muted small">{{ $ref->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            <form method="POST" action="{{ route('admin.referrers.update', $ref) }}" class="d-inline-flex flex-wrap gap-1 align-items-center justify-content-end mb-1">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" name="customer_discount_percent" class="form-control form-control-sm" style="width:4.5rem"
                                                       min="0" max="100" step="0.1" value="{{ number_format($ref->customerDiscountPercent(), 1, '.', '') }}" title="% giảm giá khách" aria-label="Giảm giá %">
                                                <input type="number" name="commission_percent" class="form-control form-control-sm" style="width:4.5rem"
                                                       min="0" max="100" step="0.1" value="{{ number_format($ref->commissionPercent(), 1, '.', '') }}" title="% hoa hồng" aria-label="Hoa hồng %">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">Lưu</button>
                                            </form>
                                            @if($ref->isSuspended())
                                                <button type="submit" class="btn btn-outline-primary btn-sm" form="show-referrer-{{ $ref->id }}">Sử dụng</button>
                                            @else
                                                <button type="submit" class="btn btn-outline-danger btn-sm" form="hide-referrer-{{ $ref->id }}">Tạm ngưng</button>
                                            @endif
                                        @else
                                            <button type="submit" class="btn btn-outline-danger btn-sm" form="delete-referral-{{ $ref->id }}">Xóa</button>
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

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'referral-costs', 'active' => $adminDefaultTab === 'referral-costs'])
                <p class="text-muted small mb-3">
                    Chi phí hoa hồng người giới thiệu theo từng cuốc hoàn thành trong tháng {{ \Carbon\Carbon::parse($revenueSummary['from'])->format('m/Y') }}.
                    Tổng: <strong>{{ number_format($revenueSummary['referral_cost'], 0, ',', '.') }} đ</strong>.
                </p>
                @if($referralCostTrips->isEmpty())
                    <div class="console-empty py-4"><p class="mb-0">Chưa có cuốc nào ghi nhận chi phí giới thiệu.</p></div>
                @else
                    <div class="console-table-wrap">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Mã chuyến</th>
                                    <th>Mã GT</th>
                                    <th>Người giới thiệu</th>
                                    <th>Khách</th>
                                    <th class="text-end">% HH</th>
                                    <th class="text-end">Chi phí</th>
                                    <th>Hoàn tất</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($referralCostTrips as $booking)
                                <tr>
                                    <td class="cell-primary"><code>{{ $booking->schedule?->shortTripCode() ?? '—' }}</code></td>
                                    <td><span class="driver-meta-code">{{ $booking->appliedReferralCode?->code ?? '—' }}</span></td>
                                    <td class="small">{{ $booking->appliedReferralCode?->name ?? '—' }}</td>
                                    <td class="small cell-muted">{{ $booking->passenger_name }}<br>{{ $booking->contact_phone }}</td>
                                    <td class="text-end">{{ number_format($booking->appliedReferralCode?->commissionPercent() ?? 0, 1) }}%</td>
                                    <td class="text-end fw-semibold">{{ number_format($booking->referralCommissionAmount(), 0, ',', '.') }} đ</td>
                                    <td class="cell-muted small">{{ $booking->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('partials.pagination', ['paginator' => $referralCostTrips])
                @endif
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'settings', 'active' => $adminDefaultTab === 'settings'])

                <div class="mb-4 pb-4 border-bottom border-secondary">
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-2" style="letter-spacing:.04em">Tài khoản ngân hàng</h3>
                    <p class="text-muted small mb-3">
                        QR chuyển khoản <strong>tự sinh qua VietQR</strong> từ thông tin bên dưới — dùng chung cho nạp ví tài xế và phí nền tảng.
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
                <div class="mt-4 pt-3 border-top border-secondary">
                    <label class="form-label d-block">Xem thử QR (chưa có số tiền)</label>
                    <img src="{{ $bankQrPreview }}" alt="QR VietQR xem thử" class="rounded border" width="160" height="160">
                </div>
                @endif
                </div>

                <div>
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-2" style="letter-spacing:.04em">Banner trang đặt vé</h3>
                    <p class="text-muted small mb-3">
                        Ảnh hiển thị thay cho dòng «Đặt vé xe liên tỉnh» trên trang khách đặt chuyến. Khuyến nghị ngang ≥ 1200px.
                    </p>
                    @if($bookingBannerUrl ?? null)
                    <div class="mb-3">
                        <label class="form-label d-block">Banner hiện tại</label>
                        <img src="{{ $bookingBannerUrl }}" alt="Banner trang đặt vé" class="rounded border admin-booking-banner-preview">
                    </div>
                    @endif
                    <form method="POST" action="{{ route('admin.bookingBanner.update') }}" class="console-form mb-3" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label" for="banner-image">Ảnh banner</label>
                                <input type="file" name="banner_image" id="banner-image"
                                       class="form-control @error('banner_image') is-invalid @enderror"
                                       accept="image/jpeg,image/png,image/webp,image/*" required>
                                @error('banner_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary fw-semibold w-100">Lưu banner</button>
                            </div>
                        </div>
                    </form>
                    @if($bookingBannerUrl ?? null)
                    <form method="POST" action="{{ route('admin.bookingBanner.destroy') }}"
                          data-confirm="Xóa banner và dùng lại giao diện mặc định?"
                          data-confirm-title="Xóa banner"
                          data-confirm-variant="danger"
                          data-confirm-ok="Xóa">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">Xóa banner</button>
                    </form>
                    @endif
                </div>
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'revenue', 'active' => $adminDefaultTab === 'revenue'])
                <p class="text-muted small mb-3">
                    Tháng {{ \Carbon\Carbon::parse($revenueSummary['from'])->format('m/Y') }} —
                    tổng {{ number_format($revenueSummary['total_trips']) }} chuyến ghi nhận,
                    tỷ lệ hoàn thành <strong>{{ number_format($revenueSummary['completion_rate'], 1) }}%</strong>.
                </p>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '₫', 'value' => number_format($revenueSummary['total_revenue'], 0, ',', '.') . ' đ', 'label' => 'Tổng doanh thu', 'tone' => 'primary'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => 'GT', 'value' => number_format($revenueSummary['referral_cost'], 0, ',', '.') . ' đ', 'label' => 'Phí giới thiệu', 'tone' => 'warning'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => 'TX', 'value' => number_format($revenueSummary['driver_revenue'], 0, ',', '.') . ' đ', 'label' => 'Thu nhập tài xế', 'tone' => 'info'])
                    </div>
                    <div class="col-6 col-md-3">
                        @include('partials.console-stat', ['icon' => '∑', 'value' => number_format($revenueSummary['net_revenue'], 0, ',', '.') . ' đ', 'label' => 'Doanh thu thực tế', 'tone' => 'success'])
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

                    <hr class="my-4">
                    <h3 class="h6 fw-semibold mb-2">Hệ số giá cả xe theo loại chỗ</h3>
                    <p class="text-muted small mb-3">
                        Lấy <strong>4 chỗ = 100%</strong> làm chuẩn. Mỗi bậc loại xe tiếp theo tăng thêm
                        <strong>{{ number_format($feeSettings['vehicle_capacity']['step_percent'], 1) }}%</strong> (có thể chỉnh bên dưới).
                        Giá cả xe = (km × đơn giá/km) × hệ số %.
                    </p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label">Bước tăng mỗi loại xe (%)</label>
                            <div class="input-group">
                                <input type="number" name="capacity_step_percent" class="form-control" min="0" max="50" step="0.1"
                                       value="{{ old('capacity_step_percent', $feeSettings['vehicle_capacity']['step_percent']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="console-table-wrap mb-3">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Loại xe</th>
                                    <th>% so với 4 chỗ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(\App\Support\VehicleCapacityOptions::STANDARD as $capacity)
                                @php
                                    $defaultPercent = \App\Support\VehicleCapacityPricing::defaultPercentForCapacity($capacity);
                                @endphp
                                <tr>
                                    <td>{{ \App\Support\VehicleCapacityOptions::label($capacity) }}</td>
                                    <td style="max-width: 12rem;">
                                        <div class="input-group input-group-sm">
                                            <input type="number"
                                                   name="capacity_percents[{{ $capacity }}]"
                                                   class="form-control"
                                                   min="50" max="500" step="0.1"
                                                   value="{{ old('capacity_percents.'.$capacity, $feeSettings['vehicle_capacity']['percents'][$capacity] ?? $defaultPercent) }}"
                                                   required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        @if($capacity !== 4)
                                            <div class="form-text">Mặc định {{ number_format($defaultPercent, 1) }}%</div>
                                        @else
                                            <div class="form-text">Chuẩn 100%</div>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
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

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'cancel-reasons', 'active' => $adminDefaultTab === 'cancel-reasons'])
                @include('partials.admin-cancellation-reasons', ['reasons' => $cancellationReasonList ?? collect()])
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
