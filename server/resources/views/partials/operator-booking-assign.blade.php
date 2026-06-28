@php
    $needsConfirm = $booking->needsOperatorConfirmation();
    $awaitingDriver = ! $needsConfirm && ! $booking->hasDriverAccepted()
        && ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        && ! $booking->isExpired();
@endphp

@if($needsConfirm || $awaitingDriver)
<form method="POST" action="{{ route('operator.bookings.assign', $booking) }}" class="d-flex flex-wrap gap-1 align-items-center">
    @csrf
    <select name="driver_code" class="form-select form-select-sm" style="min-width: 7rem; max-width: 9rem;" required>
        <option value="">Tài xế</option>
        @foreach($drivers as $d)
            @if($d->driver_code)
                <option value="{{ $d->driver_code }}">{{ $d->user->name }}</option>
            @endif
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm text-nowrap">
        {{ $needsConfirm ? 'Xác nhận' : 'Giao lại' }}
    </button>
</form>
@elseif($booking->schedule->driver)
    {{ $booking->schedule->driver->name }}
@else
    <span class="text-muted">—</span>
@endif
