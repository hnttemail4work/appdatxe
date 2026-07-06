<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AppBrandingSettings
{
    public const SETTING_KEY = 'app_branding';

    public const DEFAULT_APP_NAME = 'gozviet';

    public const DEFAULT_BRAND_TITLE = 'gozviet';

    public const DEFAULT_BRAND_TAGLINE = 'đặt xe liên tỉnh';

    public const DEFAULT_PWA_GUEST_SHORT_NAME = 'Đặt xe';

    public const DEFAULT_PWA_DRIVER_SHORT_NAME = 'Tài xế';

    public const ICON_DIR = 'app-branding';

    public static function appName(): string
    {
        $stored = self::stored();
        $name = trim((string) ($stored['app_name'] ?? ''));

        return $name !== '' ? $name : (string) config('app.name', self::DEFAULT_APP_NAME);
    }

    public static function brandTitle(): string
    {
        $stored = self::stored();
        $title = trim((string) ($stored['brand_title'] ?? ''));

        return $title !== '' ? $title : self::DEFAULT_BRAND_TITLE;
    }

    public static function brandTagline(): string
    {
        $stored = self::stored();
        $tagline = trim((string) ($stored['brand_tagline'] ?? ''));

        return $tagline !== '' ? $tagline : self::DEFAULT_BRAND_TAGLINE;
    }

    public static function pwaGuestShortName(): string
    {
        $stored = self::stored();
        $name = trim((string) ($stored['pwa_guest_short_name'] ?? ''));

        return $name !== '' ? $name : self::DEFAULT_PWA_GUEST_SHORT_NAME;
    }

    public static function pwaDriverShortName(): string
    {
        $stored = self::stored();
        $name = trim((string) ($stored['pwa_driver_short_name'] ?? ''));

        return $name !== '' ? $name : self::DEFAULT_PWA_DRIVER_SHORT_NAME;
    }

    public static function pwaShortName(string $audience): string
    {
        return $audience === PushAudience::DRIVER
            ? self::pwaDriverShortName()
            : self::pwaGuestShortName();
    }

    public static function appIconStoragePath(): ?string
    {
        $stored = self::stored();
        $path = trim((string) ($stored['icon_path'] ?? ''));

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    public static function hasAppIcon(): bool
    {
        return self::appIconStoragePath() !== null;
    }

    public static function appIconUrl(): ?string
    {
        $path = self::appIconStoragePath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function appIconAssetUrl(): string
    {
        $custom = self::appIconUrl();

        if ($custom) {
            return $custom . '?v=' . self::appIconVersion();
        }

        $fallback = public_path('favicon.svg');
        $version = is_file($fallback) ? (filemtime($fallback) ?: time()) : time();

        return asset('favicon.svg') . '?v=' . $version;
    }

    public static function appIconVersion(): int
    {
        $path = self::appIconStoragePath();

        if ($path) {
            $full = Storage::disk('public')->path($path);

            return is_file($full) ? (filemtime($full) ?: time()) : time();
        }

        $fallback = public_path('favicon.svg');

        return is_file($fallback) ? (filemtime($fallback) ?: time()) : time();
    }

    /** @return list<array{src: string, sizes: string, type: string, purpose: string}> */
    public static function manifestIcons(): array
    {
        $url = self::appIconAssetUrl();
        $mime = self::appIconMimeType();

        if (self::appIconMimeType() === 'image/svg+xml') {
            return [[
                'src'     => $url,
                'sizes'   => 'any',
                'type'    => 'image/svg+xml',
                'purpose' => 'any maskable',
            ]];
        }

        return [
            [
                'src'     => $url,
                'sizes'   => '192x192',
                'type'    => $mime,
                'purpose' => 'any',
            ],
            [
                'src'     => $url,
                'sizes'   => '512x512',
                'type'    => $mime,
                'purpose' => 'maskable',
            ],
        ];
    }

    public static function appIconMimeType(): string
    {
        $path = self::appIconStoragePath();

        if (! $path) {
            return 'image/svg+xml';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            default => 'image/png',
        };
    }

    /** @return array{app_name: string, brand_title: string, brand_tagline: string, pwa_guest_short_name: string, pwa_driver_short_name: string, app_icon_url: ?string, has_app_icon: bool} */
    public static function forAdmin(): array
    {
        return [
            'app_name'              => self::appName(),
            'brand_title'           => self::brandTitle(),
            'brand_tagline'         => self::brandTagline(),
            'pwa_guest_short_name'  => self::pwaGuestShortName(),
            'pwa_driver_short_name' => self::pwaDriverShortName(),
            'app_icon_url'          => self::appIconUrl(),
            'has_app_icon'          => self::hasAppIcon(),
        ];
    }

    public static function saveBranding(
        string $appName,
        string $brandTitle,
        string $brandTagline,
        string $pwaGuestShortName = '',
        string $pwaDriverShortName = '',
    ): void {
        $stored = self::stored();

        $stored['app_name'] = trim($appName) !== '' ? trim($appName) : self::DEFAULT_APP_NAME;
        $stored['brand_title'] = trim($brandTitle) !== '' ? trim($brandTitle) : self::DEFAULT_BRAND_TITLE;
        $stored['brand_tagline'] = trim($brandTagline) !== '' ? trim($brandTagline) : self::DEFAULT_BRAND_TAGLINE;
        $stored['pwa_guest_short_name'] = trim($pwaGuestShortName) !== ''
            ? trim($pwaGuestShortName)
            : self::DEFAULT_PWA_GUEST_SHORT_NAME;
        $stored['pwa_driver_short_name'] = trim($pwaDriverShortName) !== ''
            ? trim($pwaDriverShortName)
            : self::DEFAULT_PWA_DRIVER_SHORT_NAME;

        PlatformSetting::setValue(self::SETTING_KEY, $stored, 'ui');
    }

    public static function storeAppIcon(UploadedFile $file): void
    {
        $stored = self::stored();
        $previous = trim((string) ($stored['icon_path'] ?? ''));

        if ($previous !== '' && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        Storage::disk('public')->makeDirectory(self::ICON_DIR);
        $path = $file->store(self::ICON_DIR, 'public');

        $stored['icon_path'] = $path;
        PlatformSetting::setValue(self::SETTING_KEY, $stored, 'ui');
    }

    public static function removeAppIcon(): void
    {
        $stored = self::stored();
        $previous = trim((string) ($stored['icon_path'] ?? ''));

        if ($previous !== '' && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        unset($stored['icon_path']);
        PlatformSetting::setValue(self::SETTING_KEY, $stored, 'ui');
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = PlatformSetting::getValue(self::SETTING_KEY, []);

        return is_array($value) ? $value : [];
    }
}
