<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;

class AdminOperatorAlertService
{
    private const CACHE_KEY = 'admin_operator_alerts';

    private const MAX_ALERTS = 30;

    public function recordDriverAccepted(Booking $booking): void
    {
        $booking->loadMissing(['schedule.route']);

        $driverName = trim((string) ($booking->schedule?->driver_name ?? ''));
        if ($driverName === '') {
            $driverName = 'Tài xế';
        }

        $passenger = trim((string) ($booking->passenger_name ?: $booking->contact_phone ?: 'Khách'));
        $reference = $booking->booking_reference ?? ('#' . $booking->id);
        $route = $booking->schedule?->route;
        $routeLabel = $route
            ? trim(($route->departure ?? '') . ' → ' . ($route->destination ?? ''))
            : '';

        $message = $driverName . ' nhận chuyến ' . $reference . ' · ' . $passenger;
        if ($routeLabel !== '' && $routeLabel !== '→') {
            $message .= ' · ' . $routeLabel;
        }

        $this->push([
            'id'         => 'driver_accepted:' . $booking->id . ':' . now()->timestamp,
            'type'       => 'driver_accepted',
            'booking_id' => (int) $booking->id,
            'title'      => 'Tài xế đã nhận cuốc',
            'message'    => $message,
            'at'         => now()->toIso8601String(),
        ]);
    }

    /** @param array<string, mixed> $alert */
    private function push(array $alert): void
    {
        $alerts = Cache::get(self::CACHE_KEY, []);
        if (! is_array($alerts)) {
            $alerts = [];
        }

        $alerts[] = $alert;

        if (count($alerts) > self::MAX_ALERTS) {
            $alerts = array_slice($alerts, -self::MAX_ALERTS);
        }

        Cache::put(self::CACHE_KEY, $alerts, now()->addHours(2));
    }

    /** @return list<array<string, mixed>> */
    public function pullAlerts(): array
    {
        $alerts = Cache::pull(self::CACHE_KEY, []);

        return is_array($alerts) ? array_values($alerts) : [];
    }
}
