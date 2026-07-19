<?php

namespace App\Support;

/** 5 tone Web Audio — dùng chung khách/tài xế; mặc định nền tảng ở NotificationSoundSettings. */
class DriverSoundPresets
{
    public const DEFAULT = 'tone1';

    /** Mặc định hiện tại (admin), fallback tone1. */
    public static function platformDefault(): string
    {
        try {
            return NotificationSoundSettings::preset();
        } catch (\Throwable) {
            return self::DEFAULT;
        }
    }

    /**
     * @return array<string, array{label: string, label_en: string}>
     */
    public static function options(): array
    {
        return [
            'tone1' => ['label' => 'Chuông nhanh (mặc định)', 'label_en' => 'Quick chime (default)'],
            'tone2' => ['label' => 'Ping nhẹ', 'label_en' => 'Soft ping'],
            'tone3' => ['label' => 'Nhịp đôi', 'label_en' => 'Double beat'],
            'tone4' => ['label' => 'Còi ngắn', 'label_en' => 'Short alert'],
            'tone5' => ['label' => 'Gợn sóng', 'label_en' => 'Wave pulse'],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function isValid(?string $key): bool
    {
        return is_string($key) && isset(self::options()[$key]);
    }

    public static function normalize(?string $key): string
    {
        if (self::isValid($key)) {
            return $key;
        }

        return self::platformDefault();
    }

    public static function label(string $key, string $locale = 'vi'): string
    {
        $opt = self::options()[self::normalize($key)];

        return $locale === 'en' ? $opt['label_en'] : $opt['label'];
    }
}
