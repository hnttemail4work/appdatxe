@extends('layouts.console')

@section('console')
@php
/** @var array{trips: int, revenue: int, app_fee: int, referral_commission: int, app_percent: float, referral_percent_default: float} $summary */
/** @var \Illuminate\Support\Collection $referrerRows */
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $completedTrips */
$summary = $summary ?? [
    'trips' => 0, 'revenue' => 0, 'revenue_before_discount' => 0, 'referral_discount' => 0,
    'surcharges' => 0, 'tolls' => 0, 'app_fee' => 0, 'referral_commission' => 0,
    'app_percent' => 0, 'referral_percent_default' => 8,
];
$referrerRows = $referrerRows ?? collect();
$completedTrips = $completedTrips ?? collect();
@endphp

@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'revenue'])

        <div class="pt-3">
            <div class="console-panel-head px-0 pt-0">
                <div class="console-panel-head-accent">
                    <h2>Doanh thu đặt xe thành công</h2>
                </div>
            </div>

            <div class="console-stats admin-revenue-stats mb-4">
                <div class="console-stat">
                    <div class="console-stat-icon primary" aria-hidden="true">🚗</div>
                    <div>
                        <div class="console-stat-value">{{ number_format($summary['trips']) }}</div>
                        <div class="console-stat-label">Chuyến hoàn tất</div>
                    </div>
                </div>
                <div class="console-stat">
                    <div class="console-stat-icon success" aria-hidden="true">₫</div>
                    <div>
                        <div class="console-stat-value">{{ number_format($summary['revenue'], 0, ',', '.') }}</div>
                        <div class="console-stat-label">Doanh thu vé (sau giảm)</div>
                    </div>
                </div>
                <div class="console-stat">
                    <div class="console-stat-icon info" aria-hidden="true">₫</div>
                    <div>
                        <div class="console-stat-value">{{ number_format($summary['revenue_before_discount'] ?? $summary['revenue'], 0, ',', '.') }}</div>
                        <div class="console-stat-label">Trước giảm giá</div>
                    </div>
                </div>
                <div class="console-stat">
                    <div class="console-stat-icon warning" aria-hidden="true">−</div>
                    <div>
                        <div class="console-stat-value">{{ number_format($summary['referral_discount'] ?? 0, 0, ',', '.') }}</div>
                        <div class="console-stat-label">Tổng giảm giá QR</div>
                    </div>
                </div>
                <div class="console-stat">
                    <div class="console-stat-icon info" aria-hidden="true">+</div>
                    <div>
                        <div class="console-stat-value">{{ number_format(($summary['surcharges'] ?? 0) + ($summary['tolls'] ?? 0), 0, ',', '.') }}</div>
                        <div class="console-stat-label">Phụ phí + thu phí</div>
                    </div>
                </div>
                <div class="console-stat">
                    <div class="console-stat-icon info" aria-hidden="true">%</div>
                    <div>
                        <div class="console-stat-value">{{ number_format($summary['app_fee'], 0, ',', '.') }}</div>
                        <div class="console-stat-label">Phí app ({{ rtrim(rtrim(number_format($summary['app_percent'], 1, '.', ''), '0'), '.') }}%)</div>
                    </div>
                </div>
                <div class="console-stat">
                    <div class="console-stat-icon warning" aria-hidden="true">GT</div>
                    <div>
                        <div class="console-stat-value">{{ number_format($summary['referral_commission'], 0, ',', '.') }}</div>
                        <div class="console-stat-label">Hoa hồng GT (mặc định {{ rtrim(rtrim(number_format($summary['referral_percent_default'], 1, '.', ''), '0'), '.') }}%)</div>
                    </div>
                </div>
            </div>

            @include('partials.admin-revenue-referrer-table', ['referrerRows' => $referrerRows])

            <div class="console-panel-head px-0 pt-4 mt-2">
                <div class="console-panel-head-accent">
                    <h2>Chi tiết từng chuyến</h2>
                </div>
            </div>

            @include('partials.admin-revenue-trip-table', ['completedTrips' => $completedTrips])
        </div>
    </div>
</div>
@endsection
