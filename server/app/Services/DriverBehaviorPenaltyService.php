<?php

namespace App\Services;

use App\Models\DriverDailyPenalty;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\DB;

class DriverBehaviorPenaltyService
{
    public const CONSECUTIVE_CANCEL_LOCK_LIMIT = 3;

    public const LATE_CONTINUE_DAILY_BLOCK_LIMIT = 2;

    public function recordConsecutiveCancel(DriverProfile $profile): bool
    {
        $locked = false;

        DB::transaction(function () use ($profile, &$locked): void {
            $row = $this->todayRow($profile, lock: true);
            $count = (int) $row->consecutive_cancel_count + 1;
            $row->update(['consecutive_cancel_count' => $count]);

            if ($count >= self::CONSECUTIVE_CANCEL_LOCK_LIMIT) {
                app(DriverMissedTripService::class)->lockProfile($profile->fresh(), $count);
                $locked = true;
            }
        });

        return $locked;
    }

    public function resetConsecutiveCancel(DriverProfile $profile): void
    {
        $row = $this->todayRow($profile);
        if ((int) $row->consecutive_cancel_count > 0) {
            $row->update(['consecutive_cancel_count' => 0]);
        }
    }

    public function recordLateContinueTimeout(DriverProfile $profile): bool
    {
        $blocked = false;

        DB::transaction(function () use ($profile, &$blocked): void {
            $row = $this->todayRow($profile, lock: true);
            $count = (int) $row->late_continue_timeout_count + 1;
            $row->update(['late_continue_timeout_count' => $count]);
            $blocked = $count >= self::LATE_CONTINUE_DAILY_BLOCK_LIMIT;
        });

        return $blocked;
    }

    public function isReceiveBlockedToday(DriverProfile $profile): bool
    {
        $row = DriverDailyPenalty::query()
            ->where('driver_profile_id', $profile->id)
            ->whereDate('penalty_date', today())
            ->first();

        return $row && (int) $row->late_continue_timeout_count >= self::LATE_CONTINUE_DAILY_BLOCK_LIMIT;
    }

    public function receiveBlockReason(DriverProfile $profile): ?string
    {
        if (! $this->isReceiveBlockedToday($profile)) {
            return null;
        }

        return 'Bạn đã bỏ qua thông báo đón khách 2 lần hôm nay — tạm không nhận cuốc mới.';
    }

    private function todayRow(DriverProfile $profile, bool $lock = false): DriverDailyPenalty
    {
        $query = DriverDailyPenalty::query()
            ->where('driver_profile_id', $profile->id)
            ->whereDate('penalty_date', today());

        if ($lock) {
            $existing = $query->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return DriverDailyPenalty::query()->create([
                'driver_profile_id' => $profile->id,
                'penalty_date'      => today(),
            ]);
        }

        return DriverDailyPenalty::query()->firstOrCreate(
            [
                'driver_profile_id' => $profile->id,
                'penalty_date'      => today(),
            ],
            [
                'consecutive_cancel_count'      => 0,
                'late_continue_timeout_count' => 0,
            ],
        );
    }
}
