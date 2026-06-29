<?php

namespace App\Support;

class BookingShareUrl
{
    public static function guest(?string $driverCode = null): string
    {
        $url = route('home');

        $code = strtoupper(trim((string) $driverCode));

        if ($code !== '') {
            return $url . '?tx=' . urlencode($code);
        }

        return $url;
    }
}
