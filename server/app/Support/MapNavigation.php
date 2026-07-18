<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Schedule;

/** Liên kết mở bản đồ chỉ đường (geo URI + Google Maps). */
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

    /**
     * Google Maps Directions — origin/destination theo tọa độ.
     * Thiếu origin → Google dùng vị trí hiện tại của thiết bị.
     */
    public static function googleDirectionsUrl(
        ?float $originLat,
        ?float $originLng,
        ?float $destLat,
        ?float $destLng,
        ?string $destAddress = null,
    ): ?string {
        $destination = null;
        if ($destLat !== null && $destLng !== null) {
            $destination = self::formatCoordinate($destLat).','.self::formatCoordinate($destLng);
        } else {
            $address = trim((string) $destAddress);
            if ($address !== '' && $address !== '—' && $address !== 'liên hệ khách') {
                $destination = $address;
            }
        }

        if ($destination === null) {
            return null;
        }

        $params = [
            'api'         => '1',
            'destination' => $destination,
            'travelmode'  => 'driving',
        ];

        if ($originLat !== null && $originLng !== null) {
            $params['origin'] = self::formatCoordinate($originLat).','.self::formatCoordinate($originLng);
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($params);
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

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private static function coordsOrProvinceFallback(?float $lat, ?float $lng, ?string $province): array
    {
        if ($lat !== null && $lng !== null) {
            return [(float) $lat, (float) $lng];
        }

        $center = ProvinceCenters::forProvince($province);
        if ($center === null) {
            return [null, null];
        }

        return [(float) $center['lat'], (float) $center['lng']];
    }

    /**
     * @return array{
     *   label: string,
     *   url: string,
     *   google_url: string|null,
     *   dest_lat: float|null,
     *   dest_lng: float|null,
     *   origin_lat: float|null,
     *   origin_lng: float|null,
     *   use_current_origin: bool
     * }|null
     */
    public static function driverTargetForSchedule(Schedule $schedule, Booking $booking): ?array
    {
        $schedule->loadMissing('route');
        $stage = $schedule->resolvedDriverStage();
        $toDropoff = in_array($stage, [
            Schedule::DRIVER_STAGE_PICKED_UP,
            Schedule::DRIVER_STAGE_RUNNING,
        ], true);

        if ($toDropoff) {
            $destLabel = self::dropoffNavigationLabel($booking);
            [$destLat, $destLng] = self::coordsOrProvinceFallback(
                $booking->dropoff_lat !== null ? (float) $booking->dropoff_lat : null,
                $booking->dropoff_lng !== null ? (float) $booking->dropoff_lng : null,
                $schedule->route?->destination,
            );
            [$originLat, $originLng] = self::coordsOrProvinceFallback(
                $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
                $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
                $schedule->route?->departure,
            );

            return self::packNavPayload(
                self::directionsUrl($destLat, $destLng, $destLabel),
                self::googleDirectionsUrl($originLat, $originLng, $destLat, $destLng, $destLabel),
                $destLat,
                $destLng,
                $originLat,
                $originLng,
                useCurrentOrigin: $originLat === null || $originLng === null,
            );
        }

        $destLabel = self::pickupNavigationLabel($booking);
        [$destLat, $destLng] = self::coordsOrProvinceFallback(
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            $schedule->route?->departure,
        );

        return self::packNavPayload(
            self::directionsUrl($destLat, $destLng, $destLabel),
            self::googleDirectionsUrl(null, null, $destLat, $destLng, $destLabel),
            $destLat,
            $destLng,
            null,
            null,
            useCurrentOrigin: true,
        );
    }

    /** @return array{label: string, url: string, google_url: string|null, dest_lat: float|null, dest_lng: float|null, origin_lat: float|null, origin_lng: float|null, use_current_origin: bool}|null */
    public static function driverPickupTarget(Booking $booking, ?Schedule $schedule = null): ?array
    {
        $schedule ??= $booking->schedule;
        $schedule?->loadMissing('route');

        $destLabel = self::pickupNavigationLabel($booking);
        [$destLat, $destLng] = self::coordsOrProvinceFallback(
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            $schedule?->route?->departure,
        );

        return self::packNavPayload(
            self::directionsUrl($destLat, $destLng, $destLabel),
            self::googleDirectionsUrl(null, null, $destLat, $destLng, $destLabel),
            $destLat,
            $destLng,
            null,
            null,
            useCurrentOrigin: true,
        );
    }

    /**
     * @return array{
     *   label: string,
     *   url: string,
     *   google_url: string|null,
     *   dest_lat: float|null,
     *   dest_lng: float|null,
     *   origin_lat: float|null,
     *   origin_lng: float|null,
     *   use_current_origin: bool
     * }|null
     */
    private static function packNavPayload(
        ?string $geoUrl,
        ?string $googleUrl,
        ?float $destLat,
        ?float $destLng,
        ?float $originLat,
        ?float $originLng,
        bool $useCurrentOrigin,
    ): ?array {
        if (! $geoUrl && ! $googleUrl) {
            return null;
        }

        return [
            'label'              => 'Điều hướng',
            'url'                => $geoUrl ?: (string) $googleUrl,
            'google_url'         => $googleUrl,
            'dest_lat'           => $destLat,
            'dest_lng'           => $destLng,
            'origin_lat'         => $originLat,
            'origin_lng'         => $originLng,
            'use_current_origin' => $useCurrentOrigin,
        ];
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
