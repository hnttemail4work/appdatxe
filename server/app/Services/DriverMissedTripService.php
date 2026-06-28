<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use Illuminate\Support\Facades\DB;

class DriverMissedTripService
{
    public const STRIKE_LIMIT = 3;

    /** Ghi nhận tài xế không nhận chuyến khi đã quá giờ khởi hành. */
    public function processExpiredPendingRequests(): void
    {
        $requests = DriverTripRequest::query()
            ->with('schedule')
            ->where('status', 'pending')
            ->whereHas('schedule', fn ($q) => $q->where('departure_time', '<=', now()))
            ->get();

        if ($requests->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($requests): void {
            foreach ($requests as $request) {
                $this->recordStrike((int) $request->driver_id);
            }

            DriverTripRequest::query()
                ->whereIn('id', $requests->pluck('id'))
                ->update([
                    'status'       => 'expired',
                    'responded_at' => now(),
                ]);
        });
    }

    public function recordStrike(int $driverUserId): void
    {
        $profile = DriverProfile::query()->where('user_id', $driverUserId)->first();
        if (! $profile || $profile->missed_trip_locked_at) {
            return;
        }

        $strikes = (int) $profile->missed_trip_strikes + 1;

        if ($strikes >= self::STRIKE_LIMIT) {
            $this->lockProfile($profile, $strikes);

            return;
        }

        $profile->update(['missed_trip_strikes' => $strikes]);
    }

    public function lockProfile(DriverProfile $profile, ?int $strikes = null): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile, $strikes): void {
            $profile->update([
                'missed_trip_strikes'   => $strikes ?? self::STRIKE_LIMIT,
                'missed_trip_locked_at' => now(),
                'status'                => 'inactive',
                'availability_status'   => 'off_duty',
            ]);
            $profile->user->update(['status' => 'inactive']);
        });
    }

    public function unlock(DriverProfile $profile): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile): void {
            $profile->update([
                'missed_trip_strikes'   => 0,
                'missed_trip_locked_at' => null,
                'status'                => 'active',
                'availability_status'   => 'available',
            ]);
            $profile->user->update(['status' => 'active']);
        });
    }

    public function isLocked(DriverProfile $profile): bool
    {
        return $profile->missed_trip_locked_at !== null;
    }
}
