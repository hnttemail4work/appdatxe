@php
/** @var \App\Models\Booking $booking */
$walletAlert = $booking->adminWalletTopUpAlert();
@endphp

<span class="status-pill status-pill--{{ $booking->operatorMonitorColor() }}">
    {{ $booking->operatorMonitorLabel() }}
</span>

@if($walletAlert)
    <div class="mt-1">
        <span class="status-pill status-pill--{{ $walletAlert['level'] }}">
            {{ $walletAlert['label'] }}
        </span>
    </div>
@endif
