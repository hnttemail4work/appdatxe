<?php

use App\Models\DriverProfile;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_templates')) {
            return;
        }

        $drivers = User::query()
            ->where('role', 'driver')
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $vehicles = Vehicle::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($vehicles->values() as $index => $vehicle) {
            $driver = $drivers->get($index % max($drivers->count(), 1));

            ScheduleTemplate::query()
                ->where('vehicle_id', $vehicle->id)
                ->update([
                    'driver_id'   => $driver?->id,
                    'driver_name' => $driver?->name ?? 'Chờ phân bổ',
                ]);

            if (! $driver) {
                continue;
            }

            $profile = DriverProfile::query()->where('user_id', $driver->id)->first();
            if (! $profile) {
                continue;
            }

            $profile->update([
                'vehicle_license_plate' => $vehicle->license_plate,
                'vehicle_type'          => $vehicle->type,
                'vehicle_seats'         => $vehicle->capacity,
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
