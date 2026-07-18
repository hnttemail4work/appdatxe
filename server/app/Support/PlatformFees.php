<?php

namespace App\Support;

/**
 * Tỷ lệ phí / hoa hồng / đơn giá tạm (hardcoded).
 * Admin cấu hình tính tiền đã gỡ — sẽ làm lại sau.
 */
class PlatformFees
{
    public const APP_COMMISSION_PERCENT = 2.0;

    public const REFERRAL_COMMISSION_FIRST_PERCENT = 8.0;

    public const BOOKING_QR_DISCOUNT_PERCENT = 2.0;

    public const DRIVER_INVITE_QR_DISCOUNT_PERCENT = 2.0;

    public const KM_RATE_UNDER_100 = 13000;

    public const KM_RATE_OVER_100 = 10000;

    public static function appCommissionPercent(): float
    {
        return self::APP_COMMISSION_PERCENT;
    }

    /** % hoa hồng người giới thiệu — mã admin tạo từ hệ thống. */
    public static function referralCommissionFirstPercent(): float
    {
        return self::REFERRAL_COMMISSION_FIRST_PERCENT;
    }

    /** % giảm giá khách khi quét mã QR từ chuyến hoàn tất — không tính vào doanh thu admin. */
    public static function bookingQrDiscountPercent(): float
    {
        return self::BOOKING_QR_DISCOUNT_PERCENT;
    }

    /** % giảm giá khách khi quét QR «Mời bạn bè» của tài xế. */
    public static function driverInviteQrDiscountPercent(): float
    {
        return self::DRIVER_INVITE_QR_DISCOUNT_PERCENT;
    }

    /** @deprecated Dùng {@see bookingQrDiscountPercent()} hoặc {@see referralCommissionFirstPercent()}. */
    public static function referralCommissionRepeatPercent(): float
    {
        return self::bookingQrDiscountPercent();
    }

    public static function kmRateUnder100(): int
    {
        return self::KM_RATE_UNDER_100;
    }

    public static function kmRateOver100(): int
    {
        return self::KM_RATE_OVER_100;
    }

    /** Giá khách — làm tròn đến chục nghìn gần nhất (1.096.200 → 1.100.000; 1.254.567 → 1.250.000). */
    public static function roundDisplayPrice(float|int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        return (int) (round((float) $amount / 10_000) * 10_000);
    }

    /** Tiền cả xe theo km — đơn giá tạm ≤100 km / phần vượt. */
    public static function wholeCarBaseFromDistanceKm(float $distanceKm): int
    {
        $km = max(0.0, $distanceKm);
        if ($km <= 0) {
            return 0;
        }

        $underRate = self::kmRateUnder100();
        $overRate = self::kmRateOver100();

        if ($km <= 100) {
            return (int) round($km * $underRate);
        }

        return (int) round((100 * $underRate) + (($km - 100) * $overRate));
    }

    /** @deprecated Dùng {@see roundDisplayPrice()} */
    public static function roundDownPrice(float|int $amount): int
    {
        return self::roundDisplayPrice($amount);
    }
}
