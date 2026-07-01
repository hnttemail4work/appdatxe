<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'vehicle_count')) {
                $table->unsignedTinyInteger('vehicle_count')->default(1)->after('booking_mode');
            }
            if (! Schema::hasColumn('bookings', 'vehicle_capacity')) {
                $table->unsignedSmallInteger('vehicle_capacity')->nullable()->after('vehicle_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'vehicle_capacity')) {
                $table->dropColumn('vehicle_capacity');
            }
            if (Schema::hasColumn('bookings', 'vehicle_count')) {
                $table->dropColumn('vehicle_count');
            }
        });
    }
};
