<?php

use App\Models\ScheduleTemplate;
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

        ScheduleTemplate::query()->update([
            'route_id'        => null,
            'whole_car_price' => null,
        ]);

        Vehicle::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->each(function (Vehicle $vehicle): void {
                ScheduleTemplate::query()->updateOrCreate(
                    ['vehicle_id' => $vehicle->id],
                    [
                        'route_id'        => null,
                        'driver_id'       => null,
                        'driver_name'     => 'Chờ khách đặt',
                        'departure_time'  => null,
                        'whole_car_price' => null,
                        'status'          => 'active',
                    ],
                );
            });
    }

    public function down(): void
    {
        //
    }
};
