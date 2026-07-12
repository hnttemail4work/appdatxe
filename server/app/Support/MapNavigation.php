<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Schedule;

/** Liên kết mở bản đồ chỉ đường trên điện thoại (geo URI). */
final class MapNavigation
{
    public static function directionsUrl(?float $lat, ?float $lng, ?string $address = null): ?string
    {
        if ($lat !== null && $lng !== null) {
            $label = trim((string) $address);

            return self::geoUrl($lat, $lng, $label !== '' && $label !== '—' ? $label : null);
        }

        $address = trim((string) $address);
        if ($address === '' || $address === '—' || $address === 'liên hệ khách') {
            return null;
        }

        return 'geo:0,0?q='.rawurlencode($address);
    }

    private static function formatCoordinate(float $value): string
    {
        return number_format($value, 6, '.', '');
    }

    private static function geoUrl(float $lat, float $lng, ?string $label = null): string
    {
        $coords = self::formatCoordinate($lat).','.self::formatCoordinate($lng);
        $query = $coords;
        if ($label !== null && $label !== '') {
            $query .= '('.str_replace(['(', ')'], '', $label).')';
        }

        return 'geo:'.$coords.'?q='.rawurlencode($query);
    }

    /** @return array{label: string, url: string, use_current_origin?: bool}|null */
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
                self::dropoffNavigationLabel($booking),
            );

            return $url ? [
                'label' => 'Chỉ đường',
                'url'   => $url,
            ] : null;
        }

        $url = self::directionsUrl(
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            self::pickupNavigationLabel($booking),
        );

        return $url ? ['label' => 'Chỉ đường', 'url' => $url] : null;
    }

    /** @return array{label: string, url: string}|null */
    public static function driverPickupTarget(Booking $booking): ?array
    {
        $url = self::directionsUrl(
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            self::pickupNavigationLabel($booking),
        );

        return $url ? ['label' => 'Chỉ đường', 'url' => $url] : null;
    }

    private static function pickupNavigationLabel(Booking $booking): string
    {
        $label = $booking->pickupLabel();

        return $label !== '—' ? $label : $booking->driverPickupDetailLabel();
    }

    private static function dropoffNavigationLabel(Booking $booking): string
    {
        $label = $booking->dropoffLabel();

        return $label !== '—' ? $label : $booking->driverDropoffDetailLabel();
    }
}
