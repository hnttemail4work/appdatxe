<?php

namespace App\Services;

use App\Models\DriverProfile;

/** Thống kê tỷ lệ từ chối cuốc — tách khỏi khóa/hủy chuyến hiện có. */
class DriverCancelRateService
{
    public function recordOfferForUserId(int $driverUserId): void
    {
        $profile = DriverProfile::query()->where('user_id', $driverUserId)->first();
        if (! $profile) {
            return;
        }

        $this->bumpCounts($profile, offerDelta: 1);
    }

    public function recordRejectForUserId(int $driverUserId): void
    {
        $profile = DriverProfile::query()->where('user_id', $driverUserId)->first();
        if (! $profile) {
            return;
        }

        $this->bumpCounts($profile, rejectDelta: 1);
    }

    public function reset(DriverProfile $profile): void
    {
        $profile->update([
            'cuoc_offer_count'    => 0,
            'cuoc_reject_count'   => 0,
            'cancel_rate_percent' => 0,
        ]);
    }

    private function bumpCounts(DriverProfile $profile, int $offerDelta = 0, int $rejectDelta = 0): void
    {
        if ($offerDelta === 0 && $rejectDelta === 0) {
            return;
        }

        $offerCount = max(0, (int) $profile->cuoc_offer_count + $offerDelta);
        $rejectCount = max(0, (int) $profile->cuoc_reject_count + $rejectDelta);

        $profile->update([
            'cuoc_offer_count'    => $offerCount,
            'cuoc_reject_count'   => $rejectCount,
            'cancel_rate_percent' => $this->percentFromCounts($offerCount, $rejectCount),
        ]);
    }

    private function percentFromCounts(int $offerCount, int $rejectCount): float
    {
        if ($offerCount <= 0) {
            return 0.0;
        }

        return round(($rejectCount / $offerCount) * 100, 1);
    }
}
