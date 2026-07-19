@extends('layouts.console')

@section('console')
@php
$allowedAdminTabs = ['fees', 'settings', 'account', 'appearance'];
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
    : (in_array($tabFromCookie, $allowedAdminTabs, true) ? $tabFromCookie : 'fees');
if ($errors->hasAny(['current_password', 'password', 'password_confirmation'])) {
    $adminDefaultTab = 'account';
}
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
                        ['key' => 'fees', 'label' => 'Tính tiền'],
                        ['key' => 'settings', 'label' => 'Ngân hàng'],
                        ['key' => 'account', 'label' => 'Tài khoản'],
                        ['key' => 'appearance', 'label' => 'Hiển thị'],
                    ],
                ])

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'fees', 'active' => $adminDefaultTab === 'fees'])
                @include('partials.admin-pricing-panel')
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
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.04em">Thương hiệu &amp; App</h3>
                    <form method="POST" action="{{ route('admin.brandingSettings.update') }}" class="console-form" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="branding-app-name">Tên hiển thị</label>
                                <input type="text" name="app_name" id="branding-app-name" class="form-control @error('app_name') is-invalid @enderror"
                                       value="{{ old('app_name', $brandingSettings['app_name']) }}" maxlength="80">
                                @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Dùng cho tiêu đề trang và tên đầy đủ khi ghim app.</div>
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
                            <div class="col-md-6">
                                <label class="form-label" for="branding-pwa-guest-name">Tên app — Khách đặt xe</label>
                                <input type="text" name="pwa_guest_short_name" id="branding-pwa-guest-name" class="form-control @error('pwa_guest_short_name') is-invalid @enderror"
                                       value="{{ old('pwa_guest_short_name', $brandingSettings['pwa_guest_short_name']) }}" maxlength="24">
                                @error('pwa_guest_short_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Tên ngắn trên màn hình chính (mặc định: Đặt xe).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="branding-pwa-driver-name">Tên app — Tài xế</label>
                                <input type="text" name="pwa_driver_short_name" id="branding-pwa-driver-name" class="form-control @error('pwa_driver_short_name') is-invalid @enderror"
                                       value="{{ old('pwa_driver_short_name', $brandingSettings['pwa_driver_short_name']) }}" maxlength="24">
                                @error('pwa_driver_short_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Tên ngắn trên màn hình chính (mặc định: Tài xế).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="branding-app-icon">Biểu tượng app</label>
                                <input type="file" name="app_icon" id="branding-app-icon" class="form-control @error('app_icon') is-invalid @enderror"
                                       accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                                @error('app_icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Ảnh vuông PNG/JPG/WEBP/SVG, tối đa 2 MB. Dùng cho ghim màn hình và thông báo.</div>
                                @if($brandingSettings['has_app_icon'] ?? false)
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_app_icon" value="1" id="branding-remove-app-icon">
                                        <label class="form-check-label" for="branding-remove-app-icon">Xóa biểu tượng hiện tại</label>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">Xem thử trên màn hình chính</label>
                                <div class="admin-pwa-home-preview">
                                    <img src="{{ $brandingSettings['app_icon_url'] ?? asset('favicon.svg') }}" alt="" class="admin-pwa-icon-preview">
                                    <div>
                                        <div class="admin-pwa-home-preview__name">{{ $brandingSettings['pwa_guest_short_name'] }}</div>
                                        <div class="admin-pwa-home-preview__hint text-muted small">Ví dụ: khách ghim app</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu thương hiệu &amp; app</button>
                    </form>
                </div>
                <div class="mb-4 pb-4 border-bottom border-secondary">
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.04em">Âm thanh hộp thư / thông báo</h3>
                    <p class="small text-muted mb-3">Mặc định khi có tin mới trong hộp thư (Thông báo / Thông tin). Tài xế có thể đổi riêng trong Cài đặt âm thanh.</p>
                    <form method="POST" action="{{ route('admin.soundSettings.update') }}" class="console-form">
                        @csrf
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="sound-enabled"
                                   name="enabled" value="1" @checked($soundSettings['enabled'] ?? true)>
                            <label class="form-check-label" for="sound-enabled">Bật âm thanh khi có tin hộp thư mới</label>
                        </div>
                        <div class="row g-2 mb-3">
                            @foreach($soundOptions ?? [] as $toneKey => $toneMeta)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="preset"
                                               value="{{ $toneKey }}" id="sound-preset-{{ $toneKey }}"
                                               @checked(($soundSettings['preset'] ?? 'tone1') === $toneKey)>
                                        <label class="form-check-label small" for="sound-preset-{{ $toneKey }}">
                                            {{ $toneMeta['label'] }}
                                        </label>
                                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline"
                                                data-sound-preview="{{ $toneKey }}">Nghe thử</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button class="btn btn-outline-primary px-4 fw-semibold">Lưu âm thanh</button>
                    </form>
                </div>
                <div class="mb-4 pb-4 border-bottom border-secondary">
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.04em">Thông báo đẩy (PWA)</h3>
                    @unless($pushVapidReady ?? false)
                        <div class="alert alert-warning py-2 small mb-3">
                            Chưa có khóa VAPID. Chạy <code>php artisan pwa:vapid-keys</code> trên server trước khi gửi thông báo.
                        </div>
                    @endunless
                    <form method="POST" action="{{ route('admin.pushSettings.update') }}" class="console-form">
                        @csrf
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="push-enabled"
                                   name="enabled" value="1" @checked($pushSettings['enabled'] ?? true)>
                            <label class="form-check-label" for="push-enabled">Bật thông báo đẩy qua biểu tượng app</label>
                        </div>
                        <div class="row g-2 mb-3">
                            @foreach($pushEventLabels ?? [] as $eventKey => $eventLabel)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="events[]"
                                               value="{{ $eventKey }}" id="push-event-{{ md5($eventKey) }}"
                                               @checked($pushSettings['events'][$eventKey] ?? false)>
                                        <label class="form-check-label small" for="push-event-{{ md5($eventKey) }}">{{ $eventLabel }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button class="btn btn-outline-primary px-4 fw-semibold">Lưu thông báo</button>
                    </form>
                    <details class="mt-3 small text-muted">
                        <summary class="fw-semibold text-secondary">Khóa VAPID (tuỳ chọn)</summary>
                        <p class="mt-2 mb-2">Có thể dán khóa từ server Linux (<code>php artisan pwa:vapid-keys</code>) hoặc đặt biến môi trường <code>VAPID_PUBLIC_KEY</code> / <code>VAPID_PRIVATE_KEY</code>.</p>
                        <form method="POST" action="{{ route('admin.pushSettings.update') }}" class="console-form mt-2">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ ($pushSettings['enabled'] ?? true) ? '1' : '0' }}">
                            @foreach(array_keys($pushSettings['events'] ?? []) as $eventKey)
                                @if($pushSettings['events'][$eventKey] ?? false)
                                    <input type="hidden" name="events[]" value="{{ $eventKey }}">
                                @endif
                            @endforeach
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label" for="vapid-public">Public key</label>
                                    <input type="text" class="form-control form-control-sm" id="vapid-public" name="vapid_public" autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="vapid-private">Private key</label>
                                    <input type="text" class="form-control form-control-sm" id="vapid-private" name="vapid_private" autocomplete="off">
                                </div>
                            </div>
                            <button class="btn btn-sm btn-secondary mt-2">Lưu khóa VAPID</button>
                        </form>
                    </details>
                </div>
                <div class="mb-2">
                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.04em">Trang đặt xe</h3>
                </div>
                <form method="POST" action="{{ route('admin.bookingPageSettings.update') }}" class="console-form" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
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

                @include('partials.screen-tab-pane', ['prefix' => 'admin-main', 'key' => 'account', 'active' => $adminDefaultTab === 'account'])
                @include('partials.admin-password-form')
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tabs-end')
            </div>
        </div>
    </div>
</div>
@endsection
