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

@php
    $mergeCandidates = (($booking->booking_mode ?? 'shared') === 'shared'
        && ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        && ($schedule?->departure_time?->isFuture() ?? false))
        ? app(\App\Services\TripConsolidationService::class)->mergeCandidatesFor($booking)
        : collect();
    $consolidation = app(\App\Services\TripConsolidationService::class);
@endphp
@if($mergeCandidates->isNotEmpty() && $schedule)
    <div class="operator-merge-schedules mt-2 pt-2 border-top">
        <div class="small text-muted mb-1">Gom ghép xe vào chuyến gần giờ (≤ {{ \App\Services\TripConsolidationService::POOL_WINDOW_MINUTES }} phút)
            @if($schedule->driver_id || $mergeCandidates->contains(fn ($c) => (int) $c->driver_id > 0))
                — <strong>có tài xế thì cần TX xác nhận</strong>
            @endif
            :
        </div>
        @foreach($mergeCandidates as $candidate)
            @php $pendingMerge = $consolidation->pendingMergeForPair($candidate, $schedule); @endphp
            @if($pendingMerge)
                <span class="badge bg-warning text-dark mb-1 me-1">
                    Chờ TX · {{ $candidate->departure_time->format('H:i') }} · {{ $candidate->shortTripCode() }}
                </span>
            @else
            <form method="POST"
                  action="{{ route('operator.schedules.merge', ['target' => $candidate->id, 'source' => $schedule->id]) }}"
                  class="d-inline"
                  data-confirm="Gom toàn bộ khách từ mã {{ $schedule->shortTripCode() }} sang chuyến {{ $candidate->departure_time->format('H:i') }} ({{ $candidate->shortTripCode() }})?{{ ((int) $candidate->driver_id > 0) ? ' Tài xế sẽ được hỏi xác nhận trước.' : '' }}"
                  data-confirm-title="Gom chuyến"
                  data-confirm-ok="Gom">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary mb-1 me-1">
                    {{ $candidate->departure_time->format('H:i') }} · {{ $candidate->shortTripCode() }}
                    · {{ $candidate->activeGuestBookingsCount() }} khách
                    @if($candidate->driver_id)
                        · có TX
                    @endif
                </button>
            </form>
            @endif
        @endforeach
    </div>
@endif
