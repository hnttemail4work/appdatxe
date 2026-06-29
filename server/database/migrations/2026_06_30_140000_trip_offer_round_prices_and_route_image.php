<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            $table->string('image_path')->nullable()->after('distance_km');
        });

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('whole_car_round_trip_price')->nullable()->after('whole_car_price');
            $table->unsignedBigInteger('seat_round_trip_price')->nullable()->after('seat_price');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->dropColumn(['whole_car_round_trip_price', 'seat_round_trip_price']);
        });

        Schema::table('routes', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });
    }
};
