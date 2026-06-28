<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->decimal('whole_car_price', 12, 2)->nullable()->after('seat_price');
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->decimal('whole_car_price', 12, 2)->nullable()->after('seat_price');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropColumn('whole_car_price');
        });

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->dropColumn('whole_car_price');
        });
    }
};
