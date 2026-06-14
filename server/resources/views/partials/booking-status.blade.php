{{-- Trạng thái thanh toán --}}

@php

    $pendingClaim = $booking->hasPendingPaymentClaim();

@endphp

@if($booking->payment_status === 'paid')

    <span class="badge bg-success">Đã thanh toán</span>

@elseif($pendingClaim)

    <span class="badge bg-info text-dark">Chờ QL xác nhận TT</span>

@else

    <span class="badge bg-warning text-dark">Chưa thanh toán</span>

@endif



{{-- Trạng thái booking / chuyến --}}

@php

    $tripLabel = match($booking->trip_status) {

        'completed'            => 'Hoàn tất',

        'awaiting_completion'    => 'Chờ KH xác nhận hoàn',

        'cancelled'              => 'Đã hủy',

        'confirmed'              => 'Đang chạy / sắp chạy',

        default                  => null,

    };

    $tripColor = match($booking->trip_status) {

        'completed'         => 'success',

        'awaiting_completion' => 'info text-dark',

        'cancelled'         => 'danger',

        'confirmed'         => 'primary',

        default             => 'secondary',

    };



    $bookingLabel = match($booking->booking_status) {

        'confirmed' => $tripLabel ?? 'Đã duyệt chuyến',

        'cancelled' => 'Đã hủy',

        'rejected'  => 'Từ chối',

        default     => $booking->payment_status === 'paid' ? 'Chờ duyệt chuyến' : 'Chờ thanh toán',

    };

    $bookingColor = match($booking->booking_status) {

        'confirmed' => $tripColor,

        'cancelled', 'rejected' => 'danger',

        default => 'warning text-dark',

    };

@endphp

<span class="badge bg-{{ $bookingColor }} mt-1">{{ $bookingLabel }}</span>

