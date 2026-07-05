@extends('layouts.console')

@section('console')
@php
$allowedAdminTabs = ['referrals', 'fees', 'settings', 'appearance'];
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
    : (in_array($tabFromCookie, $allowedAdminTabs, true) ? $tabFromCookie : 'referrals');
$referralCommissionStats = $referralCommissionStats ?? [];
@endphp
@include('partials.console-hero', [
    'title' => 'Quản trị hệ thống',
])

@include('partials.admin-nav-tabs', ['active' => 'config'])

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="console-panel">
            <div class="console-panel-body">
                @include('partials.screen-tabs-start', [
                    'prefix' => 'admin-main',
                    'activeKey' => $adminDefaultTab,
                    'tabs' => [
                        ['key' => 'referrals', 'label' => 'Mã giới thiệu', 'badge' => $referralCodes->total()],
                        ['key' => 'fees', 'label' => 'Tính tiền'],
                        ['key' => 'settings', 'label' => 'Ngân hàng'],
                        ['key' => 'appearance', 'label' => 'Cài đặt'],
                    ],
                ])

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
                                    <th>% HH</th>
                                    <th>Doanh thu GT</th>
                                    <th>Hoa hồng GT</th>
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
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            {{ number_format($ref->commissionPercent(), 1) }}%
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="cell-muted small">
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            @php
                                                $refStats = $referralCommissionStats[$ref->id] ?? ['trips' => 0, 'revenue' => 0, 'commission' => 0];
                                            @endphp
                                            @if($refStats['revenue'] > 0)
                                                <span class="fw-semibold text-body">{{ number_format($refStats['revenue'], 0, ',', '.') }} đ</span>
                                                <span class="d-block text-muted">{{ $refStats['trips'] }} chuyến HT</span>
                                            @else
                                                <span class="text-muted">0 đ</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="cell-muted small">
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            @php
                                                $refStats = $referralCommissionStats[$ref->id] ?? ['trips' => 0, 'revenue' => 0, 'commission' => 0];
                                            @endphp
                                            @if($refStats['commission'] > 0)
                                                <span class="fw-semibold text-success">{{ number_format($refStats['commission'], 0, ',', '.') }} đ</span>
                                            @else
                                                <span class="text-muted">0 đ</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="cell-muted">
                                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                                            <span class="text-muted">—</span>
                                        @else
                                            {{ number_format($ref->customerDiscountPercent(), 1) }}%
                                            <span class="d-block small">Mã QR vé</span>
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
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'appearance', 'active' => $adminDefaultTab === 'appearance'])
                <div class="mb-4 pb-4 border-bottom border-secondary">
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.04em">Thương hiệu</h3>
                    <form method="POST" action="{{ route('admin.brandingSettings.update') }}" class="console-form">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="branding-app-name">Tên hiển thị</label>
                                <input type="text" name="app_name" id="branding-app-name" class="form-control @error('app_name') is-invalid @enderror"
                                       value="{{ old('app_name', $brandingSettings['app_name']) }}" maxlength="80">
                                @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="branding-brand-title">Chữ thương hiệu</label>
                                <input type="text" name="brand_title" id="branding-brand-title" class="form-control @error('brand_title') is-invalid @enderror"
                                       value="{{ old('brand_title', $brandingSettings['brand_title']) }}" maxlength="40">
                                @error('brand_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="branding-brand-tagline">Dòng phụ</label>
                                <input type="text" name="brand_tagline" id="branding-brand-tagline" class="form-control @error('brand_tagline') is-invalid @enderror"
                                       value="{{ old('brand_tagline', $brandingSettings['brand_tagline']) }}" maxlength="80">
                                @error('brand_tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu thương hiệu</button>
                    </form>
                </div>
                <div class="mb-2">
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.04em">Trang đặt xe</h3>
                </div>
                <form method="POST" action="{{ route('admin.bookingPageSettings.update') }}" class="console-form" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="booking-hero-title">Tiêu đề hiển thị</label>
                            <input type="text" name="hero_title" id="booking-hero-title" class="form-control @error('hero_title') is-invalid @enderror"
                                   value="{{ old('hero_title', $bookingPageSettings['hero_title']) }}" maxlength="120">
                            @error('hero_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="booking-hero-banner">Ảnh banner</label>
                            <input type="file" name="banner" id="booking-hero-banner" class="form-control @error('banner') is-invalid @enderror"
                                   accept="image/jpeg,image/png,image/webp,image/gif">
                            @error('banner')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($bookingPageSettings['has_banner'] ?? false)
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_banner" value="1" id="booking-remove-banner">
                                    <label class="form-check-label" for="booking-remove-banner">Xóa banner hiện tại</label>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($bookingPageSettings['banner_url'] ?? null)
                        <div class="mt-3">
                            <img src="{{ $bookingPageSettings['banner_url'] }}" alt="Banner trang đặt xe" class="admin-booking-banner-preview rounded border">
                        </div>
                    @endif
                    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu cài đặt</button>
                </form>
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
                            <label class="form-label">Hoa hồng người giới thiệu (%)</label>
                            <div class="input-group">
                                <input type="number" name="referral_commission_first" class="form-control" min="0" max="100" step="0.1"
                                       value="{{ old('referral_commission_first', $feeSettings['referral_commission_first']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Tính vào doanh thu GT khi khách dùng mã admin.</div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Giảm giá mã QR vé (%)</label>
                            <div class="input-group">
                                <input type="number" name="referral_commission_repeat" class="form-control" min="0" max="100" step="0.1"
                                       value="{{ old('referral_commission_repeat', $feeSettings['referral_commission_repeat']) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Chỉ giảm giá trên trang đặt xe, không tính doanh thu.</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h3 class="h6 fw-semibold mb-2">Loại chỗ hiển thị trên form đặt xe</h3>
                    <p class="text-muted small mb-3">Chọn loại xe khách có thể chọn khi đặt. Có thể thêm số chỗ tùy chỉnh (1–60).</p>
                    <div class="row g-2 mb-3">
                        @foreach($vehicleCapacityKnown ?? \App\Support\VehicleCapacityOptions::knownCapacities() as $capacity)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="capacity_enabled[]"
                                       value="{{ $capacity }}" id="cap-enabled-{{ $capacity }}"
                                       @checked(in_array($capacity, $vehicleCapacityEnabled ?? [], true))>
                                <label class="form-check-label" for="cap-enabled-{{ $capacity }}">
                                    {{ \App\Support\VehicleCapacityOptions::label($capacity) }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label">Thêm số chỗ mới</label>
                            <input type="number" name="capacity_custom_add" class="form-control" min="1" max="60" step="1"
                                   placeholder="VD: 45">
                            <div class="form-text">Tick thêm ở trên sau khi lưu lần đầu.</div>
                        </div>
                    </div>

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
                                @foreach($vehicleCapacityEnabled ?? \App\Support\VehicleCapacityOptions::enabled() as $capacity)
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

                @include('partials.screen-tabs-end')
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/referral-qr.js') }}"></script>
@endpush
