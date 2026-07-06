<?php

namespace App\Support;

use App\Models\User;

class PushAudience
{
    public const GUEST = 'guest';

    public const DRIVER = 'driver';

    public static function enabledFor(?User $user = null): bool
    {
        if (func_num_args() === 0) {
            $user = auth()->user();
        }

        return ($user?->role ?? null) !== 'admin';
    }

    /** @return self::GUEST|self::DRIVER */
    public static function resolve(?User $user = null): string
    {
        if (func_num_args() === 0) {
            $user = auth()->user();
        }

        if (! $user || $user->role === 'admin') {
            return self::GUEST;
        }

        return $user->role === 'driver' ? self::DRIVER : self::GUEST;
    }

    public static function startUrl(string $audience): string
    {
        return match ($audience) {
            self::DRIVER => '/driver/dashboard',
            default      => '/',
        };
    }

    public static function shortLabel(string $audience): string
    {
        return AppBrandingSettings::pwaShortName($audience);
    }

    public static function manifestName(string $audience, string $appName): string
    {
        return match ($audience) {
            self::DRIVER => $appName . ' · Tài xế',
            default      => $appName . ' · Đặt xe',
        };
    }
}
