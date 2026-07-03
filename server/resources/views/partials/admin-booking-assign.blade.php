@php
    use App\Services\DriverProximityService;

    $bookingList = $bookingList ?? 'active';
    $canReassign = $bookingList === 'active'
        && $booking->hasDriverAccepted()
        && $booking->schedule->departure_time > now()
        && ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        && $booking->trip_status !== 'completed';
    $schedule = $booking->schedule;
    $currentDriverId = (int) ($schedule->driver_id ?? 0);
    $proximity = app(DriverProximityService::class);
@endphp

@if($canReassign)
    <form method="POST" action="{{ route('admin.bookings.assign', $booking) }}" class="d-flex flex-wrap gap-1 align-items-center">
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
    @php
        $assignedProfile = $booking->schedule->assignedDriverProfile
            ?? $booking->schedule->driver?->driverProfile;
        $distanceText = $booking->driver_pickup_distance_km !== null
            ? DriverProximityService::formatDistanceLabel((float) $booking->driver_pickup_distance_km) . ' lúc nhận chuyến'
            : null;
    @endphp
    @if($assignedProfile)
        @include('partials.booking-driver-brief', [
            'profile' => $assignedProfile,
            'distanceLabel' => $distanceText,
            'compact' => true,
        ])
    @else
        {{ $booking->schedule->driver->name }}
        @if($distanceText)
            <span class="small text-muted">({{ $distanceText }})</span>
        @endif
    @endif
@else
    <span class="text-muted">—</span>
@endif
