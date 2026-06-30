<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Support\ProvinceCenters;
use Illuminate\Support\Collection;

/**
 * Chọn tài xế gần điểm đón nhất (so sánh tọa độ GPS).
 *
 * Lọc: operational, Sẵn sàng, ví đủ, không bận; có tọa độ mới (auto-gán).
 * Ưu tiên: khoảng cách km → tài xế mới/chưa đạt 100k → ít dislike → nhiều like.
 */
class DriverProximityService
{
    public const LOCATION_MAX_AGE_MINUTES = 15;

    /** Tài xế xa hơn ngưỡng này (km) không được auto-gán. */
    public const MAX_ASSIGN_RADIUS_KM = 50.0;

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
        bool $requireCoordinates = false,
    ): ?DriverProfile {
        $exclude = $excludeDriverUserIds ?? collect();
        $schedule->loadMissing(['route', 'vehicle']);
        $pickup = $this->pickupCoordinates($booking);

        if ($requireCoordinates && $pickup === null) {
            return null;
        }

        $candidates = DriverProfile::query()
            ->operational()
            ->with(['user', 'operator'])
            ->where('availability_status', 'available')
            ->get()
            ->filter(function (DriverProfile $profile) use ($schedule, $exclude, $requireCoordinates, $pickup): bool {
                if ($exclude->contains((int) $profile->user_id)) {
                    return false;
                }

                if (! $this->wallets->canAcceptTrips($profile)) {
                    return false;
                }

                if ($this->availability->isDriverBusyForSlot(
                    (int) $profile->user_id,
                    $schedule->route->departure,
                    $schedule->route->destination,
                    $schedule->departure_time,
                )) {
                    return false;
                }

                if ($requireCoordinates) {
                    if (! $profile->hasFreshLocation(self::LOCATION_MAX_AGE_MINUTES)) {
                        return false;
                    }

                    if ($pickup === null) {
                        return false;
                    }

                    return $this->distanceKm($profile, $pickup) <= self::MAX_ASSIGN_RADIUS_KM;
                }

                return true;
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
        if ($booking->pickup_lat === null || $booking->pickup_lng === null) {
            return null;
        }

        return ['lat' => (float) $booking->pickup_lat, 'lng' => (float) $booking->pickup_lng];
    }

    /** @param array{lat: float, lng: float} $pickup */
    public function distanceKm(DriverProfile $profile, array $pickup): float
    {
        return ProvinceCenters::distanceKm(
            (float) $profile->last_lat,
            (float) $profile->last_lng,
            $pickup['lat'],
            $pickup['lng'],
        );
    }

    /** @param array{lat: float, lng: float}|null $pickup */
    private function sortKey(DriverProfile $profile, ?array $pickup): array
    {
        $distance = $pickup && $profile->hasFreshLocation(self::LOCATION_MAX_AGE_MINUTES)
            ? $this->distanceKm($profile, $pickup)
            : 9999.0;
        $preThresholdRank = $this->wallets->isPreRevenueThreshold($profile) ? 0 : 1;

        return [
            $distance,
            $preThresholdRank,
            (int) $profile->preference_dislikes,
            -(int) $profile->preference_likes,
        ];
    }
}
