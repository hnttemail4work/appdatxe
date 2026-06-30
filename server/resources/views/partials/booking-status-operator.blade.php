{{-- Trạng thái + lý do hủy + nút liên hệ (vận hành chuyến) --}}
@php
    $isCancelled = in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        || $booking->trip_status === 'cancelled';
    $cancelledByCustomer = $isCancelled && $booking->cancelled_by === 'customer';
    $cancelledByDriver = $isCancelled && $booking->cancelled_by === 'driver';
    $driverUser = $booking->schedule?->driver;
@endphp

<span class="status-pill status-pill--{{ $booking->operatorMonitorColor() }}">{{ $booking->operatorMonitorLabel() }}</span>

@if($isCancelled)
    <div class="mt-1">@include('partials.booking-cancel-detail', ['booking' => $booking])</div>
@endif

@if($cancelledByCustomer)
    <div class="mt-2">
        <button type="button"
                class="btn btn-sm btn-outline-primary operator-contact-btn"
                data-contact-role="Khách hàng"
                data-contact-name="{{ $booking->passenger_name ?: 'Khách' }}"
                data-contact-phone="{{ $booking->contact_phone }}">
            Liên hệ
        </button>
    </div>
@elseif($cancelledByDriver && $driverUser)
    <div class="mt-2">
        <button type="button"
                class="btn btn-sm btn-outline-primary operator-contact-btn"
                data-contact-role="Tài xế"
                data-contact-name="{{ $driverUser->name }}"
                data-contact-phone="{{ $driverUser->phone }}">
            Liên hệ
        </button>
    </div>
@endif
