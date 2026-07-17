<?php

namespace App\Support;

use App\Models\PlatformSetting;

class VehicleCapacityOptions
{
    /** Danh sách mặc định khi chưa cấu hình admin. */
    public const DEFAULT_STANDARD = [4, 7, 9, 11, 12, 16, 19, 20, 22, 24, 28, 34];

    public const SETTING_KEY = 'vehicle_capacity_enabled';

    /** @var array<int, true> */
    private const BED_ROOM = [
        20 => true,
        22 => true,
    ];

    /** @return list<int> */
    public static function enabled(): array
    {
        $raw = PlatformSetting::getValue(self::SETTING_KEY, null);

        if (! is_array($raw) || $raw === []) {
            return self::DEFAULT_STANDARD;
        }

        $list = collect($raw)
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value >= 1 && $value <= 60)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $list !== [] ? $list : self::DEFAULT_STANDARD;
    }

    /** @param  list<int|string>  $capacities */
    public static function saveEnabled(array $capacities): void
    {
        $list = collect($capacities)
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value >= 1 && $value <= 60)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($list === []) {
            $list = self::DEFAULT_STANDARD;
        }

        PlatformSetting::setValue(self::SETTING_KEY, $list, 'finance');
    }

    /** @return list<int> */
    public static function knownCapacities(): array
    {
        return collect(self::DEFAULT_STANDARD)
            ->merge(self::enabled())
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function label(int $capacity): string
    {
        $text = $capacity . ' chỗ';

        if (isset(self::BED_ROOM[$capacity])) {
            $text .= ' (loại giường phòng)';
        }

        return $text;
    }

    public static function isAllowed(int $capacity): bool
    {
        return in_array($capacity, self::enabled(), true);
    }

    /** @return array<int, string> */
    public static function choices(): array
    {
        $out = [];
        foreach (self::enabled() as $capacity) {
            $out[$capacity] = self::label($capacity);
        }

        return $out;
    }
}
