<?php

namespace App\Services;

use App\Models\DriverCuocOfferHide;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Support\AuthIdentifier;
use Illuminate\Support\Collection;

class DriverCuocOfferHideService
{
    public function normalizePhone(string $phone): string
    {
        return AuthIdentifier::normalizePhone($phone);
    }

    public function recordMissedOffer(DriverTripRequest $request): void
    {
        $phone = $this->normalizePhone((string) $request->contact_phone);
        if ($phone === '') {
            return;
        }

        DriverCuocOfferHide::query()->firstOrCreate([
            'driver_user_id' => (int) $request->driver_id,
            'schedule_id'    => (int) $request->schedule_id,
            'contact_phone'  => $phone,
        ]);
    }

    public function isHidden(int $driverUserId, Schedule $schedule, string $contactPhone): bool
    {
        $phone = $this->normalizePhone($contactPhone);
        if ($phone === '') {
            return false;
        }

        return DriverCuocOfferHide::query()
            ->where('driver_user_id', $driverUserId)
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $phone)
            ->exists();
    }

    /** @return Collection<int, int> */
    public function hiddenDriverIdsForOffer(Schedule $schedule, string $contactPhone): Collection
    {
        $phone = $this->normalizePhone($contactPhone);
        if ($phone === '') {
            return collect();
        }

        return DriverCuocOfferHide::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $phone)
            ->pluck('driver_user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    public function clearForDriver(int $driverUserId): void
    {
        DriverCuocOfferHide::query()
            ->where('driver_user_id', $driverUserId)
            ->delete();
    }

    /** Gỡ chặn ẩn cuốc cho một tài xế trên chuyến + SĐT khách (khi đẩy offer mới). */
    public function clearForOffer(int $driverUserId, Schedule $schedule, string $contactPhone): void
    {
        $phone = $this->normalizePhone($contactPhone);
        if ($phone === '') {
            return;
        }

        DriverCuocOfferHide::query()
            ->where('driver_user_id', $driverUserId)
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $phone)
            ->delete();
    }

    /** Gỡ mọi chặn ẩn cuốc trên chuyến + SĐT khách. */
    public function clearHidesForContact(Schedule $schedule, string $contactPhone): void
    {
        $phone = $this->normalizePhone($contactPhone);
        if ($phone === '') {
            return;
        }

        DriverCuocOfferHide::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $phone)
            ->delete();
    }
}
