@php
/** @var \App\Models\Booking $booking */
$walletAlert = $booking->adminWalletTopUpAlert();
$dispatch = $booking->adminDriverDispatchDetail();
@endphp

<span class="status-pill status-pill--{{ $booking->operatorMonitorColor() }}">
    {{ $booking->operatorMonitorLabel() }}
</span>

@if($dispatch)
    <div class="mt-1 d-flex flex-wrap align-items-center gap-1">
        <span class="status-pill status-pill--{{ $dispatch['color'] }} small">
            {{ $dispatch['label'] }}
        </span>
        @if($dispatch['can_nudge'] ?? false)
            <form method="POST" action="{{ route('admin.bookings.nudgeDriver', $booking) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-warning btn-sm py-0 px-2">Gửi lại TB</button>
            </form>
        @endif
    </div>
@endif

@if(($bookingList ?? '') === 'completed')
@endif

@if($walletAlert)
    <div class="mt-1">
        <span class="status-pill status-pill--{{ $walletAlert['level'] }}">
            {{ $walletAlert['label'] }}
        </span>
    </div>
@endif
