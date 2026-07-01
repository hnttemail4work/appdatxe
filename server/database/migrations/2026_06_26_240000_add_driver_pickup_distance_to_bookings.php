<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'driver_pickup_distance_km')) {
                $table->decimal('driver_pickup_distance_km', 6, 1)->nullable()->after('pickup_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'driver_pickup_distance_km')) {
                $table->dropColumn('driver_pickup_distance_km');
            }
        });
    }
};
