@extends('layouts.app')

@section('content')
@php
use App\Support\AppBrandingSettings;

$brandName = AppBrandingSettings::appName();
$brandTitle = AppBrandingSettings::brandTitle();
$platformHotlinePhone = (string) ($platformHotlinePhone ?? config('app.contact_phone'));
$hotlineDigits = preg_replace('/\D+/', '', $platformHotlinePhone);
$hotlineTel = preg_replace('/[^\d+]/', '', $platformHotlinePhone);
$email = (string) config('app.contact_email');
@endphp

<div class="customer-page customer-about-page">
    <div class="customer-hero mb-4">
        <h1>Về chúng tôi</h1>
        <p class="mb-0">{{ $brandTitle }} — {{ AppBrandingSettings::brandTagline() }}</p>
    </div>

    <div class="customer-about-card">
        <h2 class="h5 mb-3">{{ $brandName }}</h2>
        <p class="text-muted mb-3">
            {{ $brandName }} là nền tảng đặt xe liên tỉnh, kết nối hành khách với đội ngũ tài xế đã được duyệt hồ sơ và xe rõ ràng thông tin.
            Chúng tôi hướng tới trải nghiệm đặt chuyến minh bạch, nhanh và an tâm trên mọi hành trình.
        </p>
        <ul class="customer-about-list mb-0">
            <li>Chọn xe và tài xế phù hợp ngay trên trang đặt xe</li>
            <li>Theo dõi trạng thái chuyến và liên hệ tổng đài khi cần hỗ trợ</li>
            <li>Đội ngũ vận hành hỗ trợ xử lý các tình huống phát sinh</li>
        </ul>
    </div>

    <div class="customer-about-card mt-3">
        <h2 class="h6 mb-3">Liên hệ</h2>
        @if($hotlineDigits !== '')
            <p class="mb-2"><strong>Tổng đài:</strong> <a href="tel:{{ $hotlineTel }}">{{ $platformHotlinePhone }}</a></p>
        @endif
        @if($email !== '')
            <p class="mb-0"><strong>Email:</strong> <a href="mailto:{{ $email }}">{{ $email }}</a></p>
        @endif
    </div>

    <div class="mt-4">
        <a href="{{ route('home') }}" class="btn btn-outline-primary">← Quay lại đặt xe</a>
    </div>

    @include('partials.customer-contact-fab', [
        'hotlinePhone' => $platformHotlinePhone,
        'variant' => 'fixed',
    ])
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<style>
.customer-about-page { max-width: 42rem; margin: 0 auto; }
.customer-about-card {
    padding: 1.15rem 1.2rem;
    border-radius: .85rem;
    border: 1px solid var(--tl-border);
    background: var(--tl-surface);
}
.customer-about-list {
    padding-left: 1.1rem;
    color: var(--tl-text-muted);
}
.customer-about-list li + li { margin-top: .35rem; }
.customer-about-card a { color: var(--tl-primary-light); }
</style>
@endpush
