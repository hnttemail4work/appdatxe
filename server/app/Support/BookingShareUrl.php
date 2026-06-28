<?php

namespace App\Support;

class BookingShareUrl
{
    public static function guest(?string $driverCode = null): string
    {
        $url = route('booking.index');

        $code = strtoupper(trim((string) $driverCode));

        if ($code !== '') {
            return $url . '?tx=' . urlencode($code);
        }

        return $url;
    }
}
