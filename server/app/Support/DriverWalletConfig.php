<?php

namespace App\Support;

/** Quy tắc ví tài xế — nạp tối thiểu 100k để kích hoạt; sau doanh thu 200k giữ số dư > 100k. */
class DriverWalletConfig
{
    /** Tổng doanh thu đạt ngưỡng này → bật cổng ví (yêu cầu số dư tối thiểu). */
    public const REVENUE_THRESHOLD = 200_000;

    /** Số dư tối thiểu để nhận cuốc sau khi đã bật cổng ví. */
    public const MIN_BALANCE = 100_000;

    /** Số tiền nạp tối thiểu mỗi lần & tổng nạp để kích hoạt tài xế chính thức. */
    public const MIN_DEPOSIT = 100_000;

    public const ACTIVATION_DEPOSIT = self::MIN_DEPOSIT;

    /** Tối đa số yêu cầu nạp chờ duyệt cùng lúc mỗi tài xế. */
    public const MAX_PENDING_DEPOSITS = 1;

    public static function commissionRate(): float
    {
        return PlatformFees::appCommissionPercent();
    }

    /** Phí nền tảng (mặc định 2%) trên doanh thu chuyến. */
    public static function platformFee(int $revenue): int
    {
        if ($revenue <= 0) {
            return 0;
        }

        return (int) round($revenue * self::commissionRate() / 100, 0);
    }

    /** Chỉ trừ phí khi tài xế đã nạp ví và tổng doanh thu đã vượt ngưỡng (không phải lần đầu đạt ngưỡng). */
    public static function shouldDeductPlatformFee(string $category, bool $walletActivated): bool
    {
        return $walletActivated && $category === 'over_threshold';
    }

    /**
     * Phân loại kết chuyến theo tổng doanh thu tích lũy — không theo giá từng chuyến.
     *
     * - under_threshold: tổng doanh thu sau chuyến vẫn < 200k
     * - first_over_threshold: lần đầu tổng doanh thu đạt ≥ 200k
     * - over_threshold: tổng doanh thu trước chuyến đã ≥ 200k
     */
    public static function resolveSettlementCategory(int $tripRevenue, int $cumulativeRevenueBefore): string
    {
        $cumulativeAfter = $cumulativeRevenueBefore + $tripRevenue;

        if ($cumulativeAfter < self::REVENUE_THRESHOLD) {
            return 'under_threshold';
        }

        if ($cumulativeRevenueBefore < self::REVENUE_THRESHOLD) {
            return 'first_over_threshold';
        }

        return 'over_threshold';
    }

    public static function revenueThresholdShortLabel(): string
    {
        return number_format((int) (self::REVENUE_THRESHOLD / 1000), 0, ',', '.') . 'k';
    }

    public static function minBalanceFormatted(): string
    {
        return Money::vnd(self::MIN_BALANCE);
    }

    public static function minDepositFormatted(): string
    {
        return Money::vnd(self::MIN_DEPOSIT);
    }
}
