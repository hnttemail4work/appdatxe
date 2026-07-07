<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Support\ProvinceCenters;
use App\Support\ProvinceResolver;
use Illuminate\Support\Collection;

/**
 * Chọn tài xế gần điểm đón nhất (so sánh tọa độ GPS).
 *
 * Lọc: operational, Sẵn sàng, ví đủ, không bận; có tọa độ mới (auto-gán).
 * Khác tỉnh điểm đón: tối đa 20 km; cùng tỉnh: tối đa 50 km.
 * Ưu tiên: ít chuyến đang chạy → khoảng cách km → tài xế mới → ít dislike → nhiều like.
 */
class DriverProximityService
{
    public const LOCATION_MAX_AGE_MINUTES = 15;

    /** Khác tỉnh điểm đón — không gán nếu xa hơn ngưỡng này. */
    public const MAX_CROSS_PROVINCE_KM = 20.0;

    /** Cùng tỉnh điểm đón — ngưỡng tối đa. */
    public const MAX_SAME_PROVINCE_KM = 50.0;

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
            ->where('availability_status', '!=', 'off_duty')
            ->get()
            ->filter(function (DriverProfile $profile) use ($schedule, $exclude, $requireCoordinates, $pickup, $booking): bool {
                if ($exclude->contains((int) $profile->user_id)) {
                    return false;
                }

                if (! $profile->isApproved()) {
                    return false;
                }

                if (! $this->availability->catalogBookingButtonState($profile)['is_online']) {
                    return false;
                }

                if (! $this->wallets->canAcceptTrips($profile)) {
                    return false;
                }

                if ($this->availability->hasTripTimeConflict(
                    (int) $profile->user_id,
                    $schedule,
                    $booking,
                )) {
                    return false;
                }

                if ($requireCoordinates) {
                    if ($pickup === null || ! $this->availability->hasAssignableLocation($profile, self::LOCATION_MAX_AGE_MINUTES)) {
                        return false;
                    }

                    return $this->withinAssignRadius($profile, $booking, $pickup);
                }

                return true;
            });

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn (DriverProfile $p): array => $this->sortKey($p, $pickup, $schedule, $booking))
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

    /** @param array{lat: float, lng: float} $pickup */
    public function withinAssignRadius(DriverProfile $profile, Booking $booking, array $pickup): bool
    {
        if (! $this->availability->hasAssignableLocation($profile, self::LOCATION_MAX_AGE_MINUTES)) {
            return false;
        }

        $distance = $this->distanceKm($profile, $pickup);
        $pickupProvince = ProvinceResolver::fromBooking($booking);
        $driverProvince = ProvinceResolver::forDriver(
            $profile->last_lat !== null ? (float) $profile->last_lat : null,
            $profile->last_lng !== null ? (float) $profile->last_lng : null,
            $profile->last_province,
        );

        if ($pickupProvince && $driverProvince && $pickupProvince !== $driverProvince) {
            return $distance <= self::MAX_CROSS_PROVINCE_KM;
        }

        return $distance <= self::MAX_SAME_PROVINCE_KM;
    }

    public static function formatDistanceLabel(float $km): string
    {
        if ($km < 1) {
            return '< 1 km';
        }

        if ($km >= 10) {
            return number_format($km, 0, ',', '.') . ' km';
        }

        return number_format($km, 1, ',', '.') . ' km';
    }

    public function snapshotPickupDistance(Booking $booking, DriverProfile $profile): ?float
    {
        $pickup = $this->pickupCoordinates($booking);
        if ($pickup === null || $profile->last_lat === null || $profile->last_lng === null) {
            return null;
        }

        return round($this->distanceKm($profile, $pickup), 1);
    }

    /**
     * Cập nhật khoảng cách đến điểm đón cho các chuyến tài xế đang đi đón.
     *
     * @return list<array{trip_code: string, distance_km: float, distance_label: string}>
     */
    public function refreshAssignedPickupDistances(DriverProfile $profile): array
    {
        $profile->refresh();
        $results = [];

        foreach ($this->availability->activeSchedulesForDriver((int) $profile->user_id) as $schedule) {
            if (! in_array($schedule->resolvedDriverStage(), [
                Schedule::DRIVER_STAGE_ASSIGNED,
                Schedule::DRIVER_STAGE_AT_PICKUP,
            ], true)) {
                continue;
            }

            foreach ($schedule->driverRelevantBookings() as $booking) {
                $distance = $this->snapshotPickupDistance($booking, $profile);
                if ($distance === null) {
                    continue;
                }

                $booking->update(['driver_pickup_distance_km' => $distance]);
                $results[] = [
                    'trip_code'      => $schedule->shortTripCode() ?? '—',
                    'distance_km'    => $distance,
                    'distance_label' => self::formatDistanceLabel($distance),
                ];
            }
        }

        return $results;
    }

    /** @return array{distance_km: ?float, distance_label: ?string, auto_assign_eligible: bool, hint: ?string} */
    public function assignDiagnostics(DriverProfile $profile, Booking $booking, Schedule $schedule): array
    {
        $pickup = $this->pickupCoordinates($booking);
        $distance = ($pickup !== null && $profile->last_lat !== null && $profile->last_lng !== null)
            ? round($this->distanceKm($profile, $pickup), 1)
            : null;

        $hints = [];
        $eligible = true;
        $catalogState = $this->availability->catalogBookingButtonState($profile);

        if ($pickup === null) {
            $hints[] = 'Đơn thiếu tọa độ đón';
            $eligible = false;
        }

        if (! $catalogState['is_online']) {
            $hints[] = 'Chưa bật Sẵn sàng';
            $eligible = false;
        }

        if (! $this->wallets->canAcceptTrips($profile)) {
            $hints[] = $this->wallets->acceptBlockReason($profile) ?: 'Ví/chứng thực chưa đủ';
            $eligible = false;
        }

        if ($this->availability->hasTripTimeConflict(
            (int) $profile->user_id,
            $schedule,
            $booking,
        )) {
            $hints[] = 'Trùng giờ với chuyến đang chạy';
            $eligible = false;
        }

        if ($pickup !== null) {
            if (! $catalogState['has_location']) {
                $hints[] = 'Chưa chia sẻ vị trí';
                $eligible = false;
            } elseif ($distance !== null && ! $this->withinAssignRadius($profile, $booking, $pickup)) {
                $hints[] = 'Xa điểm đón (>' . (int) self::MAX_SAME_PROVINCE_KM . ' km)';
                $eligible = false;
            }
        }

        return [
            'distance_km'          => $distance,
            'distance_label'       => $distance !== null ? self::formatDistanceLabel($distance) : null,
            'auto_assign_eligible' => $eligible,
            'hint'                 => $hints !== [] ? implode(' · ', $hints) : null,
        ];
    }

    /** Tài xế dò cuốc: chỉ theo khoảng cách GPS, không phân biệt tỉnh (tránh lệch catalog). */
    public function withinDiscoveryRadius(DriverProfile $profile, Booking $booking, float $maxKm = self::MAX_SAME_PROVINCE_KM): bool
    {
        $pickup = $this->pickupCoordinates($booking);
        if ($pickup === null || ! $profile->hasFreshLocation(self::LOCATION_MAX_AGE_MINUTES)) {
            return false;
        }

        return $this->distanceKm($profile, $pickup) <= $maxKm;
    }

    /** @param array{lat: float, lng: float}|null $pickup */
    private function sortKey(DriverProfile $profile, ?array $pickup, Schedule $schedule, Booking $booking): array
    {
        $distance = $pickup && $profile->hasFreshLocation(self::LOCATION_MAX_AGE_MINUTES)
            ? $this->distanceKm($profile, $pickup)
            : 9999.0;
        $preThresholdRank = $this->wallets->isPreRevenueThreshold($profile) ? 0 : 1;
        $activeTrips = $this->availability->activeTripCount((int) $profile->user_id);

        return [
            $activeTrips,
            $distance,
            $preThresholdRank,
            (int) $profile->preference_dislikes,
            -(int) $profile->preference_likes,
        ];
    }
}
