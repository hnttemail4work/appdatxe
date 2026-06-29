<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Support\ProvinceCenters;
use Illuminate\Support\Collection;

class DriverProximityService
{
    public const LOCATION_MAX_AGE_MINUTES = 15;

    public function __construct(
        private readonly DriverAvailabilityService $availability,
        private readonly DriverWalletService $wallets,
    ) {
    }

    /** @param Collection<int, int>|null $excludeDriverUserIds */
    public function pickBest(
        Schedule $schedule,
        Booking $booking,
        ?Collection $excludeDriverUserIds = null,
    ): ?DriverProfile {
        $exclude = $excludeDriverUserIds ?? collect();
        $schedule->loadMissing(['route', 'vehicle']);
        $pickup = $this->pickupCoordinates($booking);

        $candidates = DriverProfile::query()
            ->operational()
            ->with(['user', 'operator'])
            ->where('availability_status', 'available')
            ->when($schedule->vehicle?->operator_id, fn ($q, $opId) => $q->where('operator_id', $opId))
            ->get()
            ->filter(function (DriverProfile $profile) use ($schedule, $exclude): bool {
                if ($exclude->contains((int) $profile->user_id)) {
                    return false;
                }

                if (! $this->wallets->canAcceptTrips($profile)) {
                    return false;
                }

                return ! $this->availability->isDriverBusyForSlot(
                    (int) $profile->user_id,
                    $schedule->route->departure,
                    $schedule->route->destination,
                    $schedule->departure_time,
                );
            });

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn (DriverProfile $p): array => $this->sortKey($p, $pickup))
            ->first();
    }

    /** @return array{lat: float, lng: float}|null */
    public function pickupCoordinates(Booking $booking): ?array
    {
        if ($booking->pickup_lat !== null && $booking->pickup_lng !== null) {
            return ['lat' => (float) $booking->pickup_lat, 'lng' => (float) $booking->pickup_lng];
        }

        return ProvinceCenters::forProvince($booking->pickup_address);
    }

    /** @param array{lat: float, lng: float}|null $pickup */
    private function sortKey(DriverProfile $profile, ?array $pickup): array
    {
        $distance = $this->driverDistanceKm($profile, $pickup);

        return [
            (int) $profile->preference_dislikes,
            $distance,
            -(int) $profile->preference_likes,
            -(int) $profile->experience_years,
        ];
    }

    /** @param array{lat: float, lng: float}|null $pickup */
    private function driverDistanceKm(DriverProfile $profile, ?array $pickup): float
    {
        if (! $pickup || ! $profile->hasFreshLocation(self::LOCATION_MAX_AGE_MINUTES)) {
            return 9999.0;
        }

        return ProvinceCenters::distanceKm(
            (float) $profile->last_lat,
            (float) $profile->last_lng,
            $pickup['lat'],
            $pickup['lng'],
        );
    }
}
