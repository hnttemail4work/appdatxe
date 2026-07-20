<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\ReferralCode;
use Illuminate\Support\Collection;

/**
 * Chia doanh thu cuốc trừ ví sau hoàn thành:
 * - Chuẩn (không KM, không GT): TX 90% · Admin 10%
 * - Có khuyến mãi chuyến (discount): TX 90% · Admin 8% · (2% absorb)
 * - Có người giới thiệu (mã GT + hoa hồng): TX 90% · Admin 2% · GT 8%
 */
class WalletTripFeeSplit
{
    public const DRIVER_PERCENT = 90;

    public const ADMIN_PERCENT_STANDARD = 10;

    public const ADMIN_PERCENT_PROMO = 8;

    public const ADMIN_PERCENT_REFERRER = 2;

    public const REFERRER_PERCENT = 8;

    /**
     * @param  Collection<int, Booking>|list<Booking>  $bookings
     * @return array{
     *   case: string,
     *   revenue: int,
     *   driver_percent: int,
     *   admin_percent: int,
     *   referrer_percent: int,
     *   absorbed_percent: int,
     *   driver_amount: int,
     *   admin_amount: int,
     *   referrer_amount: int,
     *   absorbed_amount: int
     * }
     */
    public static function forBookings(Collection|array $bookings): array
    {
        $collection = $bookings instanceof Collection ? $bookings : collect($bookings);
        $revenue = (int) $collection->sum(fn (Booking $b): int => $b->tripRevenueAmount());

        if ($revenue <= 0) {
            return self::emptySplit();
        }

        $hasReferrer = $collection->contains(fn (Booking $b): bool => self::bookingHasReferrerCommission($b));
        $hasPromo = $collection->contains(fn (Booking $b): bool => self::bookingHasPromoDiscount($b));

        if ($hasReferrer) {
            return self::buildSplit($revenue, 'referrer', self::ADMIN_PERCENT_REFERRER, self::REFERRER_PERCENT, 0);
        }

        if ($hasPromo) {
            return self::buildSplit($revenue, 'promo', self::ADMIN_PERCENT_PROMO, 0, 2);
        }

        return self::buildSplit($revenue, 'standard', self::ADMIN_PERCENT_STANDARD, 0, 0);
    }

    public static function forBooking(Booking $booking): array
    {
        return self::forBookings([$booking]);
    }

    public static function bookingHasReferrerCommission(Booking $booking): bool
    {
        $booking->loadMissing('appliedReferralCode');
        $code = $booking->appliedReferralCode;
        if (! $code || $code->type !== ReferralCode::TYPE_REFERRER) {
            return false;
        }

        return $code->commissionPercent() > 0;
    }

    public static function bookingHasPromoDiscount(Booking $booking): bool
    {
        if ((float) ($booking->referral_discount_percent ?? 0) > 0) {
            return true;
        }

        return (int) ($booking->referral_discount_amount ?? 0) > 0;
    }

    /**
     * @return array{
     *   case: string,
     *   revenue: int,
     *   driver_percent: int,
     *   admin_percent: int,
     *   referrer_percent: int,
     *   absorbed_percent: int,
     *   driver_amount: int,
     *   admin_amount: int,
     *   referrer_amount: int,
     *   absorbed_amount: int
     * }
     */
    private static function buildSplit(
        int $revenue,
        string $case,
        int $adminPercent,
        int $referrerPercent,
        int $absorbedPercent,
    ): array {
        $driverAmount = (int) round($revenue * self::DRIVER_PERCENT / 100);
        $adminAmount = (int) round($revenue * $adminPercent / 100);
        $referrerAmount = $referrerPercent > 0
            ? (int) round($revenue * $referrerPercent / 100)
            : 0;
        $absorbedAmount = max(0, $revenue - $driverAmount - $adminAmount - $referrerAmount);

        return [
            'case'              => $case,
            'revenue'           => $revenue,
            'driver_percent'    => self::DRIVER_PERCENT,
            'admin_percent'     => $adminPercent,
            'referrer_percent'  => $referrerPercent,
            'absorbed_percent'  => $absorbedPercent,
            'driver_amount'     => $driverAmount,
            'admin_amount'      => $adminAmount,
            'referrer_amount'   => $referrerAmount,
            'absorbed_amount'   => $absorbedAmount,
        ];
    }

    /** @return array<string, int|string> */
    private static function emptySplit(): array
    {
        return [
            'case'              => 'standard',
            'revenue'           => 0,
            'driver_percent'    => self::DRIVER_PERCENT,
            'admin_percent'     => self::ADMIN_PERCENT_STANDARD,
            'referrer_percent'  => 0,
            'absorbed_percent'  => 0,
            'driver_amount'     => 0,
            'admin_amount'      => 0,
            'referrer_amount'   => 0,
            'absorbed_amount'   => 0,
        ];
    }

    /** @param  array<string, int|string>  $split */
    public static function depositNotes(array $split, int $scheduleId): string
    {
        $parts = [
            'Thu nhập cuốc trừ ví #' . $scheduleId,
            'TX ' . $split['driver_percent'] . '%',
            'Admin ' . $split['admin_percent'] . '%',
        ];

        if ((int) $split['referrer_percent'] > 0) {
            $parts[] = 'GT ' . $split['referrer_percent'] . '%';
        }

        if ((int) $split['absorbed_percent'] > 0) {
            $parts[] = 'Absorb ' . $split['absorbed_percent'] . '%';
        }

        return implode(' · ', $parts);
    }
}
