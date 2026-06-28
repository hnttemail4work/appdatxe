<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->decimal('seat_price', 12, 2)->nullable()->after('departure_time');
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->decimal('seat_price', 12, 2)->nullable()->after('departure_time');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('expired_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('expired_at');
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropColumn('seat_price');
        });

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->dropColumn('seat_price');
        });
    }
};
