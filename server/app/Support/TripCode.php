<?php

namespace App\Support;

use Illuminate\Support\Str;

class TripCode
{
    public static function generate(): string
    {
        return 'TRP-' . Str::upper(Str::random(8));
    }

    public static function short(?string $code): string
    {
        $code = (string) $code;

        if ($code === '') {
            return '';
        }

        return str_starts_with($code, 'TRP-') ? substr($code, 4) : $code;
    }
}
