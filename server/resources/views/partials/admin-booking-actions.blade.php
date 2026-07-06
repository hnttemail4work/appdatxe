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
        && $booking->driverAcceptanceState() === 'accepted';
    $canAssign = $canModify && (
        in_array($booking->driverAcceptanceState(), ['none', 'pending'], true)
        || $booking->needs_operator_help_at
    );
    $canCancelAfterTimeout = $canModify && $booking->adminCanCancelAfterInviteTimeout();
    $chosenProfile = $booking->catalogChosenDriverProfile();
    $chosenCode = $chosenProfile?->driver_code ? strtoupper(trim($chosenProfile->driver_code)) : '';
    $activeCode = $activeProfile?->driver_code ? strtoupper(trim($activeProfile->driver_code)) : '';
    $selectedCode = $canReassign ? $chosenCode : ($activeCode !== '' ? $activeCode : $chosenCode);
    $waitMinutes = $booking->adminWaitingMinutesRemaining();
@endphp

<div class="admin-booking-actions">
    @if($waitMinutes)
        <div class="admin-booking-wait-hint mb-2">
            Chờ tài xế · còn ~{{ $waitMinutes }} phút
        </div>
    @elseif($canCancelAfterTimeout)
        <div class="admin-booking-wait-hint admin-booking-wait-hint--expired mb-2">
            TX không nhận trong 15 phút — gán lại hoặc hủy chuyến
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
                <option value="">Chọn tài xế</option>
                @foreach($drivers as $d)
                    @if($d->driver_code)
                        @php
                            $code = strtoupper(trim($d->driver_code));
                            $includeDriver = (int) $d->user_id !== $activeDriverId
                                || ($canReassign && $chosenCode !== '' && $code === $chosenCode);
                            $diag = $schedule
                                ? $proximity->assignDiagnostics($d, $booking, $schedule)
                                : ['distance_label' => null, 'hint' => null];
                            $isSelected = $selectedCode !== '' && $code === $selectedCode;
                        @endphp
                        @if($includeDriver)
                        <option value="{{ $d->driver_code }}" @selected($isSelected)>
                            {{ $d->user->name }}
                            @if($diag['distance_label'])
                                — {{ $diag['distance_label'] }}
                            @endif
                        </option>
                        @endif
                    @endif
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm w-100 mt-1">
                {{ $canReassign ? 'Gán lại tài xế' : 'Gán tài xế' }}
            </button>
        </form>
    @endif

    @if($canCancelAfterTimeout)
        <form method="POST"
              action="{{ route('admin.bookings.cancel', $booking) }}"
              class="admin-booking-action-form"
              data-confirm="Hủy chuyến này? Khách và tài xế sẽ không còn thấy trên app."
              data-confirm-title="Hủy chuyến"
              data-confirm-variant="danger"
              data-confirm-ok="Hủy chuyến">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                Hủy chuyến
            </button>
        </form>
    @endif
</div>
