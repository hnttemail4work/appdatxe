@extends('layouts.console')

@section('console')
@php
$pendingBookings = $pendingBookingsCount ?? 0;
$pendingSettleCount = $pendingSettleCount ?? 0;
@endphp

@include('partials.operator-console-hero')

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.operator-nav-tabs', [
            'active' => 'bookings',
            'pendingBookings' => $pendingBookings,
        ])

        <div class="pt-3">
        @if($passengers->isEmpty())
            <div class="console-empty py-3">
                <p class="mb-0">Chưa có đơn đặt xe nào.</p>
            </div>
        @else
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Hành khách</th>
                            <th>Chuyến</th>
                            <th>Loại</th>
                            <th>Ghế</th>
                            <th>Tổng tiền</th>
                            <th>Giới thiệu</th>
                            <th>Trạng thái</th>
                            <th>Tài xế</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($passengers as $booking)
                        <tr>
                            <td>
                                @include('partials.admin-booking-customer', ['booking' => $booking])
                            </td>
                            <td>
                                <div>{{ $booking->schedule->route->departure }} → {{ $booking->schedule->route->destination }}</div>
                                <div class="cell-muted">{{ $booking->schedule->departure_time->format('H:i, d/m/Y') }}</div>
                                @if($booking->schedule->shortTripCode())
                                    <div class="cell-muted small">Mã chuyến: {{ $booking->schedule->shortTripCode() }}</div>
                                @endif
                            </td>
                            <td class="small">
                                <span class="status-pill status-pill--{{ \App\Support\StatusBadge::bookingMode($booking->booking_mode ?? 'shared') }}">{{ $booking->bookingModeLabel() }}</span>
                            </td>
                            <td>{{ $booking->booking_mode === 'shared' ? $booking->seatCountLabel() : 'Cả xe' }}</td>
                            <td class="fw-semibold">{{ number_format($booking->chargedTotal(), 0, ',', '.') }} đ</td>
                            <td class="small cell-muted">
                                @if($booking->appliedReferralCode)
                                    <span class="driver-meta-code">{{ $booking->appliedReferralCode->code }}</span>
                                    @if($booking->trip_status === 'completed')
                                        <div>{{ number_format($booking->referralCommissionAmount(), 0, ',', '.') }} đ ({{ number_format($booking->appliedReferralCode->commissionPercent(), 1) }}%)</div>
                                    @else
                                        <div class="text-muted">Chờ hoàn tất</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>@include('partials.booking-status-operator', ['booking' => $booking])</td>
                            <td class="small">
                                @include('partials.operator-booking-assign', ['booking' => $booking, 'drivers' => $drivers])
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $passengers])
        @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}">
@endpush
