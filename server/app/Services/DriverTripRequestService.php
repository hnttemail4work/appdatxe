<?php

namespace App\Services;

use App\Support\VehicleDisplay;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\SeatReservation;
use App\Models\TripLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DriverTripRequestService
{
    public const ACCEPT_WINDOW_DAYS = 1;

    public const ACCEPT_TIMEOUT_MINUTES = 15;

    /** Sau bao lâu không có tài xế nhận cuốc thì chuyển quản lý hỗ trợ gán thủ công. */
    public const OPERATOR_ESCALATION_MINUTES = 15;

    public const HELP_SEARCH_TIMEOUT = Booking::HELP_SEARCH_TIMEOUT;

    public const HELP_NO_DRIVER_IN_PROVINCE = Booking::HELP_NO_DRIVER_IN_PROVINCE;

    public const HELP_DRIVER_DECLINED = Booking::HELP_DRIVER_DECLINED;

    public function __construct(
        private readonly DriverAvailabilityService $availability,
        private readonly DriverWalletService $wallets,
        private readonly TripLedgerService $tripLedger,
        private readonly DriverProximityService $proximity,
    ) {
    }

    public function expireStale(): void
    {
        DriverTripRequest::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['schedule.route', 'schedule.vehicle'])
            ->each(function (DriverTripRequest $request): void {
                $request->update([
                    'status'       => 'expired',
                    'responded_at' => now(),
                ]);
                $this->tryReassignAfterDecline($request);
            });

        $this->escalateDriverSearchTimeouts();
    }

    /** @deprecated Không khôi phục yêu cầu hết hạn — chuyển sang tài xế khác. */
    public function restoreExpiredForDriver(int $driverUserId): void
    {
    }

    /** @return Collection<int, DriverProfile> */
    public function suggestDrivers(?Schedule $schedule = null): Collection
    {
        $query = DriverProfile::query()
            ->operational()
            ->with(['user', 'operator'])
            ->orderByDesc('experience_years');

        return $query->get();
    }

    public function availabilityMeta(string $status): array
    {
        return match ($status) {
            'available' => ['label' => 'Sẵn sàng', 'color' => 'success', 'icon' => '🟢', 'suggested' => true],
            'on_trip'   => ['label' => 'Đang chạy', 'color' => 'primary', 'icon' => '🔵', 'suggested' => false],
            default     => ['label' => 'Nghỉ / Bận', 'color' => 'secondary', 'icon' => '⚫', 'suggested' => false],
        };
    }

    public function requestDriver(Schedule $schedule, string $driverCode, string $contactPhone): DriverTripRequest
    {
        $this->expireStale();

        $contactPhone = trim($contactPhone);
        if ($contactPhone === '') {
            throw new InvalidArgumentException('Thiếu số điện thoại liên hệ.');
        }

        if (! in_array($schedule->status, ['scheduled', 'running'], true)) {
            throw new InvalidArgumentException('Chuyến không còn mở để mời tài xế.');
        }

        $schedule->loadMissing('route');

        $profile = DriverProfile::query()
            ->operational()
            ->where('driver_code', strtoupper(trim($driverCode)))
            ->with('user')
            ->first();

        if (! $profile) {
            throw new InvalidArgumentException('Không tìm thấy tài xế với mã này.');
        }

        $designated = $schedule->designatedDriverProfile();
        if ($designated && (int) $designated->user_id !== (int) $profile->user_id) {
            $designated->loadMissing('user');
            throw new InvalidArgumentException(
                'Chuyến này đã được giao cho tài xế ' . $designated->user->name
                . '. Các khách ghép cùng chuyến phải dùng một tài xế.'
            );
        }

        $alreadyOnTrip = $schedule->driver_id && (int) $schedule->driver_id === (int) $profile->user_id;

        if (! $alreadyOnTrip && $this->availability->isDriverBusyForSlot(
            (int) $profile->user_id,
            $schedule->route->departure,
            $schedule->route->destination,
            $schedule->departure_time,
        )) {
            throw new InvalidArgumentException('Tài xế đã full ghế khung giờ này. Vui lòng chọn tài xế khác.');
        }

        if (! $alreadyOnTrip) {
            $blockReason = $this->wallets->acceptBlockReason($profile);
            if ($blockReason) {
                throw new InvalidArgumentException('Tài xế không thể nhận cuốc: ' . $blockReason);
            }
        }

        if ($schedule->driver_id) {
            if ((int) $schedule->driver_id === (int) $profile->user_id) {
                if ($schedule->bookedSeatsCount() >= $schedule->capacity()) {
                    throw new InvalidArgumentException('Chuyến này đã full ghế.');
                }

                return tap(
                    DriverTripRequest::query()->firstOrCreate(
                        [
                            'schedule_id'   => $schedule->id,
                            'contact_phone' => $contactPhone,
                            'driver_id'     => $profile->user_id,
                        ],
                        [
                            'status'       => 'accepted',
                            'responded_at' => now(),
                            'expires_at'   => null,
                        ],
                    ),
                    fn () => $this->stampAssignedDriverOnBooking($schedule, $contactPhone, (int) $profile->user_id),
                );
            }

            throw new InvalidArgumentException('Chuyến này đã có tài xế nhận. Chỉ có thể chọn tài xế đang phục vụ chuyến này.');
        }

        $existing = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('status', 'pending')
            ->where('contact_phone', $contactPhone)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Bạn đang chờ tài xế phản hồi cho chuyến này.');
        }

        $request = DriverTripRequest::query()->create([
            'schedule_id'   => $schedule->id,
            'contact_phone' => $contactPhone,
            'driver_id'     => $profile->user_id,
            'status'        => 'pending',
            'expires_at'    => now()->addMinutes(self::ACCEPT_TIMEOUT_MINUTES),
        ]);

        return $request;
    }

    /** Đánh dấu đơn chờ tài xế chủ động dò — không gửi cuốc tự động. */
    public function markBookingAwaitingDriver(Booking $booking): void
    {
        $booking->loadMissing('schedule');

        if (! $booking->schedule
            || $booking->schedule->driver_id
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if (! $booking->driver_search_started_at) {
            $booking->update([
                'driver_search_started_at' => now(),
                'operator_confirmed_at'    => $booking->operator_confirmed_at ?? now(),
            ]);
        }
    }

    /** @deprecated Không còn gán tự động — tài xế dò và nhận cuốc. */
    public function autoAssignForBooking(Booking $booking): ?DriverTripRequest
    {
        $this->markBookingAwaitingDriver($booking);

        return null;
    }

    /** Tài xế cập nhật vị trí — chỉ hết hạn yêu cầu cũ, không gán tự động. */
    public function retryWaitingBookings(): int
    {
        $this->expireStale();

        return 0;
    }

    /** Ghép thêm khách vào chuyến tài xế đang phục vụ — schedule đã có driver_id. */
    private function stampAssignedDriverOnBooking(Schedule $schedule, string $contactPhone, int $driverUserId): void
    {
        $booking = Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $contactPhone)
            ->latest('id')
            ->first();

        $booking?->stampAssignedDriver($driverUserId);
    }

    public function claimBooking(Booking $booking, int $driverUserId): void
    {
        $this->expireStale();

        $profile = DriverProfile::query()
            ->operational()
            ->where('user_id', $driverUserId)
            ->with('user')
            ->firstOrFail();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = $booking->schedule;

        if (! $schedule || $schedule->driver_id) {
            throw new InvalidArgumentException('Chuyến đã có tài xế nhận.');
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Đơn không còn hiệu lực.');
        }

        if (($profile->availability_status ?? 'off_duty') !== 'available') {
            throw new InvalidArgumentException('Bật trạng thái Sẵn sàng trước khi nhận cuốc.');
        }

        if (! $profile->hasFreshLocation(DriverProximityService::LOCATION_MAX_AGE_MINUTES)) {
            throw new InvalidArgumentException('Cập nhật vị trí GPS trên bản đồ trước khi nhận cuốc.');
        }

        if (! $this->proximity->withinDiscoveryRadius($profile, $booking)) {
            throw new InvalidArgumentException('Cuốc này ngoài phạm vi của bạn (quá xa điểm đón).');
        }

        $blockReason = $this->wallets->acceptBlockReason($profile);
        if ($blockReason) {
            throw new InvalidArgumentException($blockReason);
        }

        $designated = $schedule->designatedDriverProfile();
        if ($designated && (int) $designated->user_id !== $driverUserId) {
            throw new InvalidArgumentException('Chuyến ghép đã giao cho tài xế khác.');
        }

        if ($this->availability->isDriverBusyForSlot(
            $driverUserId,
            $schedule->route->departure,
            $schedule->route->destination,
            $schedule->departure_time,
        )) {
            throw new InvalidArgumentException('Bạn đã full ghế khung giờ này.');
        }

        if ($this->hasExclusivePendingFromOtherDriver($booking, $driverUserId)) {
            throw new InvalidArgumentException('Cuốc đang chờ tài xế khác xác nhận.');
        }

        DB::transaction(function () use ($booking, $profile): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($booking->schedule_id);

            if ($schedule->driver_id) {
                throw new InvalidArgumentException('Chuyến đã có tài xế nhận.');
            }

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            $tripRequest = DriverTripRequest::query()->create([
                'schedule_id'   => $schedule->id,
                'contact_phone' => $booking->contact_phone,
                'driver_id'     => $profile->user_id,
                'status'        => 'pending',
                'expires_at'    => now()->addMinute(),
            ]);

            $this->accept($tripRequest, (int) $profile->user_id);
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function tripCardsForDriver(int $driverUserId): Collection
    {
        $profile = DriverProfile::query()->where('user_id', $driverUserId)->first();

        $cards = $this->pendingGroupsForDriver($driverUserId)
            ->map(function (array $group) use ($profile): array {
                $payload = $this->serializePendingGroup($group);
                $payload['is_open_trip'] = false;

                if ($profile) {
                    $booking = $this->bookingForRequest($group['primary']);
                    $km = $this->proximity->snapshotPickupDistance($booking, $profile);
                    $payload['distance_label'] = $km !== null
                        ? DriverProximityService::formatDistanceLabel($km)
                        : null;
                }

                return $payload;
            });

        if (! $profile) {
            return $cards;
        }

        $open = $this->discoverOpenTripsForDriver($driverUserId)
            ->map(fn (array $group): array => $this->serializeOpenTripGroup($group));

        return $cards->concat($open)->values();
    }

    /** @return Collection<int, array{primary_booking: Booking, schedule: Schedule, passengers: Collection<int, Booking>, distance_km: ?float}> */
    public function discoverOpenTripsForDriver(int $driverUserId): Collection
    {
        $profile = DriverProfile::query()->where('user_id', $driverUserId)->with('user')->first();
        if (! $profile || ! $this->profileEligibleForMatching($profile)) {
            return collect();
        }

        if (($profile->availability_status ?? 'off_duty') !== 'available') {
            return collect();
        }

        if (! $this->wallets->canDiscoverTrips($profile)) {
            return collect();
        }

        if (! $profile->hasFreshLocation(DriverProximityService::LOCATION_MAX_AGE_MINUTES)) {
            return collect();
        }

        $pendingScheduleIds = DriverTripRequest::query()
            ->where('driver_id', $driverUserId)
            ->where('status', 'pending')
            ->pluck('schedule_id');

        $bookings = Booking::query()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->whereHas('schedule', fn ($q) => $q
                ->whereNull('driver_id')
                ->where('status', 'scheduled')
                ->where('departure_time', '>', now()))
            ->with(['schedule.route', 'schedule.vehicle', 'schedule.bookings'])
            ->orderBy('created_at')
            ->get();

        return $bookings
            ->filter(function (Booking $booking) use ($profile, $driverUserId, $pendingScheduleIds): bool {
                if ($pendingScheduleIds->contains($booking->schedule_id)) {
                    return false;
                }

                if ($this->hasExclusivePendingFromOtherDriver($booking, $driverUserId)) {
                    return false;
                }

                $schedule = $booking->schedule;
                $designated = $schedule->designatedDriverProfile();
                if ($designated && (int) $designated->user_id !== $driverUserId) {
                    return false;
                }

                if (! $this->proximity->withinDiscoveryRadius($profile, $booking)) {
                    return false;
                }

                return ! $this->availability->isDriverBusyForSlot(
                    $driverUserId,
                    $schedule->route->departure,
                    $schedule->route->destination,
                    $schedule->departure_time,
                );
            })
            ->groupBy('schedule_id')
            ->map(function (Collection $scheduleBookings) use ($profile): array {
                /** @var Booking $primaryBooking */
                $primaryBooking = $scheduleBookings->sortByDesc('id')->first();
                $schedule = $primaryBooking->schedule;
                $schedule->loadMissing('bookings', 'route', 'vehicle');
                $ids = $scheduleBookings->pluck('id');

                return [
                    'primary_booking' => $primaryBooking,
                    'schedule'        => $schedule,
                    'passengers'      => $schedule->driverRelevantBookings()
                        ->filter(fn (Booking $b): bool => $ids->contains($b->id))
                        ->values(),
                    'distance_km'     => $this->proximity->snapshotPickupDistance($primaryBooking, $profile),
                ];
            })
            ->sortBy(fn (array $group): float => $group['distance_km'] ?? 9999.0)
            ->values();
    }

    private function hasExclusivePendingFromOtherDriver(Booking $booking, int $driverUserId): bool
    {
        return DriverTripRequest::query()
            ->where('schedule_id', $booking->schedule_id)
            ->where('contact_phone', $booking->contact_phone)
            ->where('status', 'pending')
            ->where('driver_id', '!=', $driverUserId)
            ->exists();
    }

    private function profileEligibleForMatching(DriverProfile $profile): bool
    {
        $profile->loadMissing('user');

        return $profile->status === 'active'
            && $profile->user
            && $profile->user->status === 'active'
            && $profile->isApproved()
            && ! $profile->isMissedTripLocked();
    }

    /** @param array{primary_booking: Booking, schedule: Schedule, passengers: Collection<int, Booking>, distance_km: ?float} $group */
    private function serializeOpenTripGroup(array $group): array
    {
        /** @var Booking $booking */
        $booking = $group['primary_booking'];
        /** @var Schedule $schedule */
        $schedule = $group['schedule'];
        /** @var Collection<int, Booking> $passengers */
        $passengers = $group['passengers'];
        $distanceKm = $group['distance_km'];

        return [
            'id'               => 'open-' . $booking->id,
            'is_open_trip'     => true,
            'accept_url'       => route('driver.bookings.claim', $booking),
            'claim_url'        => route('driver.bookings.claim', $booking),
            'reject_url'       => null,
            'route'            => $schedule->route->departure . ' → ' . $schedule->route->destination,
            'departure_time'   => $schedule->departure_time->format('H:i, d/m/Y'),
            'expires_at'       => null,
            'expires_in_label' => null,
            'trip_code'        => $schedule->shortTripCode(),
            'meta_label'       => $schedule->tripMetaLabel(),
            'passenger_count'  => $passengers->count(),
            'trip_total'       => number_format((float) $passengers->sum(fn (Booking $b) => (float) $b->total_price), 0, ',', '.'),
            'distance_label' => $distanceKm !== null
                ? DriverProximityService::formatDistanceLabel($distanceKm)
                : null,
            'passengers'       => $passengers->map(fn (Booking $b): array => $this->serializePassengerForRequest($b))->values()->all(),
        ];
    }

    /** Gán thẳng tài xế vào chuyến khi khách đặt xe (gần điểm đón nhất). */
    public function directAssignForBooking(Booking $booking): bool
    {
        $this->expireStale();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = Schedule::query()->find($booking->schedule_id);

        if (! $schedule
            || $schedule->driver_id
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if ($this->proximity->pickupCoordinates($booking) === null) {
            return false;
        }

        $tried = collect();

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $driver = $this->proximity->pickBest($schedule, $booking, $tried, true);

            if (! $driver?->user_id) {
                return false;
            }

            if ($this->wallets->acceptBlockReason($driver)) {
                $tried->push((int) $driver->user_id);

                continue;
            }

            try {
                $this->assignDriverToSchedule($schedule, $driver);

                return true;
            } catch (InvalidArgumentException) {
                $tried->push((int) $driver->user_id);
            }
        }

        return false;
    }

    public function escalateDriverSearchTimeouts(): void
    {
        $cutoff = now()->subMinutes(self::OPERATOR_ESCALATION_MINUTES);

        Booking::query()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->whereNull('needs_operator_help_at')
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($q) use ($cutoff): void {
                    $q->whereNotNull('driver_search_started_at')
                        ->where('driver_search_started_at', '<=', $cutoff);
                })->orWhere(function ($q) use ($cutoff): void {
                    $q->whereNull('driver_search_started_at')
                        ->where('created_at', '<=', $cutoff);
                });
            })
            ->whereHas('schedule', fn ($q) => $q->whereNull('driver_id'))
            ->each(function (Booking $booking): void {
                $this->markNeedsOperatorHelp($booking->fresh(), self::HELP_SEARCH_TIMEOUT);
            });
    }

    public function markNeedsOperatorHelp(Booking $booking, string $reason): void
    {
        if ($booking->needs_operator_help_at
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'completed'
            || $booking->hasDriverAccepted()) {
            return;
        }

        $payload = [
            'needs_operator_help_at' => now(),
            'operator_help_reason'   => $reason,
        ];

        if (Booking::supportsOperatorDismiss()) {
            $payload['operator_dismissed_at'] = null;
        }

        $booking->update($payload);
    }

    public function clearOperatorHelp(Booking $booking): void
    {
        if (! $booking->needs_operator_help_at && ! $booking->operator_help_reason) {
            return;
        }

        $booking->update([
            'needs_operator_help_at' => null,
            'operator_help_reason'   => null,
        ]);
    }

    public function accept(DriverTripRequest $request, int $driverUserId): void
    {
        $this->expireStale();

        if ($request->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Không có quyền xử lý yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $driver = DriverProfile::query()->where('user_id', $request->driver_id)->firstOrFail();
        $blockReason = $this->wallets->acceptBlockReason($driver);
        if ($blockReason) {
            throw new InvalidArgumentException($blockReason);
        }

        DB::transaction(function () use ($request): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($request->schedule_id);

            if ($schedule->driver_id && $schedule->driver_id !== $request->driver_id) {
                throw new InvalidArgumentException('Chuyến đã được tài xế khác nhận.');
            }

            $driver = DriverProfile::query()->where('user_id', $request->driver_id)->firstOrFail();
            $driverName = $driver->user->name;

            $schedule->update([
                'driver_id'   => $request->driver_id,
                'driver_name' => $driverName,
            ]);

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('driver_id', $request->driver_id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'accepted',
                    'responded_at' => now(),
                ]);

            $this->anchorAllBookingsForAcceptedSchedule($schedule->fresh());
        });
    }

    private function assignDriverToSchedule(Schedule $schedule, DriverProfile $driver): void
    {
        DB::transaction(function () use ($schedule, $driver): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ($schedule->driver_id && (int) $schedule->driver_id !== (int) $driver->user_id) {
                throw new InvalidArgumentException('Chuyến đã được tài xế khác nhận.');
            }

            if ($schedule->driver_id) {
                return;
            }

            $driver->loadMissing('user');
            $schedule->update([
                'driver_id'   => $driver->user_id,
                'driver_name' => $driver->user->name,
            ]);

            $this->anchorAllBookingsForAcceptedSchedule($schedule->fresh());
        });
    }

    /** @return Collection<int, array{primary: DriverTripRequest, schedule: Schedule, passengers: Collection<int, Booking>}> */
    public function pendingGroupsForDriver(int $driverUserId): Collection
    {
        return DriverTripRequest::query()
            ->where('driver_id', $driverUserId)
            ->where('status', 'pending')
            ->with(['schedule.route', 'schedule.vehicle', 'schedule.bookings'])
            ->latest()
            ->get()
            ->groupBy('schedule_id')
            ->map(function (Collection $requests): array {
                $primary = $requests->sortByDesc('created_at')->first();
                $schedule = $primary->schedule;
                $schedule->loadMissing('bookings', 'route', 'vehicle');

                return [
                    'primary'    => $primary,
                    'schedule'   => $schedule,
                    'passengers' => $this->passengersForPendingGroup($schedule, $requests),
                ];
            })
            ->values();
    }

    /** @return array<string, mixed> */
    public function serializePendingGroup(array $group): array
    {
        /** @var DriverTripRequest $primary */
        $primary = $group['primary'];
        /** @var Schedule $schedule */
        $schedule = $group['schedule'];
        /** @var Collection<int, Booking> $passengers */
        $passengers = $group['passengers'];

        return [
            'id'               => $primary->id,
            'is_open_trip'     => false,
            'accept_url'       => route('driver.tripRequests.accept', $primary),
            'reject_url'       => route('driver.tripRequests.reject', $primary),
            'route'            => $schedule->route->departure . ' → ' . $schedule->route->destination,
            'departure_time'   => $schedule->departure_time->format('H:i, d/m/Y'),
            'expires_at'       => $primary->expires_at?->toIso8601String(),
            'expires_in_label' => $primary->acceptTimeRemainingLabel(),
            'trip_code'        => $schedule->shortTripCode(),
            'meta_label'       => $schedule->tripMetaLabel(),
            'passenger_count'  => $passengers->count(),
            'trip_total'       => number_format((float) $passengers->sum(fn (Booking $b) => (float) $b->total_price), 0, ',', '.'),
            'passengers'       => $passengers->map(fn (Booking $b): array => $this->serializePassengerForRequest($b))->values()->all(),
        ];
    }

    /** @return Collection<int, Booking> */
    private function passengersForPendingGroup(Schedule $schedule, Collection $requests): Collection
    {
        return $schedule->driverRelevantBookings()->filter(function (Booking $booking) use ($requests): bool {
            foreach ($requests as $request) {
                if ($booking->matchesContactPhone((string) $request->contact_phone)) {
                    return true;
                }
            }

            return $booking->operator_confirmed_at !== null && ! $booking->hasDriverAccepted();
        })->values();
    }

    /** @return array<string, mixed> */
    private function serializePassengerForRequest(Booking $booking): array
    {
        return [
            'passenger_name'   => $booking->passenger_name,
            'passenger_gender' => $booking->passengerGenderLabel(),
            'passenger_age'    => $booking->passenger_age,
            'passenger_profile'=> $booking->passengerProfileDetail(),
            'booking_mode'     => $booking->bookingModeLabel(),
            'booking_mode_key' => $booking->booking_mode ?? 'shared',
            'pickup_time'      => $booking->pickupTimeLabel(),
            'pickup'           => $booking->driverPickupDetailLabel(),
            'dropoff'          => $booking->driverDropoffDetailLabel(),
            'notes'            => $booking->notes,
            'seats_label'      => ($booking->booking_mode ?? 'shared') === 'shared' && $booking->seatCount() > 0
                ? $booking->seatCountLabel()
                : null,
            'trip_total'       => number_format((float) $booking->total_price, 0, ',', '.'),
        ];
    }

    private function anchorAllBookingsForAcceptedSchedule(Schedule $schedule): void
    {
        $workflow = app(BookingWorkflowService::class);
        $driverUserId = (int) $schedule->driver_id;
        $driverProfile = DriverProfile::query()->where('user_id', $driverUserId)->first();

        foreach ($schedule->driverRelevantBookings() as $booking) {
            $distance = $driverProfile
                ? $this->proximity->snapshotPickupDistance($booking, $driverProfile)
                : null;

            $booking->stampAssignedDriver($driverUserId);

            if ($distance !== null) {
                $booking->update(['driver_pickup_distance_km' => $distance]);
            }

            $this->clearOperatorHelp($booking);
            $this->anchorBookingForDriverAccept($schedule, $booking, $workflow);
        }
    }

    private function anchorBookingForDriverAccept(
        Schedule $schedule,
        Booking $booking,
        BookingWorkflowService $workflow,
    ): void {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            if (! $booking->expired_at) {
                return;
            }

            $booking->update([
                'booking_status'  => 'pending',
                'trip_status'     => 'pending',
                'expired_at'      => null,
                'cancelled_at'    => null,
                'hold_expires_at' => null,
            ]);

            foreach ((array) $booking->seat_numbers as $seat) {
                SeatReservation::query()->updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'seat_number' => (string) $seat,
                        'booking_id'  => $booking->id,
                    ],
                    [
                        'status'            => 'held',
                        'expires_at'        => null,
                        'reservation_token' => (string) Str::uuid(),
                    ],
                );
            }

            $workflow->syncScheduleAvailability($schedule);
            $workflow->confirmForDriverAccept($booking->fresh());

            return;
        }

        $booking->update(['hold_expires_at' => null]);
        $booking->seatReservations()
            ->whereIn('status', ['held', 'booked'])
            ->update(['expires_at' => null]);

        $workflow->confirmForDriverAccept($booking->fresh());
    }

    public function reassignScheduleDriver(Schedule $schedule, string $newDriverCode, int $operatorUserId): void
    {
        $this->expireStale();

        $schedule->loadMissing(['route', 'vehicle', 'bookings']);

        if ((int) $schedule->vehicle->operator_id !== $operatorUserId) {
            throw new InvalidArgumentException('Không có quyền phân công chuyến này.');
        }

        if ($schedule->departure_time <= now()) {
            throw new InvalidArgumentException('Chuyến đã khởi hành, không thể đổi tài xế.');
        }

        $profile = DriverProfile::query()
            ->operational()
            ->where('driver_code', strtoupper(trim($newDriverCode)))
            ->with('user')
            ->first();

        if (! $profile) {
            throw new InvalidArgumentException('Không tìm thấy tài xế với mã này.');
        }

        if ($schedule->driver_id && (int) $schedule->driver_id === (int) $profile->user_id) {
            throw new InvalidArgumentException('Chuyến đang được tài xế này phục vụ.');
        }

        if ($this->availability->isDriverBusyForSlot(
            (int) $profile->user_id,
            $schedule->route->departure,
            $schedule->route->destination,
            $schedule->departure_time,
        )) {
            throw new InvalidArgumentException('Tài xế mới đã bận khung giờ này. Vui lòng chọn tài xế khác.');
        }

        $bookingsToReassign = $schedule->driverRelevantBookings()
            ->filter(fn (Booking $booking): bool => ! in_array($booking->trip_status, ['completed'], true));

        if ($bookingsToReassign->isEmpty()) {
            throw new InvalidArgumentException('Chuyến không còn vé cần phân công.');
        }

        DB::transaction(function () use ($schedule, $bookingsToReassign): void {
            $locked = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            DriverTripRequest::query()
                ->where('schedule_id', $locked->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            $locked->update([
                'driver_id'   => null,
                'driver_name' => null,
            ]);

            foreach ($bookingsToReassign as $booking) {
                $booking->update([
                    'trip_status' => 'pending',
                ]);
            }
        });

        foreach ($bookingsToReassign as $booking) {
            $this->requestDriver(
                $schedule->fresh(['route']),
                $newDriverCode,
                (string) $booking->contact_phone,
            );
        }
    }

    public function reject(DriverTripRequest $request, int $driverUserId): void
    {
        if ($request->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Không có quyền xử lý yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $siblings = DriverTripRequest::query()
            ->where('schedule_id', $request->schedule_id)
            ->where('driver_id', $driverUserId)
            ->where('status', 'pending')
            ->get();

        foreach ($siblings as $sibling) {
            $sibling->update([
                'status'       => 'rejected',
                'responded_at' => now(),
            ]);

            $this->tryReassignAfterDecline($sibling);
        }

        $request->loadMissing('schedule.route');
        if ($request->schedule) {
            $profile = DriverProfile::query()->with('user')->where('user_id', $driverUserId)->first();
            $this->tripLedger->recordForSchedule($request->schedule, TripLedger::OUTCOME_CANCELLED_DRIVER, [
                'actor_label' => $profile?->user?->name ?? 'Tài xế',
                'actor_code'  => $profile?->driver_code,
            ]);
        }
    }

    private function tryReassignAfterDecline(DriverTripRequest $request): void
    {
        $request->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = $request->schedule;

        if (! $schedule || $schedule->driver_id || $schedule->departure_time <= now()) {
            return;
        }

        // Cuốc mở lại cho tài xế khác dò — không gán tự động.
    }

    private function bookingForRequest(DriverTripRequest $request): Booking
    {
        $booking = $request->relatedBooking();
        if ($booking) {
            return $booking;
        }

        return Booking::query()
            ->where('schedule_id', $request->schedule_id)
            ->where('contact_phone', $request->contact_phone)
            ->latest()
            ->firstOrFail();
    }

    /** @return Collection<int, int> */
    private function triedDriverIds(Schedule $schedule, string $contactPhone): Collection
    {
        return DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $contactPhone)
            ->whereIn('status', ['expired', 'rejected', 'cancelled'])
            ->pluck('driver_id')
            ->map(fn ($id): int => (int) $id);
    }

    public function cancelByContactPhone(DriverTripRequest $request, string $contactPhone): void
    {
        $stored = preg_replace('/\D+/', '', (string) $request->contact_phone);
        $given = preg_replace('/\D+/', '', $contactPhone);
        if ($stored === '' || $stored !== $given) {
            throw new InvalidArgumentException('Không có quyền hủy yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $request->update([
            'status'       => 'cancelled',
            'responded_at' => now(),
        ]);

        $this->tryReassignAfterDecline($request->fresh());
    }

    /** @return array<string, mixed> */
    public function serializeDriver(DriverProfile $profile): array
    {
        $meta = $this->availabilityMeta($profile->availability_status ?? 'off_duty');

        return [
            'code'                => $profile->driver_code,
            'name'                => $profile->user->name,
            'license_class'       => $profile->license_class,
            'experience_years'    => $profile->experience_years,
            'operator'            => $profile->operator?->name,
            'availability'        => $profile->availability_status,
            'availability_label'  => $meta['label'],
            'availability_color'  => $meta['color'],
            'availability_icon'   => $meta['icon'],
            'suggested'           => $meta['suggested'],
            'vehicle_type'        => $profile->vehicle_type,
            'vehicle_plate'       => $profile->vehicle_license_plate,
            'vehicle_seats'       => (int) ($profile->vehicle_seats ?? 0),
            'vehicle_label'       => self::vehicleLabel($profile),
        ];
    }

    public static function vehicleLabel(DriverProfile $profile): string
    {
        return VehicleDisplay::compactLabel(
            $profile->vehicle_type ? (string) $profile->vehicle_type : null,
            $profile->vehicle_license_plate,
            $profile->vehicle_seats ? (int) $profile->vehicle_seats : null,
        );
    }

    /** @return array<string, mixed>|null */
    public function serializeRequest(?DriverTripRequest $request): ?array
    {
        if (! $request) {
            return null;
        }

        return [
            'id'           => $request->id,
            'schedule_id'  => $request->schedule_id,
            'status'       => $request->status,
            'status_label' => $request->statusLabel(),
            'driver_name'  => $request->driver?->name,
            'driver_code'  => $request->driverProfile?->driver_code,
            'expires_at'   => $request->expires_at?->toIso8601String(),
            'is_pending'   => $request->isPending(),
        ];
    }
}
