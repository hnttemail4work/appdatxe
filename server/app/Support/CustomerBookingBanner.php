<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Storage;

class CustomerBookingBanner
{
    public const SETTING_KEY = 'customer_booking_banner';

    /** @return array{image_path?: string|null} */
    public static function settings(): array
    {
        return PlatformSetting::getValue(self::SETTING_KEY, []);
    }

    public static function imagePath(): ?string
    {
        $path = trim((string) (self::settings()['image_path'] ?? ''));

        return $path !== '' ? $path : null;
    }

    public static function imageUrl(): ?string
    {
        $path = self::imagePath();
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function hasBanner(): bool
    {
        return self::imageUrl() !== null;
    }
}
