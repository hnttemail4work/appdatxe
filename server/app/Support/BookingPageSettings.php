<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BookingPageSettings
{
    public const SETTING_KEY = 'booking_page';

    public const DEFAULT_TITLE = 'Xe của chúng tôi';

    public const BANNER_DIR = 'booking-page';

    public static function heroTitle(): string
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);

        $title = trim((string) ($stored['hero_title'] ?? ''));

        return $title !== '' ? $title : self::DEFAULT_TITLE;
    }

    public static function bannerStoragePath(): ?string
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);
        $path = trim((string) ($stored['banner_path'] ?? ''));

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    public static function bannerUrl(): ?string
    {
        $path = self::bannerStoragePath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function hasBanner(): bool
    {
        return self::bannerStoragePath() !== null;
    }

    /** @return array{hero_title: string, banner_url: ?string, has_banner: bool} */
    public static function forAdmin(): array
    {
        return [
            'hero_title'  => self::heroTitle(),
            'banner_url'  => self::bannerUrl(),
            'has_banner'  => self::hasBanner(),
        ];
    }

    public static function saveHeroTitle(string $title): void
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);
        $stored['hero_title'] = trim($title) !== '' ? trim($title) : self::DEFAULT_TITLE;

        PlatformSetting::setValue(self::SETTING_KEY, $stored, 'ui');
    }

    public static function storeBanner(UploadedFile $file): void
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);
        $previous = trim((string) ($stored['banner_path'] ?? ''));

        if ($previous !== '' && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        Storage::disk('public')->makeDirectory(self::BANNER_DIR);
        $path = $file->store(self::BANNER_DIR, 'public');

        $stored['banner_path'] = $path;
        if (empty($stored['hero_title'])) {
            $stored['hero_title'] = self::DEFAULT_TITLE;
        }

        PlatformSetting::setValue(self::SETTING_KEY, $stored, 'ui');
    }

    public static function removeBanner(): void
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);
        $previous = trim((string) ($stored['banner_path'] ?? ''));

        if ($previous !== '' && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        unset($stored['banner_path']);
        PlatformSetting::setValue(self::SETTING_KEY, $stored, 'ui');
    }
}
