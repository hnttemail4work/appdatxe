@php
    $needsConfirm = $booking->needsOperatorConfirmation();
    $awaitingDriver = ! $needsConfirm && ! $booking->hasDriverAccepted()
        && ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        && ! $booking->isExpired();
    $canReassign = $booking->hasDriverAccepted()
        && $booking->schedule->departure_time > now()
        && ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        && $booking->trip_status !== 'completed';
    $schedule = $booking->schedule;
    $lockedDriver = $schedule?->designatedDriverProfile();
    $ownPending = $schedule
        ? $schedule->driverTripRequests
            ->first(fn ($r) => $r->isPending()
                && $booking->matchesContactPhone((string) $r->contact_phone))
        : null;
    $currentDriverId = (int) ($schedule->driver_id ?? 0);
@endphp

@if($needsConfirm || $awaitingDriver)
    @if($ownPending)
        <span class="small text-muted">Chờ {{ $ownPending->driver?->name ?? 'tài xế' }} phản hồi</span>
    @else
    <form method="POST" action="{{ route('operator.bookings.assign', $booking) }}" class="d-flex flex-wrap gap-1 align-items-center">
        @csrf
        @if($lockedDriver && $lockedDriver->driver_code)
            <input type="hidden" name="driver_code" value="{{ $lockedDriver->driver_code }}">
            <span class="small text-nowrap">
                {{ $lockedDriver->user->name }}
                <span class="text-muted">(cùng chuyến)</span>
            </span>
        @else
            <select name="driver_code" class="form-select form-select-sm" style="min-width: 7rem; max-width: 9rem;" required>
                <option value="">Tài xế</option>
                @foreach($drivers as $d)
                    @if($d->driver_code && (int) $d->user_id !== $currentDriverId)
                        <option value="{{ $d->driver_code }}">{{ $d->user->name }}</option>
                    @endif
                @endforeach
            </select>
        @endif
        <button type="submit" class="btn btn-primary btn-sm text-nowrap">
            {{ $needsConfirm ? 'Xác nhận' : 'Giao lại' }}
        </button>
    </form>
    @endif
@elseif($canReassign)
    <form method="POST" action="{{ route('operator.bookings.assign', $booking) }}" class="d-flex flex-wrap gap-1 align-items-center">
        @csrf
        <select name="driver_code" class="form-select form-select-sm" style="min-width: 7rem; max-width: 9rem;" required>
            <option value="">Tài xế</option>
            @foreach($drivers as $d)
                @if($d->driver_code && (int) $d->user_id !== $currentDriverId)
                    <option value="{{ $d->driver_code }}">{{ $d->user->name }}</option>
                @endif
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm text-nowrap">Đổi TX</button>
    </form>
@elseif($booking->schedule->driver)
    {{ $booking->schedule->driver->name }}
@else
    <span class="text-muted">—</span>
@endif
