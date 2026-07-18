@extends('layouts.app')

@section('content')
@php
    $activeTab = $activeTab ?? 'profile';
@endphp

<div class="customer-page customer-account-page" data-customer-tabs data-customer-tabs-active="{{ $activeTab }}" data-customer-tabs-base="{{ route('customer.account') }}">
    <header class="customer-account-hero mb-3">
        <div class="customer-account-hero__avatar" aria-hidden="true">
            {{ $user->avatarInitial() }}
        </div>
        <div class="customer-account-hero__copy">
            <p class="customer-account-hero__eyebrow">Tài khoản khách</p>
            <h1 class="customer-account-hero__title">{{ $profile['name'] ?? $user->preferredDisplayName() }}</h1>
            <p class="customer-account-hero__meta mb-0">
                <span>{{ $profile['phone'] ?? $user->phone }}</span>
                @if($profile['email'] ?? '')
                    <span class="customer-account-hero__dot">·</span>
                    <span>{{ $profile['email'] }}</span>
                @endif
            </p>
        </div>
    </header>

    @php
        $inboxUnreadTotal = (int) (($inboxUnread['total'] ?? 0));
    @endphp
    <nav class="customer-account-tabs" aria-label="Thông tin tài khoản">
        <a href="{{ route('customer.account', ['tab' => 'profile']) }}"
           class="customer-account-tab {{ $activeTab === 'profile' ? 'is-active' : '' }}"
           data-customer-tab="profile">Hồ sơ</a>
        <a href="{{ route('customer.account', ['tab' => 'inbox']) }}"
           class="customer-account-tab {{ $activeTab === 'inbox' ? 'is-active' : '' }}"
           data-customer-tab="inbox">
            Hộp thư
            @if($inboxUnreadTotal > 0)
                <span class="customer-account-tab__badge">{{ $inboxUnreadTotal > 99 ? '99+' : $inboxUnreadTotal }}</span>
            @endif
        </a>
        <a href="{{ route('customer.account', ['tab' => 'trips']) }}"
           class="customer-account-tab {{ $activeTab === 'trips' ? 'is-active' : '' }}"
           data-customer-tab="trips">Lịch sử chuyến</a>
        <a href="{{ route('customer.account', ['tab' => 'reviews']) }}"
           class="customer-account-tab {{ $activeTab === 'reviews' ? 'is-active' : '' }}"
           data-customer-tab="reviews">Đánh giá</a>
    </nav>

    <div class="customer-account-panels">
        <section class="customer-account-panel {{ $activeTab === 'profile' ? 'is-active' : '' }}" data-customer-panel="profile">
            <div class="customer-account-card">
                <h2 class="customer-account-card__title">Thông tin cá nhân</h2>
                <dl class="customer-account-dl">
                    <div>
                        <dt>Họ và tên</dt>
                        <dd>{{ $profile['name'] ?? $user->preferredDisplayName() }}</dd>
                    </div>
                    <div>
                        <dt>Số điện thoại</dt>
                        <dd>{{ $profile['phone'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Tuổi</dt>
                        <dd>{{ ($profile['age'] ?? null) ? $profile['age'] . ' tuổi' : 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Giới tính</dt>
                        <dd>{{ $profile['gender_label'] ?? 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Gmail</dt>
                        <dd>{{ $profile['email'] ?: 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Số CCCD</dt>
                        <dd>{{ $profile['id_number'] ?: 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Địa chỉ</dt>
                        <dd>{{ $profile['address'] ?: 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Sinh trắc học</dt>
                        <dd>{{ ($profile['has_biometric'] ?? false) ? 'Đã thiết lập' : 'Chưa thiết lập' }}</dd>
                    </div>
                    <div>
                        <dt>Tổng chuyến</dt>
                        <dd>{{ number_format($profile['trip_count'] ?? 0) }}</dd>
                    </div>
                </dl>
                @if(($profile['photo_id_card_url'] ?? null) || ($profile['photo_id_card_back_url'] ?? null))
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @if($profile['photo_id_card_url'] ?? null)
                        <a href="{{ $profile['photo_id_card_url'] }}" target="_blank" rel="noopener" class="small">CCCD trước</a>
                    @endif
                    @if($profile['photo_id_card_back_url'] ?? null)
                        <a href="{{ $profile['photo_id_card_back_url'] }}" target="_blank" rel="noopener" class="small">CCCD sau</a>
                    @endif
                </div>
                @endif
            </div>

            @include('partials.customer-profile-update-form', [
                'user' => $user,
                'pendingChange' => $pendingChange ?? null,
            ])

            @if(($recentTrips ?? collect())->isNotEmpty())
            <div class="customer-account-card mt-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="customer-account-card__title mb-0">Chuyến gần đây</h2>
                    <a href="{{ route('customer.account', ['tab' => 'trips']) }}" class="small">Xem tất cả</a>
                </div>
                @foreach($recentTrips as $trip)
                    @include('partials.customer-trip-card', ['trip' => $trip])
                @endforeach
            </div>
            @endif

            <div class="customer-account-card mt-3">
                <h2 class="customer-account-card__title">Bảo mật</h2>
                <p class="small text-muted mb-2">Đăng nhập bằng SĐT và PIN 6 số.</p>
                <a href="{{ route('password.reset.request') }}" class="btn btn-sm btn-outline-secondary">Đặt lại PIN</a>
            </div>

            <div class="customer-account-card mt-3">
                @include('partials.logout-button', ['class' => 'btn btn-outline-danger w-100'])
            </div>
        </section>

        <section class="customer-account-panel {{ $activeTab === 'trips' ? 'is-active' : '' }}" data-customer-panel="trips">
            @if(($tripHistory ?? null) && $tripHistory->count())
                @foreach($tripHistory as $trip)
                    @include('partials.customer-trip-card', ['trip' => $trip])
                @endforeach
                <div class="mt-3">{{ $tripHistory->withQueryString()->links() }}</div>
            @else
                <div class="customer-account-empty">
                    <p class="mb-2">Chưa có chuyến nào.</p>
                    <a href="{{ route('home') }}" class="btn btn-outline-primary btn-sm">Đặt xe ngay</a>
                </div>
            @endif
        </section>

        <section class="customer-account-panel {{ $activeTab === 'reviews' ? 'is-active' : '' }}" data-customer-panel="reviews">
            @if(($reviews ?? null) && $reviews->count())
                @foreach($reviews as $review)
                    @include('partials.customer-review-card', ['review' => $review])
                @endforeach
                <div class="mt-3">{{ $reviews->withQueryString()->links() }}</div>
            @else
                <div class="customer-account-empty">
                    <p class="mb-0">Chưa có đánh giá nào. Hoàn tất chuyến đi để đánh giá tài xế.</p>
                </div>
            @endif
        </section>

        <section class="customer-account-panel {{ $activeTab === 'inbox' ? 'is-active' : '' }}" data-customer-panel="inbox">
            <div class="customer-account-card">
                <h2 class="customer-account-card__title">Hộp thư</h2>
                @include('partials.customer-tab-inbox', [
                    'inboxTab' => $inboxTab ?? 'notice',
                    'inboxUnread' => $inboxUnread ?? ['info' => 0, 'notice' => 0, 'total' => 0],
                    'inboxNoticeMessages' => $inboxNoticeMessages ?? collect(),
                    'inboxInfoMessages' => $inboxInfoMessages ?? collect(),
                ])
            </div>
        </section>
    </div>
</div>

@include('partials.customer-scroll-dock')
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/customer-account-tabs.js') }}?v={{ filemtime(public_path('js/customer-account-tabs.js')) }}"></script>
<script src="{{ asset('js/customer-inbox.js') }}?v={{ filemtime(public_path('js/customer-inbox.js')) }}"></script>
<script>
document.querySelectorAll('.customer-profile-update-form [data-file-trigger]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = btn.closest('.register-file-field');
        var input = wrap && wrap.querySelector('input[type="file"]');
        if (input) input.click();
    });
});
document.querySelectorAll('.customer-profile-update-form input[type="file"]').forEach(function (input) {
    input.addEventListener('change', function () {
        var wrap = input.closest('.register-file-field');
        var label = wrap && wrap.querySelector('[data-file-name]');
        var preview = wrap && wrap.querySelector('[data-doc-preview]');
        var file = input.files && input.files[0];
        if (label) label.textContent = file ? file.name : 'Chưa chọn';
        if (wrap) wrap.classList.toggle('has-file', !!file);
        if (preview) {
            if (preview.dataset.objectUrl) {
                URL.revokeObjectURL(preview.dataset.objectUrl);
                delete preview.dataset.objectUrl;
            }
            if (file && file.type.indexOf('image/') === 0) {
                var url = URL.createObjectURL(file);
                preview.dataset.objectUrl = url;
                preview.src = url;
                preview.classList.remove('d-none');
            } else {
                preview.removeAttribute('src');
                preview.classList.add('d-none');
            }
        }
    });
});
</script>
@endpush
