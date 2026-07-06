@php
    use App\Services\DriverProximityService;

    $bookingList = $bookingList ?? 'active';
    $displayOnly = (bool) ($displayOnly ?? false);

    $schedule = $booking->schedule;

    $activeProfile = $booking->activeDriverProfile();

    $chosenProfile = $booking->catalogChosenDriverProfile();

@endphp



@if($activeProfile)
    @include('partials.admin-booking-driver-code', ['profile' => $activeProfile])
@elseif($chosenProfile)
    @include('partials.admin-booking-driver-code', ['profile' => $chosenProfile])
@else
    <span class="text-muted">Chưa có tài xế</span>
@endif



@if(! $displayOnly)

    @php

        $activeDriverId = (int) ($booking->resolveAssignedDriverId($schedule) ?? 0);

        $proximity = app(DriverProximityService::class);

        $isActiveTrip = $bookingList === 'active'

            && ! in_array($booking->booking_status, ['cancelled', 'rejected'], true)

            && $booking->trip_status !== 'completed';

        $canReassign = $isActiveTrip
            && $booking->adminCanModifyDriverOrCancel()
            && $booking->driverAcceptanceState() === 'accepted';

        $canAssign = $isActiveTrip
            && $booking->adminCanModifyDriverOrCancel()
            && (
                in_array($booking->driverAcceptanceState(), ['none', 'pending'], true)
                || $booking->needs_operator_help_at
            );

        $chosenCode = $chosenProfile?->driver_code ? strtoupper(trim($chosenProfile->driver_code)) : '';

        $activeCode = $activeProfile?->driver_code ? strtoupper(trim($activeProfile->driver_code)) : '';

        $selectedCode = $canReassign ? $chosenCode : ($activeCode !== '' ? $activeCode : $chosenCode);

    @endphp



    @if($canReassign)

        <form method="POST" action="{{ route('admin.bookings.assign', $booking) }}" class="d-flex flex-wrap gap-1 align-items-center mt-2">

            @csrf

            <select name="driver_code" class="form-select form-select-sm" style="min-width: 9rem; max-width: 14rem;" required>

                <option value="">Chọn tài xế</option>

                @foreach($drivers as $d)

                    @if($d->driver_code)

                        @php

                            $code = strtoupper(trim($d->driver_code));

                            $includeDriver = (int) $d->user_id !== $activeDriverId
                                || ($chosenCode !== '' && $code === $chosenCode);

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

            <button type="submit" class="btn btn-primary btn-sm text-nowrap">Gán lại TX</button>

        </form>

    @elseif($canAssign)

        <form method="POST" action="{{ route('admin.bookings.assign', $booking) }}" class="d-flex flex-column gap-1 mt-2">

            @csrf

            <div class="d-flex flex-wrap gap-1 align-items-center">

                <select name="driver_code" class="form-select form-select-sm" style="min-width: 9rem; max-width: 14rem;" required>

                    <option value="">Chọn tài xế</option>

                    @foreach($drivers as $d)

                        @if($d->driver_code)

                            @php

                                $code = strtoupper(trim($d->driver_code));

                                $diag = $schedule

                                    ? $proximity->assignDiagnostics($d, $booking, $schedule)

                                    : ['distance_label' => null, 'hint' => null];

                                $isSelected = $selectedCode !== '' && $code === $selectedCode;

                            @endphp

                            <option value="{{ $d->driver_code }}" @selected($isSelected)>

                                {{ $d->user->name }}

                                @if($diag['distance_label'])

                                    — {{ $diag['distance_label'] }}

                                @endif

                            </option>

                        @endif

                    @endforeach

                </select>

                <button type="submit" class="btn btn-primary btn-sm text-nowrap">Giao TX</button>

            </div>

        </form>

    @endif

@endif

