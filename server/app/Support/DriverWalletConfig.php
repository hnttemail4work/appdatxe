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

    public static function revenueThresholdShortLabel(): string
    {
        return number_format((int) (self::REVENUE_THRESHOLD / 1000), 0, ',', '.') . 'k';
    }

    public static function revenueThresholdFormatted(): string
    {
        return number_format(self::REVENUE_THRESHOLD, 0, ',', '.') . ' đ';
    }

    public static function minBalanceFormatted(): string
    {
        return number_format(self::MIN_BALANCE, 0, ',', '.') . ' đ';
    }

    public static function minDepositFormatted(): string
    {
        return number_format(self::MIN_DEPOSIT, 0, ',', '.') . ' đ';
    }
}
