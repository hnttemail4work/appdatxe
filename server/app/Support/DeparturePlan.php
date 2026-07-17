<?php

namespace App\Support;

use Carbon\Carbon;

/** Thời điểm đặt chuyến — ảnh hưởng hệ số giá. */
final class DeparturePlan
{
    public const ONE_WAY = 'oneway';

    public const TODAY = 'today';

    public const TOMORROW = 'tomorrow';

    public const LATER = 'later';

    /** Số ngày chờ về tối thiểu (> 2 ngày). */
    public const MIN_LATER_RETURN_DAYS = 3;

    public const MAX_LATER_RETURN_DAYS = 60;

    public const DEFAULT_LATER_RETURN_DAYS = 3;

    /** @deprecated Nhắc đón theo ngày về (service_date). */
    public const LATER_PICKUP_REMINDER_DAYS = 2;

    public static function normalize(?string $plan): string
    {
        $plan = strtolower(trim((string) $plan));

        return in_array($plan, [self::ONE_WAY, self::TODAY, self::TOMORROW, self::LATER], true)
            ? $plan
            : self::ONE_WAY;
    }

    public static function label(string $plan): string
    {
        return match (self::normalize($plan)) {
            self::ONE_WAY   => 'Một chiều',
            self::TODAY    => 'Về trong ngày',
            self::TOMORROW => 'Về ngày mai',
            self::LATER    => 'Trên 2 ngày',
        };
    }

    /** @return array<string, string> */
    public static function labelsForJs(): array
    {
        return [
            self::ONE_WAY   => self::label(self::ONE_WAY),
            self::TODAY     => self::label(self::TODAY),
            self::TOMORROW  => self::label(self::TOMORROW),
            self::LATER     => self::label(self::LATER),
        ];
    }

    public static function displayLabel(string $plan, ?int $laterReturnDays = null): string
    {
        $plan = self::normalize($plan);

        if ($plan === self::LATER) {
            $days = self::normalizeLaterReturnDays($laterReturnDays);

            return self::label($plan) . ' (' . $days . ' ngày)';
        }

        return self::label($plan);
    }

    /** Nhãn ngắn cho khách xem chuyến — ví dụ «4 ngày», «Một chiều». */
    public static function guestStayLabel(string $plan, ?int $laterReturnDays = null): string
    {
        $plan = self::normalize($plan);

        if ($plan === self::LATER) {
            return self::normalizeLaterReturnDays($laterReturnDays) . ' ngày';
        }

        return self::label($plan);
    }

    public static function normalizeLaterReturnDays(mixed $days): int
    {
        $value = (int) $days;

        if ($value < self::MIN_LATER_RETURN_DAYS) {
            return self::DEFAULT_LATER_RETURN_DAYS;
        }

        return min($value, self::MAX_LATER_RETURN_DAYS);
    }

    /** Hệ số nhân trên giá chuẩn (một chiều theo km). */
    public static function priceMultiplier(string $plan, ?int $laterReturnDays = null): float
    {
        $plan = self::normalize($plan);

        if ($plan === self::LATER) {
            return PlatformFees::departurePlanLaterPriceMultiplier(
                self::normalizeLaterReturnDays($laterReturnDays),
            );
        }

        return PlatformFees::departurePlanPriceMultiplier($plan);
    }

    public static function surchargePercent(string $plan, ?int $laterReturnDays = null): int
    {
        $plan = self::normalize($plan);

        if ($plan === self::LATER) {
            $days = self::normalizeLaterReturnDays($laterReturnDays);

            return (int) round($days * PlatformFees::departurePlanLaterPercentPerDay());
        }

        return (int) round(PlatformFees::departurePlanSurchargePercent($plan));
    }

    public static function resolveServiceDate(
        string $plan,
        ?string $requested = null,
        ?Carbon $reference = null,
        ?int $laterReturnDays = null,
    ): string {
        $reference ??= now();
        $plan = self::normalize($plan);

        if (in_array($plan, [self::TODAY], true)) {
            return ServiceDate::dayStart($reference)->toDateString();
        }

        if ($plan === self::ONE_WAY) {
            $requested = trim((string) $requested);
            if ($requested !== '') {
                return ServiceDate::parse($requested)->toDateString();
            }

            return ServiceDate::dayStart($reference)->toDateString();
        }

        if ($plan === self::TOMORROW) {
            return ServiceDate::dayStart($reference)->addDay()->toDateString();
        }

        if ($plan === self::LATER) {
            return ServiceDate::dayStart($reference)
                ->addDays(self::normalizeLaterReturnDays($laterReturnDays))
                ->toDateString();
        }

        $requested = trim((string) $requested);
        if ($requested !== '') {
            return ServiceDate::parse($requested)->toDateString();
        }

        return ServiceDate::dayStart($reference)->toDateString();
    }

    public static function laterPickupReminderStartsAt(\Carbon\Carbon $bookedAt): \Carbon\Carbon
    {
        return ServiceDate::dayStart($bookedAt)->addDays(self::LATER_PICKUP_REMINDER_DAYS);
    }
}
