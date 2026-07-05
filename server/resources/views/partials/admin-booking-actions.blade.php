@php
    use App\Services\DriverProximityService;

    $schedule = $booking->schedule;
    $activeDriverId = (int) ($booking->resolveAssignedDriverId($schedule) ?? 0);
    $activeProfile = $booking->activeDriverProfile();
    $proximity = app(DriverProximityService::class);
    $isActiveTrip = ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        && $booking->trip_status !== 'completed';
    $canModify = $isActiveTrip && $booking->adminCanModifyDriverOrCancel();
    $canReassign = $canModify
        && $booking->hasDriverAccepted()
        && $schedule->departure_time > now();
    $canAssign = $canModify && ! $booking->hasDriverAccepted();
    $chosenProfile = $booking->catalogChosenDriverProfile();
    $chosenCode = $chosenProfile?->driver_code ? strtoupper(trim($chosenProfile->driver_code)) : '';
    $activeCode = $activeProfile?->driver_code ? strtoupper(trim($activeProfile->driver_code)) : '';
    $selectedCode = $activeCode !== '' ? $activeCode : $chosenCode;
    $waitMinutes = $booking->adminWaitingMinutesRemaining();
@endphp

<div class="admin-booking-actions">
    @if($waitMinutes)
        <div class="admin-booking-wait-hint mb-2">
            Chờ tài xế · còn ~{{ $waitMinutes }} phút
        </div>
    @endif

    @if($isActiveTrip && $booking->passengerPickedUp())
        <div class="cell-muted small mb-0">
            Đã đón khách — không thể đổi TX
        </div>
    @elseif($canReassign || $canAssign)
        <form method="POST"
              action="{{ route('admin.bookings.assign', $booking) }}"
              class="admin-booking-action-form mb-2">
            @csrf
            <select name="driver_code"
                    class="form-select form-select-sm admin-booking-action-select"
                    required
                    aria-label="Chọn tài xế">
                <option value="">{{ $canReassign ? 'Tài xế mới' : 'Chọn tài xế' }}</option>
                @foreach($drivers as $d)
                    @if($d->driver_code && (int) $d->user_id !== $activeDriverId)
                        @php
                            $code = strtoupper(trim($d->driver_code));
                            $diag = $schedule
                                ? $proximity->assignDiagnostics($d, $booking, $schedule)
                                : ['distance_label' => null, 'hint' => null];
                            $isSelected = $selectedCode !== '' && $code === $selectedCode;
                        @endphp
                        <option value="{{ $d->driver_code }}" @selected($isSelected && ! $canReassign)>
                            {{ $d->user->name }}
                            @if($diag['distance_label'])
                                — {{ $diag['distance_label'] }}
                            @endif
                        </option>
                    @endif
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm w-100 mt-1">
                {{ $canReassign ? 'Gán lại tài xế' : 'Gán tài xế' }}
            </button>
        </form>
    @endif
</div>
