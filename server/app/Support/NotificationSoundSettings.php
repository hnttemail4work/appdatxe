<?php

namespace App\Support;

use App\Models\PlatformSetting;

/** Âm thanh mặc định nền tảng (hộp thư / thông báo) — tài xế có thể ghi đè trong cài đặt. */
final class NotificationSoundSettings
{
    public const SETTING_KEY = 'notification_sounds';

    /** @return array{enabled: bool, preset: string} */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'preset'  => DriverSoundPresets::DEFAULT,
        ];
    }

    /** @return array{enabled: bool, preset: string} */
    public static function current(): array
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $preset = $stored['preset'] ?? null;

        return [
            'enabled' => (bool) ($stored['enabled'] ?? true),
            'preset'  => DriverSoundPresets::isValid(is_string($preset) ? $preset : null)
                ? $preset
                : DriverSoundPresets::DEFAULT,
        ];
    }

    /** @return array{enabled: bool, preset: string} */
    public static function forAdmin(): array
    {
        return self::current();
    }

    /** Payload inject JS (khách / fallback tài xế). */
    /** @return array{enabled: bool, preset: string} */
    public static function forClient(): array
    {
        return self::current();
    }

    public static function isEnabled(): bool
    {
        return self::current()['enabled'];
    }

    public static function preset(): string
    {
        return self::current()['preset'];
    }

    /**
     * @param  array{enabled?: bool, preset?: string}  $input
     */
    public static function saveFromAdmin(array $input): void
    {
        $preset = $input['preset'] ?? null;

        PlatformSetting::setValue(self::SETTING_KEY, [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'preset'  => DriverSoundPresets::isValid(is_string($preset) ? $preset : null)
                ? $preset
                : DriverSoundPresets::DEFAULT,
        ], 'notifications');
    }
}
