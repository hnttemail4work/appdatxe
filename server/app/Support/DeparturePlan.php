<?php

namespace App\Support;

use Carbon\Carbon;

/** Thời điểm đặt chuyến — ảnh hưởng hệ số giá. */
final class DeparturePlan
{
    public const TODAY = 'today';

    public const TOMORROW = 'tomorrow';

    public const LATER = 'later';

    /** Số ngày placeholder khi khách chọn hẹn sau (chưa chốt ngày). */
    public const LATER_PLACEHOLDER_DAYS = 30;

    public static function normalize(?string $plan): string
    {
        $plan = strtolower(trim((string) $plan));

        return in_array($plan, [self::TODAY, self::TOMORROW, self::LATER], true)
            ? $plan
            : self::TODAY;
    }

    public static function label(string $plan): string
    {
        return match (self::normalize($plan)) {
            self::TODAY    => 'Trong ngày',
            self::TOMORROW => 'Ngày mai',
            self::LATER    => 'Hẹn sau',
        };
    }

    /** Hệ số nhân trên giá chuẩn (một chiều theo km). */
    public static function priceMultiplier(string $plan): float
    {
        return match (self::normalize($plan)) {
            self::TODAY    => 1.5,
            self::TOMORROW => 2.0,
            default        => 1.0,
        };
    }

    public static function surchargePercent(string $plan): int
    {
        return match (self::normalize($plan)) {
            self::TODAY    => 50,
            self::TOMORROW => 100,
            default        => 0,
        };
    }

    public static function resolveServiceDate(string $plan, ?string $requested = null, ?Carbon $reference = null): string
    {
        $reference ??= now();
        $plan = self::normalize($plan);

        if ($plan === self::TODAY) {
            return ServiceDate::dayStart($reference)->toDateString();
        }

        if ($plan === self::TOMORROW) {
            return ServiceDate::dayStart($reference)->addDay()->toDateString();
        }

        $requested = trim((string) $requested);
        if ($requested !== '') {
            return ServiceDate::parse($requested)->toDateString();
        }

        return ServiceDate::dayStart($reference)->addDays(self::LATER_PLACEHOLDER_DAYS)->toDateString();
    }
}
