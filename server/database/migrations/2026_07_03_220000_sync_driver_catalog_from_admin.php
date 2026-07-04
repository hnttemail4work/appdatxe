<?php

use App\Models\ScheduleTemplate;
use App\Models\Vehicle;
use App\Services\DriverCatalogService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_profiles')) {
            return;
        }

        ScheduleTemplate::query()
            ->whereNull('driver_id')
            ->update(['status' => 'inactive']);

        app(DriverCatalogService::class)->syncAllApprovedDrivers();

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

        ScheduleTemplate::query()
            ->where('status', 'active')
            ->whereNull('driver_id')
            ->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        //
    }
};
