<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('distance_km')->nullable()->after('base_price');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('trip_type', 20)->default('one_way')->after('seat_numbers');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            $table->dropColumn('distance_km');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('trip_type');
        });
    }
};
