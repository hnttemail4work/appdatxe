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
    public function createReferrer(string $name, string $phone, ?int $adminUserId, ?float $commissionPercent = null): ReferralCode
    {
        $commission = $commissionPercent;
        if ($commission === null || $commission <= 0) {
            $commission = PlatformFees::referralCommissionFirstPercent();
        }

        return ReferralCode::query()->create([
            'type'                      => ReferralCode::TYPE_REFERRER,
            'name'                      => trim($name),
            'phone'                     => trim($phone),
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'commission_percent'        => max(0.1, (float) $commission),
            'customer_discount_percent' => 0,
            'created_by'                => $adminUserId > 0 ? $adminUserId : null,
            'activated_at'              => now(),
        ]);
    }

    /**
     * Tạo mã QR gán tài xế (0% HH) — khách hoàn thành chuyến → Khách của tôi + ưu tiên nhận.
     */
    public function createDriverCustomerCode(DriverProfile $profile, ?int $adminUserId, ?string $name = null, ?string $phone = null): ReferralCode
    {
        if (! $profile->isApproved()) {
            throw new \InvalidArgumentException('Chỉ gán mã cho tài xế đã được duyệt.');
        }

        $displayName = trim((string) ($name ?: $profile->user?->preferredDisplayName() ?: $profile->driver_code ?: 'Tài xế'));
        $displayPhone = trim((string) ($phone ?: $profile->user?->phone ?: ''));

        $referral = ReferralCode::query()->create([
            'type'                      => ReferralCode::TYPE_REFERRER,
            'name'                      => $displayName !== '' ? $displayName : 'Tài xế',
            'phone'                     => $displayPhone !== '' ? $displayPhone : null,
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'commission_percent'        => 0,
            'customer_discount_percent' => 0,
            'created_by'                => $adminUserId > 0 ? $adminUserId : null,
            'activated_at'              => now(),
        ]);

        $this->assignCommissionToDriver($referral, $profile);

        return $referral->fresh();
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
     * Gán mã (0% HH / Khách của tôi) cho tài xế — thu hồi mã cũ trên cùng TX nếu có.
     */
    public function assignCommissionToDriver(ReferralCode $referral, DriverProfile $profile): void
    {
        if (! $referral->canAssignToDriver()) {
            throw new \InvalidArgumentException('Chỉ gán được mã «Khách của tôi» (0% hoa hồng) đang sử dụng.');
        }

        if (! $profile->isApproved()) {
            throw new \InvalidArgumentException('Chỉ gán mã cho tài xế đã được duyệt.');
        }

        $inbox = app(DriverInboxService::class);

        // Thu hồi mã đang gán cho tài xế này (nếu khác mã mới).
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

        $referral->update([
            'assigned_driver_profile_id' => $profile->id,
            'commission_percent'         => 0,
            'customer_discount_percent'  => 0,
        ]);
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
     * @deprecated Dùng {@see listDriverCustomers()} — giữ wrapper tương thích.
     */
    public function referredCustomersForCodes(iterable $referrals): Collection
    {
        $profileIds = collect($referrals)
            ->filter()
            ->map(fn (ReferralCode $referral): int => (int) ($referral->assigned_driver_profile_id ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($profileIds === []) {
            return collect();
        }

        return \App\Models\DriverCustomer::query()
            ->whereIn('driver_profile_id', $profileIds)
            ->orderByDesc('last_booked_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (\App\Models\DriverCustomer $row) => (object) [
                'passenger_name' => (string) ($row->passenger_name ?: '—'),
                'contact_phone'  => (string) ($row->contact_phone ?: '—'),
                'bookings_count' => (int) $row->bookings_count,
                'last_booked_at' => $row->last_booked_at,
            ])
            ->values();
    }

    /**
     * @return Collection<int, object{passenger_name: string, contact_phone: string, bookings_count: int, last_booked_at: mixed}>
     */
    public function listDriverCustomers(DriverProfile $profile): Collection
    {
        return \App\Models\DriverCustomer::query()
            ->where('driver_profile_id', $profile->id)
            ->orderByDesc('last_booked_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (\App\Models\DriverCustomer $row) => (object) [
                'passenger_name' => (string) ($row->passenger_name ?: '—'),
                'contact_phone'  => (string) ($row->contact_phone ?: '—'),
                'bookings_count' => (int) $row->bookings_count,
                'last_booked_at' => $row->last_booked_at,
            ])
            ->values();
    }

    /**
     * Sau khi hoàn thành chuyến: nếu booking dùng QR đã gán TX → lưu Khách của tôi.
     */
    public function rememberCustomerFromCompletedBooking(Booking $booking): void
    {
        $booking->loadMissing(['appliedReferralCode.assignedDriverProfile']);
        $referral = $booking->appliedReferralCode;

        if (! $referral || ! $referral->isAssignedCommissionCode()) {
            return;
        }

        $profile = $referral->assignedDriverProfile;
        if (! $profile) {
            return;
        }

        $phone = (string) ($booking->contact_phone ?? '');
        $digits = self::normalizePhone($phone);
        if ($digits === '') {
            return;
        }

        $phoneKey = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
        $now = $booking->completed_at ?? now();

        $existing = \App\Models\DriverCustomer::query()
            ->where('driver_profile_id', $profile->id)
            ->where('phone_key', $phoneKey)
            ->first();

        if ($existing) {
            $existing->update([
                'contact_phone'    => $phone !== '' ? $phone : $existing->contact_phone,
                'passenger_name'   => $booking->passenger_name ?: $existing->passenger_name,
                'referral_code_id' => $referral->id,
                'last_booking_id'  => $booking->id,
                'bookings_count'   => (int) $existing->bookings_count + 1,
                'last_booked_at'   => $now,
            ]);

            return;
        }

        \App\Models\DriverCustomer::query()->create([
            'driver_profile_id' => $profile->id,
            'contact_phone'     => $phone,
            'phone_key'         => $phoneKey,
            'passenger_name'    => $booking->passenger_name,
            'referral_code_id'  => $referral->id,
            'first_booking_id'  => $booking->id,
            'last_booking_id'   => $booking->id,
            'bookings_count'    => 1,
            'last_booked_at'    => $now,
        ]);
    }

    /**
     * TX ưu tiên: đã có trong Khách của tôi (SĐT) hoặc QR applied đang gán TX.
     */
    public function preferredDriverProfileForBooking(Booking $booking): ?DriverProfile
    {
        $phone = (string) ($booking->contact_phone ?? '');
        $digits = self::normalizePhone($phone);
        if ($digits !== '') {
            $phoneKey = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
            $fromList = \App\Models\DriverCustomer::query()
                ->where('phone_key', $phoneKey)
                ->with('driverProfile.user')
                ->orderByDesc('last_booked_at')
                ->orderByDesc('id')
                ->first();

            if ($fromList?->driverProfile) {
                return $fromList->driverProfile;
            }
        }

        $booking->loadMissing(['appliedReferralCode.assignedDriverProfile.user']);
        $referral = $booking->appliedReferralCode;
        if ($referral && $referral->isUsable() && $referral->isAssignedCommissionCode()) {
            return $referral->assignedDriverProfile;
        }

        return null;
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

        $referral = ReferralCode::query()
            ->where('code', $code)
            ->where('type', ReferralCode::TYPE_REFERRER)
            ->first();
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
