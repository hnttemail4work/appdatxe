@php
    use App\Services\DriverProximityService;

    $bookingList = $bookingList ?? 'active';
    $inPendingQueue = $booking->isInOperatorPendingQueue();
    $canReassign = $bookingList === 'active'
        && $booking->hasDriverAccepted()
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
    $proximity = app(DriverProximityService::class);
@endphp

@if($bookingList === 'pending' && $inPendingQueue && ! $booking->isTripOverdueStuck())
    @if($ownPending)
        @php
            $pendingProfile = $ownPending->driverProfile;
            $pendingDiag = ($pendingProfile && $schedule)
                ? $proximity->assignDiagnostics($pendingProfile, $booking, $schedule)
                : null;
        @endphp
        <span class="small text-muted">
            Chờ {{ $ownPending->driver?->name ?? 'tài xế' }} phản hồi
            @if($pendingDiag['distance_label'] ?? null)
                · {{ $pendingDiag['distance_label'] }} từ điểm đón
            @endif
        </span>
    @else
    <form method="POST" action="{{ route('operator.bookings.assign', $booking) }}" class="operator-assign-form d-flex flex-wrap gap-1 align-items-center">
        @csrf
        @if($lockedDriver && $lockedDriver->driver_code)
            @php
                $lockedDiag = $schedule
                    ? $proximity->assignDiagnostics($lockedDriver, $booking, $schedule)
                    : null;
            @endphp
            <input type="hidden" name="driver_code" value="{{ $lockedDriver->driver_code }}">
            <span class="small text-nowrap">
                {{ $lockedDriver->user->name }}
                @if($lockedDiag['distance_label'] ?? null)
                    <span class="text-muted">({{ $lockedDiag['distance_label'] }})</span>
                @endif
                <span class="text-muted">(cùng chuyến)</span>
            </span>
        @else
            <select name="driver_code" class="form-select form-select-sm operator-assign-select" style="min-width: 9rem; max-width: 14rem;" required>
                <option value="">Chọn tài xế</option>
                @foreach($drivers as $d)
                    @if($d->driver_code && (int) $d->user_id !== $currentDriverId)
                        @php
                            $diag = $schedule
                                ? $proximity->assignDiagnostics($d, $booking, $schedule)
                                : ['distance_label' => null, 'hint' => null, 'auto_assign_eligible' => false];
                        @endphp
                        <option value="{{ $d->driver_code }}">
                            {{ $d->user->name }}
                            @if($diag['distance_label'])
                                — {{ $diag['distance_label'] }}
                            @endif
                            @if($diag['hint'])
                                ({{ $diag['hint'] }})
                            @endif
                        </option>
                    @endif
                @endforeach
            </select>
        @endif
        <button type="submit" class="btn btn-primary btn-sm text-nowrap">Gán tài xế</button>
    </form>
    @if($booking->pickup_lat === null || $booking->pickup_lng === null)
        <div class="small text-warning mt-1">Đơn thiếu tọa độ đón — hệ thống tự gán có thể không chạy.</div>
    @else
        <div class="small text-muted mt-1">Tài xế dò cuốc trong bán kính {{ (int) \App\Services\DriverProximityService::MAX_SAME_PROVINCE_KM }} km quanh điểm đón, GPS mới ≤ {{ \App\Services\DriverProximityService::LOCATION_MAX_AGE_MINUTES }} phút.</div>
    @endif
    @endif
@elseif($canReassign)
    <form method="POST" action="{{ route('operator.bookings.assign', $booking) }}" class="d-flex flex-wrap gap-1 align-items-center">
        @csrf
        <select name="driver_code" class="form-select form-select-sm" style="min-width: 9rem; max-width: 14rem;" required>
            <option value="">Chọn tài xế</option>
            @foreach($drivers as $d)
                @if($d->driver_code && (int) $d->user_id !== $currentDriverId)
                    @php
                        $diag = $schedule
                            ? $proximity->assignDiagnostics($d, $booking, $schedule)
                            : ['distance_label' => null, 'hint' => null];
                    @endphp
                    <option value="{{ $d->driver_code }}">
                        {{ $d->user->name }}
                        @if($diag['distance_label'])
                            — {{ $diag['distance_label'] }}
                        @endif
                    </option>
                @endif
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm text-nowrap">Đổi TX</button>
    </form>
@elseif($booking->schedule->driver)
    {{ $booking->schedule->driver->name }}
    @if($booking->driver_pickup_distance_km !== null)
        <span class="small text-muted">({{ DriverProximityService::formatDistanceLabel((float) $booking->driver_pickup_distance_km) }} lúc nhận chuyến)</span>
    @endif
@else
    <span class="text-muted">—</span>
@endif
