<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\ReferralCode;
use App\Support\AuthIdentifier;
use App\Support\PlatformFees;
use Illuminate\Support\Collection;

class ReferralCodeService
{
    public function createReferrer(string $name, string $phone, ?int $adminUserId): ReferralCode
    {
        return ReferralCode::query()->create([
            'type'                      => ReferralCode::TYPE_REFERRER,
            'name'                      => trim($name),
            'phone'                     => trim($phone),
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'commission_percent'        => PlatformFees::referralCommissionFirstPercent(),
            'customer_discount_percent' => 0,
            'created_by'                => $adminUserId > 0 ? $adminUserId : null,
            'activated_at'              => now(),
        ]);
    }

    /** Mã QR giảm giá mời bạn bè của tài xế (nếu đã tạo). */
    public function forDriver(DriverProfile $profile): ?ReferralCode
    {
        return ReferralCode::query()
            ->where('driver_profile_id', $profile->id)
            ->where('type', ReferralCode::TYPE_REFERRER)
            ->first();
    }

    /**
     * Tạo mã QR mời bạn bè — chỉ khi chưa có.
     *
     * @throws \InvalidArgumentException
     */
    public function createForDriver(DriverProfile $profile, ?float $discountPercent = null): ReferralCode
    {
        if ($this->forDriver($profile)) {
            throw new \InvalidArgumentException('Tài xế đã có mã QR giới thiệu.');
        }

        $profile->loadMissing('user');
        $user = $profile->user;
        $name = trim((string) ($user?->preferredDisplayName() ?: $profile->driver_code ?: 'Tài xế'));
        $phone = trim((string) ($user?->phone ?: ''));
        $discount = $discountPercent !== null
            ? max(0.0, min(100.0, $discountPercent))
            : PlatformFees::driverInviteQrDiscountPercent();

        return ReferralCode::query()->create([
            'type'                      => ReferralCode::TYPE_REFERRER,
            'name'                      => $name !== '' ? $name : 'Tài xế',
            'phone'                     => $phone !== '' ? $phone : ('TX' . $profile->id),
            'driver_profile_id'         => $profile->id,
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'commission_percent'        => 0,
            'customer_discount_percent' => $discount,
            'created_by'                => $user?->id,
            'activated_at'              => now(),
        ]);
    }

    /**
     * Cập nhật % giảm giá QR hiện có (không tự tạo mới).
     *
     * @throws \InvalidArgumentException
     */
    public function updateDriverInviteDiscount(DriverProfile $profile, float $discountPercent): ReferralCode
    {
        $existing = $this->forDriver($profile);
        if (! $existing) {
            throw new \InvalidArgumentException('Chưa có mã QR — hãy tạo QR trước.');
        }

        $discount = max(0.0, min(100.0, $discountPercent));
        $profile->loadMissing('user');
        $user = $profile->user;
        $name = trim((string) ($user?->preferredDisplayName() ?: $profile->driver_code ?: 'Tài xế'));
        $phone = trim((string) ($user?->phone ?: ''));

        $patch = [
            'customer_discount_percent' => $discount,
            'commission_percent'        => 0,
        ];
        if ($name !== '' && $existing->name !== $name) {
            $patch['name'] = $name;
        }
        if ($phone !== '' && $existing->phone !== $phone) {
            $patch['phone'] = $phone;
        }

        $existing->update($patch);

        return $existing->fresh();
    }

    /**
     * Tạm ngưng QR mời bạn — không xóa; ẩn khỏi Mời bạn bè / không áp dụng mã.
     *
     * @return float|null % giảm giá trước khi ngưng (để thông báo)
     */
    public function suspendForDriver(DriverProfile $profile): ?float
    {
        $existing = $this->forDriver($profile);
        if (! $existing || $existing->isSuspended()) {
            return null;
        }

        $previousPercent = $existing->customerDiscountPercent();
        $this->suspendReferrer($existing);

        return $previousPercent;
    }

    /** Bật lại QR mời bạn đã tạm ngưng. */
    public function restoreForDriver(DriverProfile $profile): ?ReferralCode
    {
        $existing = $this->forDriver($profile);
        if (! $existing || ! $existing->isSuspended()) {
            return null;
        }

        $this->restoreReferrer($existing);

        return $existing->fresh();
    }

    /**
     * @deprecated Giữ tương thích — ưu tiên suspendForDriver.
     *
     * @return float|null % giảm giá trước khi xóa
     */
    public function deleteForDriver(DriverProfile $profile): ?float
    {
        return $this->suspendForDriver($profile);
    }

    /** @deprecated Dùng createForDriver / forDriver — giữ tương thích chỗ còn gọi cũ. */
    public function ensureForDriver(DriverProfile $profile): ReferralCode
    {
        return $this->forDriver($profile) ?? $this->createForDriver($profile);
    }

    public function assignedCommissionForDriver(DriverProfile $profile): ?ReferralCode
    {
        return ReferralCode::query()
            ->where('assigned_driver_profile_id', $profile->id)
            ->where('type', ReferralCode::TYPE_REFERRER)
            ->whereNull('driver_profile_id')
            ->where('status', ReferralCode::STATUS_ACTIVE)
            ->latest('id')
            ->first();
    }

    /**
     * Gán mã hoa hồng (admin tạo) cho tài xế — thu hồi mã cũ trên cùng TX nếu có.
     */
    public function assignCommissionToDriver(ReferralCode $referral, DriverProfile $profile): void
    {
        if (! $referral->canAssignToDriver()) {
            throw new \InvalidArgumentException('Chỉ gán được mã người giới thiệu (hoa hồng) đang sử dụng.');
        }

        if (! $profile->isApproved()) {
            throw new \InvalidArgumentException('Chỉ gán mã cho tài xế đã được duyệt.');
        }

        $inbox = app(DriverInboxService::class);

        // Thu hồi mã HH đang gán cho tài xế này (nếu khác mã mới).
        $existingOnDriver = ReferralCode::query()
            ->where('assigned_driver_profile_id', $profile->id)
            ->where('type', ReferralCode::TYPE_REFERRER)
            ->whereNull('driver_profile_id')
            ->where('id', '!=', $referral->id)
            ->get();

        foreach ($existingOnDriver as $old) {
            $old->update(['assigned_driver_profile_id' => null]);
            $inbox->notifyCommissionCodeRevoked($profile, $old);
        }

        $previousProfileId = (int) ($referral->assigned_driver_profile_id ?? 0);
        if ($previousProfileId > 0 && $previousProfileId !== (int) $profile->id) {
            $previousProfile = DriverProfile::query()->find($previousProfileId);
            $referral->update(['assigned_driver_profile_id' => null]);
            if ($previousProfile) {
                $inbox->notifyCommissionCodeRevoked($previousProfile, $referral);
            }
        }

        $referral->update(['assigned_driver_profile_id' => $profile->id]);
        $inbox->notifyCommissionCodeAssigned($profile, $referral->fresh());
    }

    public function revokeCommissionFromDriver(ReferralCode $referral): void
    {
        if (! $referral->isAssignedCommissionCode()) {
            throw new \InvalidArgumentException('Mã này chưa được gán cho tài xế.');
        }

        $profile = $referral->assignedDriverProfile;
        $referral->update(['assigned_driver_profile_id' => null]);

        if ($profile) {
            app(DriverInboxService::class)->notifyCommissionCodeRevoked($profile, $referral);
        }
    }

    /**
     * Khách đã dùng mã QR của tài xế (theo SĐT, mới nhất trước).
     *
     * @return Collection<int, object{passenger_name: string, contact_phone: string, bookings_count: int, last_booked_at: mixed}>
     */
    public function referredCustomersFor(ReferralCode $referral): Collection
    {
        return $this->referredCustomersForCodes([$referral]);
    }

    /**
     * @param  iterable<int, ReferralCode|null>  $referrals
     * @return Collection<int, object{passenger_name: string, contact_phone: string, bookings_count: int, last_booked_at: mixed}>
     */
    public function referredCustomersForCodes(iterable $referrals): Collection
    {
        $ids = collect($referrals)
            ->filter()
            ->map(fn (ReferralCode $referral): int => (int) $referral->id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Booking::query()
            ->whereIn('applied_referral_code_id', $ids)
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->orderByDesc('id')
            ->get(['passenger_name', 'contact_phone', 'created_at'])
            ->groupBy(function (Booking $booking): string {
                $digits = self::normalizePhone((string) $booking->contact_phone);

                return strlen($digits) >= 9 ? substr($digits, -9) : ($digits !== '' ? $digits : 'id-' . $booking->getKey());
            })
            ->map(function (Collection $rows) {
                /** @var Booking $latest */
                $latest = $rows->first();

                return (object) [
                    'passenger_name'  => (string) ($latest->passenger_name ?: '—'),
                    'contact_phone'   => (string) ($latest->contact_phone ?: '—'),
                    'bookings_count'  => $rows->count(),
                    'last_booked_at'  => $latest->created_at,
                ];
            })
            ->values();
    }

    public function purgeForBooking(Booking $booking): void
    {
        ReferralCode::query()
            ->where('booking_id', $booking->id)
            ->where('type', ReferralCode::TYPE_BOOKING_TEMP)
            ->delete();
    }

    public function deleteBookingReferralCode(ReferralCode $referralCode): void
    {
        if ($referralCode->type !== ReferralCode::TYPE_BOOKING_TEMP) {
            abort(403, 'Chỉ xóa được mã phát sinh từ đặt vé cũ.');
        }

        $referralCode->delete();
    }

    public function suspendReferrer(ReferralCode $referralCode): void
    {
        if ($referralCode->type !== ReferralCode::TYPE_REFERRER) {
            abort(403, 'Chỉ tạm ngưng được mã giới thiệu.');
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
        return AuthIdentifier::normalizePhone($phone);
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

        return false;
    }

    /** Phần trăm giảm giá khách khi mã GT (QR tài xế / người giới thiệu) đủ điều kiện. */
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
     *   attribution_only: bool,
     *   source_label: string|null
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
                'source_label'     => null,
            ];
        }

        $sourceLabel = $referral->customerDiscountSourceLabel();

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
                    'source_label'     => $sourceLabel,
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
                    'source_label'     => $sourceLabel,
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
                    'source_label'     => $sourceLabel,
                ];
            }

            return [
                'percent'          => $percent,
                'eligible'         => true,
                'reason'           => null,
                'code'             => $referral->code,
                'type'             => $referral->type,
                'attribution_only' => false,
                'source_label'     => $sourceLabel,
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
                'source_label'     => $sourceLabel,
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
                'source_label'     => $sourceLabel,
            ];
        }

        return [
            'percent'          => $referral->customerDiscountPercent(),
            'eligible'         => true,
            'reason'           => null,
            'code'             => $referral->code,
            'type'             => $referral->type,
            'attribution_only' => false,
            'source_label'     => $sourceLabel,
        ];
    }

    public function applyDiscount(float $subtotal, float $discountPercent): float
    {
        if ($discountPercent <= 0 || $subtotal <= 0) {
            return (float) PlatformFees::roundDisplayPrice($subtotal);
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
