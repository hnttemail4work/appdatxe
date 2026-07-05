<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ReferralCode;

use App\Support\PlatformFees;

class ReferralCodeService
{
    public function createReferrer(string $name, string $phone, int $adminUserId): ReferralCode
    {
        return ReferralCode::query()->create([
            'type'                      => ReferralCode::TYPE_REFERRER,
            'name'                      => trim($name),
            'phone'                     => trim($phone),
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'commission_percent'        => PlatformFees::referralCommissionFirstPercent(),
            'customer_discount_percent' => 0,
            'created_by'                => $adminUserId,
            'activated_at'              => now(),
        ]);
    }

    public function issueForBooking(Booking $booking): ReferralCode
    {
        $existing = ReferralCode::query()
            ->where('booking_id', $booking->id)
            ->where('type', ReferralCode::TYPE_BOOKING_TEMP)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ReferralCode::query()->create([
            'type'        => ReferralCode::TYPE_BOOKING_TEMP,
            'name'        => trim((string) $booking->passenger_name),
            'phone'       => trim((string) $booking->contact_phone),
            'booking_id'  => $booking->id,
            'status'      => ReferralCode::STATUS_PENDING,
        ]);
    }

    public function ensureForBooking(Booking $booking): ReferralCode
    {
        return $this->issueForBooking($booking);
    }

    public function purgeForBooking(Booking $booking): void
    {
        ReferralCode::query()
            ->where('booking_id', $booking->id)
            ->where('type', ReferralCode::TYPE_BOOKING_TEMP)
            ->delete();
    }

    public function activateForCompletedBooking(Booking $booking): void
    {
        ReferralCode::query()
            ->where('booking_id', $booking->id)
            ->where('type', ReferralCode::TYPE_BOOKING_TEMP)
            ->where('status', ReferralCode::STATUS_PENDING)
            ->update([
                'status'       => ReferralCode::STATUS_ACTIVE,
                'activated_at' => now(),
                'expires_at'   => now()->addDays(ReferralCode::BOOKING_CODE_VALIDITY_DAYS),
            ]);
    }

    public function deleteBookingReferralCode(ReferralCode $referralCode): void
    {
        if ($referralCode->type !== ReferralCode::TYPE_BOOKING_TEMP) {
            abort(403, 'Chỉ xóa được mã phát sinh từ đặt vé.');
        }

        $referralCode->delete();
    }

    public function suspendReferrer(ReferralCode $referralCode): void
    {
        if ($referralCode->type !== ReferralCode::TYPE_REFERRER) {
            abort(403, 'Chỉ ẩn được mã người giới thiệu tạo tay.');
        }

        $referralCode->update(['status' => ReferralCode::STATUS_SUSPENDED]);
    }

    public function restoreReferrer(ReferralCode $referralCode): void
    {
        if ($referralCode->type !== ReferralCode::TYPE_REFERRER) {
            abort(403);
        }

        if ($referralCode->status !== ReferralCode::STATUS_SUSPENDED) {
            abort(422, 'Mã này không ở trạng thái tạm ngưng.');
        }

        $referralCode->update([
            'status'       => ReferralCode::STATUS_ACTIVE,
            'activated_at' => $referralCode->activated_at ?? now(),
        ]);
    }

    public function resolveUsableCode(?string $rawCode): ?ReferralCode
    {
        $code = strtoupper(trim((string) $rawCode));
        if ($code === '') {
            return null;
        }

        $referral = ReferralCode::query()->where('code', $code)->first();
        if (! $referral || ! $referral->isUsable()) {
            return null;
        }

        return $referral;
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /** So khớp SĐT theo 9 số cuối (0xxx / +84xxx). */
    public function phonesMatch(?string $a, ?string $b): bool
    {
        $da = self::normalizePhone((string) $a);
        $db = self::normalizePhone((string) $b);

        if ($da === '' || $db === '') {
            return false;
        }

        $sa = strlen($da) >= 9 ? substr($da, -9) : $da;
        $sb = strlen($db) >= 9 ? substr($db, -9) : $db;

        return $sa === $sb;
    }

    public function isReferrerPhone(?ReferralCode $referral, ?string $contactPhone): bool
    {
        if (! $referral || $contactPhone === null || trim($contactPhone) === '') {
            return false;
        }

        return $this->phonesMatch($referral->phone, $contactPhone);
    }

    public function phoneHasUsedReferralBefore(string $contactPhone): bool
    {
        $digits = self::normalizePhone($contactPhone);
        if ($digits === '') {
            return false;
        }

        $suffix = strlen($digits) >= 9 ? substr($digits, -9) : $digits;

        if (Booking::query()
            ->whereNotNull('applied_referral_code_id')
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('contact_phone', 'like', '%' . $suffix)
            ->exists()) {
            return true;
        }

        return ReferralCode::query()
            ->where('type', ReferralCode::TYPE_BOOKING_TEMP)
            ->where('phone', 'like', '%' . $suffix)
            ->exists();
    }

    /** Phần trăm giảm giá cho khách — chỉ mã từ đặt vé; 0 với mã người giới thiệu (admin). */
    public function customerDiscountPercent(?ReferralCode $referral, string $contactPhone): float
    {
        if (! $this->qualifiesForCustomerDiscount($referral, $contactPhone)) {
            return 0.0;
        }

        return $referral->customerDiscountPercent();
    }

    /** Ghi nhận mã GT lên vé (hoa hồng 8%) — chỉ mã người giới thiệu do admin tạo. */
    public function shouldAttributeBooking(?ReferralCode $referral, string $contactPhone): bool
    {
        if (! $referral || ! $referral->isUsable()) {
            return false;
        }

        if ($referral->type !== ReferralCode::TYPE_REFERRER) {
            return false;
        }

        if ($this->isReferrerPhone($referral, $contactPhone)) {
            return false;
        }

        return true;
    }

    public function qualifiesForCustomerDiscount(?ReferralCode $referral, ?string $contactPhone): bool
    {
        if (! $referral || ! $referral->isUsable() || ! $referral->grantsCustomerDiscount()) {
            return false;
        }

        if ($contactPhone === null || $contactPhone === '') {
            return true;
        }

        if ($this->isReferrerPhone($referral, $contactPhone)) {
            return false;
        }

        return ! $this->phoneHasUsedReferralBefore($contactPhone);
    }

    /**
     * @return array{
     *   percent: float,
     *   eligible: bool,
     *   reason: string|null,
     *   code: string|null,
     *   type: string|null,
     *   attribution_only: bool
     * }
     */
    public function discountMeta(?ReferralCode $referral, ?string $contactPhone = null): array
    {
        if (! $referral || ! $referral->isUsable()) {
            return [
                'percent'          => 0.0,
                'eligible'         => false,
                'reason'           => null,
                'code'             => $referral?->code,
                'type'             => $referral?->type,
                'attribution_only' => false,
            ];
        }

        if ($referral->type === ReferralCode::TYPE_REFERRER) {
            $percent = $referral->customerDiscountPercent();
            if ($percent <= 0) {
                return [
                    'percent'          => 0.0,
                    'eligible'         => false,
                    'reason'           => null,
                    'code'             => $referral->code,
                    'type'             => $referral->type,
                    'attribution_only' => true,
                ];
            }

            if ($contactPhone !== null && $contactPhone !== '' && $this->isReferrerPhone($referral, $contactPhone)) {
                return [
                    'percent'          => 0.0,
                    'eligible'         => false,
                    'reason'           => 'SĐT đặt trùng với chủ mã giới thiệu — không áp dụng giảm giá.',
                    'code'             => $referral->code,
                    'type'             => $referral->type,
                    'attribution_only' => true,
                ];
            }

            if ($contactPhone !== null && $contactPhone !== '' && $this->phoneHasUsedReferralBefore($contactPhone)) {
                return [
                    'percent'          => 0.0,
                    'eligible'         => false,
                    'reason'           => 'Số điện thoại này đã từng dùng mã giới thiệu.',
                    'code'             => $referral->code,
                    'type'             => $referral->type,
                    'attribution_only' => true,
                ];
            }

            return [
                'percent'          => $percent,
                'eligible'         => true,
                'reason'           => null,
                'code'             => $referral->code,
                'type'             => $referral->type,
                'attribution_only' => false,
            ];
        }

        if ($contactPhone !== null && $contactPhone !== '' && $this->isReferrerPhone($referral, $contactPhone)) {
            return [
                'percent'          => 0.0,
                'eligible'         => false,
                'reason'           => 'SĐT đặt trùng với chủ mã giới thiệu — không áp dụng giảm giá.',
                'code'             => $referral->code,
                'type'             => $referral->type,
                'attribution_only' => false,
            ];
        }

        if ($contactPhone !== null && $contactPhone !== '' && $this->phoneHasUsedReferralBefore($contactPhone)) {
            return [
                'percent'          => 0.0,
                'eligible'         => false,
                'reason'           => 'Số điện thoại này đã từng dùng mã giới thiệu.',
                'code'             => $referral->code,
                'type'             => $referral->type,
                'attribution_only' => false,
            ];
        }

        return [
            'percent'          => $referral->customerDiscountPercent(),
            'eligible'         => true,
            'reason'           => null,
            'code'             => $referral->code,
            'type'             => $referral->type,
            'attribution_only' => false,
        ];
    }

    public function applyDiscount(float $subtotal, float $discountPercent): float
    {
        if ($discountPercent <= 0 || $subtotal <= 0) {
            return (float) PlatformFees::roundUpToThousand($subtotal);
        }

        $discounted = $subtotal * (1 - $discountPercent / 100);

        return (float) PlatformFees::roundDownPrice($discounted);
    }

    /**
     * Doanh thu & hoa hồng GT theo vé đã hoàn tất — key = referral_code.id.
     *
     * @param  list<int>  $referralCodeIds
     * @return array<int, array{trips: int, revenue: int, commission: int}>
     */
    public function commissionStatsForReferralIds(array $referralCodeIds): array
    {
        if ($referralCodeIds === []) {
            return [];
        }

        $stats = [];

        Booking::query()
            ->whereIn('applied_referral_code_id', $referralCodeIds)
            ->where('trip_status', 'completed')
            ->with('appliedReferralCode')
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$stats): void {
                foreach ($bookings as $booking) {
                    $referralId = (int) $booking->applied_referral_code_id;
                    if ($referralId < 1) {
                        continue;
                    }

                    if (! isset($stats[$referralId])) {
                        $stats[$referralId] = ['trips' => 0, 'revenue' => 0, 'commission' => 0];
                    }

                    $stats[$referralId]['trips']++;
                    $stats[$referralId]['revenue'] += $booking->tripRevenueAmount();
                    $stats[$referralId]['commission'] += $booking->referrerCommissionAmount();
                }
            });

        return $stats;
    }
}
