<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\Schedule;

class DriverAssignmentService
{
    /** Tự phân bổ tài xế sẵn sàng cho chuyến chưa có tài xế (quản lý/admin). */
    public function autoAssignUnassigned(?int $operatorId = null): int
    {
        $assigned = 0;

        Schedule::query()
            ->with('vehicle')
            ->whereNull('driver_id')
            ->whereIn('status', ['scheduled', 'running'])
            ->where('departure_time', '>=', now())
            ->whereDoesntHave('driverTripRequests', fn ($q) => $q->where('status', 'pending'))
            ->when($operatorId, fn ($q) => $q->whereHas(
                'vehicle',
                fn ($v) => $v->where('operator_id', $operatorId)
            ))
            ->orderBy('departure_time')
            ->each(function (Schedule $schedule) use (&$assigned): void {
                $driver = $this->pickDriver($schedule);
                if (! $driver) {
                    return;
                }

                $this->assignToSchedule($schedule, $driver);
                $assigned++;
            });

        return $assigned;
    }

    public function pickDriver(Schedule $schedule): ?DriverProfile
    {
        return DriverProfile::query()
            ->with('user')
            ->operational()
            ->where('availability_status', 'available')
            ->orderByDesc('experience_years')
            ->first();
    }

    public function assignToSchedule(Schedule $schedule, DriverProfile $driver): void
    {
        $driverName = $driver->user->name;

        $schedule->update([
            'driver_id'   => $driver->user_id,
            'driver_name' => $driverName,
        ]);

        if ($schedule->status === 'running') {
            $driver->update(['availability_status' => 'on_trip']);
        }
    }
}
