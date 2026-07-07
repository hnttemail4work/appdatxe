<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Schedule;

/** Liên kết mở Google Maps chỉ đường cho tài xế. */
final class MapNavigation
{
    public static function directionsUrl(?float $lat, ?float $lng, ?string $address = null): ?string
    {
        if ($lat !== null && $lng !== null) {
            return 'https://www.google.com/maps/dir/?' . http_build_query([
                'api'         => '1',
                'destination' => sprintf('%F,%F', $lat, $lng),
                'travelmode'  => 'driving',
            ]);
        }

        $address = trim((string) $address);
        if ($address === '') {
            return null;
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query([
            'api'         => '1',
            'destination' => $address,
            'travelmode'  => 'driving',
        ]);
    }

    /** @return array{label: string, url: string}|null */
    public static function driverTargetForSchedule(Schedule $schedule, Booking $booking): ?array
    {
        $stage = $schedule->resolvedDriverStage();
        $toDropoff = in_array($stage, [
            Schedule::DRIVER_STAGE_PICKED_UP,
            Schedule::DRIVER_STAGE_RUNNING,
        ], true);

        if ($toDropoff) {
            $url = self::directionsUrl(
                $booking->dropoff_lat !== null ? (float) $booking->dropoff_lat : null,
                $booking->dropoff_lng !== null ? (float) $booking->dropoff_lng : null,
                $booking->dropoffLabel() !== '—' ? $booking->dropoffLabel() : $booking->driverDropoffDetailLabel(),
            );

            return $url ? ['label' => 'Chỉ đường điểm trả', 'url' => $url] : null;
        }

        $url = self::directionsUrl(
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            $booking->pickupLabel() !== '—' ? $booking->pickupLabel() : $booking->driverPickupDetailLabel(),
        );

        return $url ? ['label' => 'Chỉ đường điểm đón', 'url' => $url] : null;
    }

    /** @return array{label: string, url: string}|null */
    public static function driverPickupTarget(Booking $booking): ?array
    {
        $url = self::directionsUrl(
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            $booking->pickupLabel() !== '—' ? $booking->pickupLabel() : $booking->driverPickupDetailLabel(),
        );

        return $url ? ['label' => 'Chỉ đường điểm đón', 'url' => $url] : null;
    }
}
