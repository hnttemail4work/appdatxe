<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Models\Vehicle;

/** Đồng bộ xe + template đặt khách từ hồ sơ tài xế (admin). Mỗi tài xế một xe. */
class DriverCatalogService
{
    public function syncCatalogForDriver(DriverProfile $profile): void
    {
        $profile->loadMissing('user');

        if (! $this->canPublish($profile)) {
            $this->deactivateCatalogForDriver($profile);

            return;
        }

        $operatorId = $profile->operator_id
            ?? User::query()->where('role', 'admin')->where('status', 'active')->value('id');

        if (! $operatorId) {
            return;
        }

        $plate = trim((string) $profile->vehicle_license_plate);
        $vehicle = Vehicle::query()->updateOrCreate(
            ['license_plate' => $plate],
            [
                'operator_id' => $operatorId,
                'type'        => (string) $profile->vehicle_type,
                'capacity'    => (int) $profile->vehicle_seats,
                'status'      => 'active',
            ],
        );

        ScheduleTemplate::query()
            ->where('driver_id', $profile->user_id)
            ->update(['status' => 'inactive']);

        ScheduleTemplate::query()->updateOrCreate(
            ['driver_id' => $profile->user_id],
            [
                'vehicle_id'      => $vehicle->id,
                'route_id'        => null,
                'driver_name'     => $profile->user->name ?? 'Tài xế',
                'departure_time'  => null,
                'whole_car_price' => null,
                'status'          => 'active',
            ],
        );

        ScheduleTemplate::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('driver_id', '!=', $profile->user_id)
            ->update(['status' => 'inactive']);
    }

    public function syncAllApprovedDrivers(): void
    {
        DriverProfile::query()
            ->operational()
            ->where('approval_status', 'approved')
            ->with('user')
            ->orderBy('id')
            ->each(fn (DriverProfile $profile) => $this->syncCatalogForDriver($profile));

        $activeVehicleIds = ScheduleTemplate::query()
            ->where('status', 'active')
            ->whereNotNull('driver_id')
            ->pluck('vehicle_id')
            ->filter()
            ->unique()
            ->values();

        if ($activeVehicleIds->isNotEmpty()) {
            Vehicle::query()
                ->whereNotIn('id', $activeVehicleIds)
                ->update(['status' => 'inactive']);
        }
    }

    public function deactivateCatalogForDriver(DriverProfile $profile): void
    {
        if (! $profile->user_id) {
            return;
        }

        ScheduleTemplate::query()
            ->where('driver_id', $profile->user_id)
            ->update(['status' => 'inactive']);
    }

    private function canPublish(DriverProfile $profile): bool
    {
        if (! $profile->isApproved() || $profile->status !== 'active') {
            return false;
        }

        if (! $profile->user || $profile->user->status !== 'active' || $profile->user->role !== 'driver') {
            return false;
        }

        if (! filled($profile->vehicle_license_plate) || ! filled($profile->vehicle_type)) {
            return false;
        }

        return (int) ($profile->vehicle_seats ?? 0) > 0;
    }
}
