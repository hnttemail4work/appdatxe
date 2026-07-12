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
            <h1 class="customer-account-hero__title">{{ $user->name }}</h1>
            <p class="customer-account-hero__meta mb-0">
                <span>{{ $profile['phone'] ?? $user->phone }}</span>
                @if($profile['email'] ?? '')
                    <span class="customer-account-hero__dot">·</span>
                    <span>{{ $profile['email'] }}</span>
                @endif
            </p>
        </div>
    </header>

    <nav class="customer-account-tabs" aria-label="Thông tin tài khoản">
        <a href="{{ route('customer.account', ['tab' => 'profile']) }}"
           class="customer-account-tab {{ $activeTab === 'profile' ? 'is-active' : '' }}"
           data-customer-tab="profile">Thông tin</a>
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
                        <dd>{{ $profile['name'] ?? $user->name }}</dd>
                    </div>
                    <div>
                        <dt>Số điện thoại</dt>
                        <dd>{{ $profile['phone'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Tuổi</dt>
                        <dd>{{ ($profile['age'] ?? null) ? $profile['age'] . ' tuổi' : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Giới tính</dt>
                        <dd>{{ $profile['gender_label'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Gmail</dt>
                        <dd>{{ $profile['email'] ?: 'Chưa cập nhật' }}</dd>
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
            </div>

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
    </div>
</div>

@include('partials.customer-scroll-dock')
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/customer-account-tabs.js') }}?v={{ filemtime(public_path('js/customer-account-tabs.js')) }}"></script>
@endpush
