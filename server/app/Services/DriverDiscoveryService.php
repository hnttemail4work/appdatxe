<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Phần "khám phá cuốc" cho tài xế — tách ra từ DriverTripRequestService (God Service):
 * chỉ đọc dữ liệu để hiển thị lên dashboard tài xế (chuyến mở / đang chờ nhận),
 * không ghi/thay đổi trạng thái gán tài xế. DriverTripRequestService vẫn giữ các
 * method cũ (delegate sang đây) để không phải sửa lại các nơi đang gọi.
 */
class DriverDiscoveryService
{
    public function __construct(
        private readonly DriverAvailabilityService $availability,
        private readonly DriverWalletService $wallets,
        private readonly DriverProximityService $proximity,
    ) {
    }

    /** @return Collection<int, array<string, mixed>> */
    public function tripCardsForDriver(int $driverUserId): Collection
    {
        return collect();
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

                return ! $this->availability->hasTripTimeConflict(
                    $driverUserId,
                    $schedule,
                    $booking,
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
            'trip_total'       => Money::format((float) $passengers->sum(fn (Booking $b) => (float) $b->total_price)),
            'distance_label' => $distanceKm !== null
                ? DriverProximityService::formatDistanceLabel($distanceKm)
                : null,
            'passengers'       => $passengers->map(fn (Booking $b): array => $this->serializePassengerForRequest($b))->values()->all(),
        ];
    }

    /** @return Collection<int, array{primary: DriverTripRequest, schedule: Schedule, passengers: Collection<int, Booking>}> */
    public function pendingGroupsForDriver(int $driverUserId): Collection
    {
        return DriverTripRequest::query()
            ->where('driver_id', $driverUserId)
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
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
            ->filter(fn (array $group): bool => $group['passengers']->isNotEmpty())
            ->values();
    }

    /** Cuốc chờ nhận còn hiển thị trên dashboard — loại cuốc tài xế đã ẩn / bỏ lỡ. */
    public function visiblePendingGroupsForDriver(int $driverUserId): Collection
    {
        $profile = DriverProfile::query()->where('user_id', $driverUserId)->first();
        if (! $profile || ! $this->availability->catalogBookingButtonState($profile)['is_online']) {
            return collect();
        }

        if ($this->wallets->acceptBlockReason($profile)) {
            return collect();
        }

        $hide = app(DriverCuocOfferHideService::class);

        return $this->pendingGroupsForDriver($driverUserId)
            ->filter(function (array $group) use ($hide, $driverUserId): bool {
                /** @var DriverTripRequest $primary */
                $primary = $group['primary'];

                return ! $hide->isHidden(
                    $driverUserId,
                    $group['schedule'],
                    (string) $primary->contact_phone,
                );
            })
            ->values();
    }

    public function tripActionCountForDriver(int $driverUserId): int
    {
        $scheduleCount = Schedule::query()
            ->with([
                'route',
                'vehicle',
                'tripSettlement',
                'bookings' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->forDriverActiveTrips($driverUserId)
            ->get()
            ->filter(fn (Schedule $schedule): bool => $schedule->driverRelevantBookings()->isNotEmpty()
                && $schedule->driverWorkflowPhase() !== 'settled'
                && $schedule->isVisibleOnDriverDashboard())
            ->filter(fn (Schedule $schedule): bool => in_array($schedule->driverWorkflowPhase(), ['upcoming', 'active'], true))
            ->count();

        return $scheduleCount
            + $this->visiblePendingGroupsForDriver($driverUserId)->count();
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
            'expires_in_label' => null,
            'trip_code'        => $schedule->shortTripCode(),
            'meta_label'       => $schedule->tripMetaLabel(),
            'passenger_count'  => $passengers->count(),
            'trip_total'       => Money::format((float) $passengers->sum(fn (Booking $b) => (float) $b->total_price)),
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
            'pickup_time'      => $booking->pickupTimeLabel(),
            'pickup_date'      => $booking->driverPickupDateLabel(),
            'pickup_schedule'  => $booking->driverPickupScheduleLabel(),
            'pickup'           => $booking->driverPickupDetailLabel(),
            'dropoff'          => $booking->driverDropoffDetailLabel(),
            'notes'            => $booking->notes,
            'trip_total'       => Money::format((float) $booking->total_price),
            'price_subtotal'   => (int) ($booking->price_subtotal ?? 0),
            'referral_discount_amount' => (int) ($booking->referral_discount_amount ?? 0),
            'surcharge_holiday'=> (int) ($booking->surcharge_holiday ?? 0),
            'surcharge_peak'   => (int) ($booking->surcharge_peak ?? 0),
            'surcharge_rain'   => (int) ($booking->surcharge_rain ?? 0),
            'toll_amount'      => (int) ($booking->toll_amount ?? 0),
            'price_breakdown'  => is_array($booking->price_breakdown) ? $booking->price_breakdown : null,
        ];
    }
}
